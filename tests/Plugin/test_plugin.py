import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import sys

sys.path.insert(0, str(Path(__file__).parents[2] / "plugin" / "EDTradeRoutes"))
import load  # noqa: E402


class PluginClientTest(unittest.TestCase):
    def test_event_is_queued_with_contract_and_flushed(self):
        with tempfile.TemporaryDirectory() as directory:
            data = Path(directory)
            (data / "config.json").write_text(json.dumps({
                "base_url": "https://example.test",
                "key_id": "id",
                "key": "secret",
            }))
            client = load.ApiClient(data)
            with patch.object(client, "_request", return_value=None):
                client.enqueue_event("Sol", "Jameson Memorial", {"event": "Docked", "timestamp": "2026-01-01T00:00:00Z"}, {})
            queued = json.loads((data / "events.json").read_text())
            self.assertEqual(len(queued), 1)
            self.assertEqual(set(queued[0]), {"eventId", "eventType", "sequence", "sourceTimestamp", "payload"})
            self.assertEqual(queued[0]["sequence"], 1)

    def test_successful_flush_removes_event(self):
        with tempfile.TemporaryDirectory() as directory:
            data = Path(directory)
            (data / "config.json").write_text(json.dumps({"base_url": "https://example.test", "key_id": "id", "key": "secret"}))
            client = load.ApiClient(data)
            with patch.object(client, "_request", return_value={"status": "accepted"}):
                client.enqueue_event("Sol", None, {"event": "FSDJump", "timestamp": "2026-01-01T00:00:00Z"}, {})
            self.assertEqual(json.loads((data / "events.json").read_text()), [])


if __name__ == "__main__":
    unittest.main()
