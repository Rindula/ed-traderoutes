"""Safe, unattended updater for the ED Trade Routes EDMC plugin."""

from __future__ import annotations

import hashlib
import json
import logging
import shutil
import tempfile
import urllib.request
import zipfile
from dataclasses import dataclass
from pathlib import Path, PurePosixPath
from typing import Callable


REPOSITORY = "Rindula/ed-traderoutes"
PLUGIN_DIRECTORY = "EDTradeRoutes"
ARCHIVE_NAME = "ed-traderoutes-edmc-plugin.zip"
CHECKSUM_NAME = f"{ARCHIVE_NAME}.sha256"

_logger = logging.getLogger("EDTradeRoutes.updater")


@dataclass(frozen=True)
class Release:
    version: str
    archive_url: str
    checksum_url: str | None


def read_version(plugin_dir: Path) -> str:
    try:
        return (plugin_dir / "VERSION").read_text(encoding="utf-8").strip() or "0.0.0"
    except OSError:
        return "0.0.0"


def version_key(version: str) -> tuple[int, ...]:
    """Convert CalVer or legacy versions into a comparable tuple."""
    values: list[int] = []
    for part in version.removeprefix("v").split("."):
        digits = "".join(character for character in part if character.isdigit())
        values.append(int(digits or 0))
    return tuple(values)


def check_latest(opener: Callable[..., object] = urllib.request.urlopen) -> Release | None:
    request = urllib.request.Request(
        f"https://api.github.com/repos/{REPOSITORY}/releases/latest",
        headers={"Accept": "application/vnd.github+json", "User-Agent": "EDTradeRoutes-EDMC"},
    )
    with opener(request, timeout=10) as response:  # type: ignore[union-attr]
        payload = json.loads(response.read().decode("utf-8"))
    tag = str(payload.get("tag_name", "")).removeprefix("v")
    if not tag or not version_key(tag):
        return None
    assets = payload.get("assets", [])
    archive_url = next(
        (str(asset.get("browser_download_url")) for asset in assets if asset.get("name") == ARCHIVE_NAME),
        None,
    )
    checksum_url = next(
        (str(asset.get("browser_download_url")) for asset in assets if asset.get("name") == CHECKSUM_NAME),
        None,
    )
    return Release(tag, archive_url, checksum_url) if archive_url else None


def install_release(
    release: Release,
    plugin_dir: Path,
    opener: Callable[..., object] = urllib.request.urlopen,
) -> None:
    """Download, verify, and unpack a release into the installed plugin directory."""
    with tempfile.TemporaryDirectory(prefix="ed-traderoutes-update-") as temporary:
        temporary_dir = Path(temporary)
        archive = temporary_dir / ARCHIVE_NAME
        _download(release.archive_url, archive, opener)
        if release.checksum_url:
            checksum = temporary_dir / CHECKSUM_NAME
            _download(release.checksum_url, checksum, opener)
            expected = checksum.read_text(encoding="utf-8").split()[0].lower()
            actual = hashlib.sha256(archive.read_bytes()).hexdigest()
            if expected != actual:
                raise ValueError("ED Trade Routes update checksum mismatch")

        extraction = temporary_dir / "extracted"
        with zipfile.ZipFile(archive) as package:
            names = package.namelist()
            prefix = f"{PLUGIN_DIRECTORY}/"
            safe_names = [PurePosixPath(name) for name in names]
            if any(path.is_absolute() or ".." in path.parts for path in safe_names):
                raise ValueError("ED Trade Routes update contains unsafe archive paths")
            if not any(name.startswith(f"{prefix}load.py") for name in names):
                raise ValueError("ED Trade Routes update has an invalid archive layout")
            package.extractall(extraction)

        source = extraction / PLUGIN_DIRECTORY
        plugin_dir.mkdir(parents=True, exist_ok=True)
        for path in source.rglob("*"):
            if path.is_file():
                target = plugin_dir / path.relative_to(source)
                target.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(path, target)


def _download(url: str, destination: Path, opener: Callable[..., object]) -> None:
    request = urllib.request.Request(url, headers={"User-Agent": "EDTradeRoutes-EDMC"})
    with opener(request, timeout=30) as response:  # type: ignore[union-attr]
        destination.write_bytes(response.read())


def update_if_available(
    plugin_dir: Path,
    opener: Callable[..., object] = urllib.request.urlopen,
) -> str | None:
    """Install the latest release when it is newer; return the installed version."""
    current = read_version(plugin_dir)
    release = check_latest(opener)
    if release is None or version_key(release.version) <= version_key(current):
        return None
    install_release(release, plugin_dir, opener)
    _logger.info("Updated ED Trade Routes plugin from %s to %s; restart EDMC", current, release.version)
    return release.version
