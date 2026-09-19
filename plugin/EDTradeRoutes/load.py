"""ED Trade Routes integration for EDMC.

The plugin uses only outbound HTTPS requests and keeps a bounded local queue so
journal events are not lost during short network outages.
"""

from __future__ import annotations

import hashlib
import importlib.util
import json
import logging
import os
import queue
import sys
import threading
import time
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any

try:
    import updater
except ImportError:
    _updater_spec = importlib.util.spec_from_file_location(
        "ed_trade_routes_updater", Path(__file__).with_name("updater.py")
    )
    if _updater_spec is None or _updater_spec.loader is None:
        raise
    updater = importlib.util.module_from_spec(_updater_spec)
    sys.modules[_updater_spec.name] = updater
    _updater_spec.loader.exec_module(updater)


PLUGIN_NAME = "ED Trade Routes"
DEFAULT_HEARTBEAT_SECONDS = 60
MAX_QUEUE_SIZE = 500
REQUEST_TIMEOUT_SECONDS = 10

_logger = logging.getLogger("EDTradeRoutes")
_stop_event = threading.Event()
_worker: threading.Thread | None = None
_update_worker: threading.Thread | None = None
_client: "ApiClient | None" = None
_prefs_state: dict[str, Any] | None = None


def plugin_start3(plugin_dir: str) -> str:
    """EDMC entry point called when the plugin is loaded."""
    global _client, _worker, _update_worker
    data_dir = Path(plugin_dir) / "data"
    data_dir.mkdir(parents=True, exist_ok=True)
    _client = ApiClient(data_dir)
    _stop_event.clear()
    _worker = threading.Thread(target=_heartbeat_loop, name="ed-trade-routes-heartbeat", daemon=True)
    _worker.start()
    _update_worker = threading.Thread(
        target=_update_plugin,
        args=(Path(plugin_dir),),
        name="ed-trade-routes-updater",
        daemon=True,
    )
    _update_worker.start()
    return PLUGIN_NAME


def plugin_stop() -> None:
    """EDMC entry point called before the plugin is unloaded."""
    _stop_event.set()
    if _worker is not None:
        _worker.join(timeout=2)


def journal_entry(cmdr: str, is_beta: bool, system: str, station: str | None, entry: dict[str, Any], state: dict[str, Any]) -> None:
    """Forward a journal event; failures are persisted in the offline queue."""
    if _client is None or is_beta:
        return
    _client.enqueue_event(system, station, entry, state)


def plugin_app(parent: Any) -> Any:
    """Render a small status panel in EDMC's plugin tab when tkinter is available."""
    try:
        from tkinter import ttk
    except ImportError:
        return None
    frame = ttk.Frame(parent)
    ttk.Label(frame, text=PLUGIN_NAME).grid(row=0, column=0, sticky="w")
    ttk.Label(frame, text="Events are synchronized in the background.").grid(row=1, column=0, sticky="w")
    ttk.Label(frame, text="Updates are checked automatically when EDMC starts.").grid(row=2, column=0, sticky="w")
    return frame


def plugin_prefs(parent: Any, cmdr: str | None, is_beta: bool) -> Any:
    """Provide EDMC preferences for the server and personal synchronization key."""
    try:
        import tkinter as tk
        from tkinter import ttk
        import myNotebook as nb
    except ImportError:
        return None
    if _client is None:
        return None
    global _prefs_state
    frame = nb.Frame(parent)
    values = _client._config
    base_url = tk.StringVar(value=str(values.get("base_url", "")))
    key_id = tk.StringVar(value=str(values.get("key_id", "")))
    key = tk.StringVar(value=str(values.get("key", "")))
    _prefs_state = {"base_url": base_url, "key_id": key_id, "key": key}
    for row, label, variable, show in (
        (0, "Server URL", base_url, None),
        (1, "Sync key ID", key_id, None),
        (2, "Sync key", key, "*"),
    ):
        nb.Label(frame, text=label).grid(row=row, column=0, sticky="w")
        ttk.Entry(frame, textvariable=variable, show=show or "").grid(row=row, column=1, sticky="ew")
    frame.columnconfigure(1, weight=1)

    def save() -> None:
        _client.update_config(base_url.get().strip(), key_id.get().strip(), key.get())

    ttk.Button(frame, text="Save", command=save).grid(row=3, column=1, sticky="e")
    return frame


def prefs_changed(cmdr: str | None, is_beta: bool) -> None:
    """Persist values when EDMC closes its settings dialog."""
    if _client is None or _prefs_state is None:
        return
    _client.update_config(
        _prefs_state["base_url"].get().strip(),
        _prefs_state["key_id"].get().strip(),
        _prefs_state["key"].get(),
    )


def _update_plugin(plugin_dir: Path) -> None:
    try:
        updater.update_if_available(plugin_dir)
    except Exception:
        _logger.exception("Automatic plugin update failed")


def _heartbeat_loop() -> None:
    while not _stop_event.is_set():
        if _client is not None:
            _client.flush_queue()
            _client.heartbeat()
        _stop_event.wait(DEFAULT_HEARTBEAT_SECONDS)


class ApiClient:
    def __init__(self, data_dir: Path) -> None:
        self._config_path = data_dir / "config.json"
        self._queue_path = data_dir / "events.json"
        self._lock = threading.Lock()
        self._config = self._read_json(self._config_path, {})
        self._events: list[dict[str, Any]] = self._read_json(self._queue_path, [])
        if not isinstance(self._events, list):
            self._events = []
        self._sequence = int(self._config.get("sequence", 0))

    def update_config(self, base_url: str, key_id: str, key: str) -> None:
        with self._lock:
            self._config.update({"base_url": base_url, "key_id": key_id, "key": key})
            self._write_state()

    def heartbeat(self) -> bool:
        response = self._request("/api/plugin/heartbeat", {})
        return response is not None

    def enqueue_event(self, system: str, station: str | None, entry: dict[str, Any], state: dict[str, Any]) -> None:
        with self._lock:
            self._sequence += 1
            timestamp = str(entry.get("timestamp") or _utc_now())
            event = {
                "eventId": self._event_id(timestamp, entry),
                "eventType": str(entry.get("event") or "unknown"),
                "sequence": self._sequence,
                "sourceTimestamp": timestamp,
                "payload": _payload(system, station, entry, state),
            }
            if len(self._events) >= MAX_QUEUE_SIZE:
                self._events.pop(0)
            self._events.append(event)
            self._config["sequence"] = self._sequence
            self._write_state()
        self.flush_queue()

    def flush_queue(self) -> None:
        while True:
            with self._lock:
                if not self._events:
                    return
                event = self._events[0]
            if self._request("/api/plugin/events", event) is None:
                return
            with self._lock:
                if self._events and self._events[0].get("eventId") == event.get("eventId"):
                    self._events.pop(0)
                    self._write_state()

    def _request(self, path: str, body: dict[str, Any]) -> dict[str, Any] | None:
        base_url = str(self._config.get("base_url", "")).rstrip("/")
        key_id = str(self._config.get("key_id", ""))
        key = str(self._config.get("key", ""))
        if not base_url or not key_id or not key:
            return None
        request = urllib.request.Request(
            base_url + path,
            data=json.dumps(body).encode("utf-8"),
            headers={
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-EDMC-Key-Id": key_id,
                "X-EDMC-Key": key,
                "User-Agent": "EDTradeRoutes-EDMC/1.0",
            },
            method="POST",
        )
        try:
            with urllib.request.urlopen(request, timeout=REQUEST_TIMEOUT_SECONDS) as response:
                return json.loads(response.read().decode("utf-8"))
        except (OSError, urllib.error.HTTPError, json.JSONDecodeError) as exc:
            _logger.debug("ED Trade Routes request failed: %s", exc)
            return None

    def _write_state(self) -> None:
        _atomic_write(self._config_path, self._config)
        _atomic_write(self._queue_path, self._events)

    @staticmethod
    def _event_id(timestamp: str, entry: dict[str, Any]) -> str:
        canonical = json.dumps({"timestamp": timestamp, "entry": entry}, sort_keys=True, separators=(",", ":"))
        return hashlib.sha256(canonical.encode("utf-8")).hexdigest()[:26]

    @staticmethod
    def _read_json(path: Path, default: Any) -> Any:
        try:
            return json.loads(path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            return default


def _payload(system: str, station: str | None, entry: dict[str, Any], state: dict[str, Any]) -> dict[str, Any]:
    payload: dict[str, Any] = {
        "system": system,
        "station": station,
        "journal": entry,
    }
    for source, target in ((state, "cargoCapacity"),):
        if isinstance(source.get("CargoCapacity"), int):
            payload[target] = source["CargoCapacity"]
    if isinstance(state.get("Cargo"), dict):
        payload["cargo"] = state["Cargo"]
    return payload


def _utc_now() -> str:
    return time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())


def _atomic_write(path: Path, value: Any) -> None:
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(json.dumps(value, indent=2, sort_keys=True), encoding="utf-8")
    os.replace(temporary, path)
