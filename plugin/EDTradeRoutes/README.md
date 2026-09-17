# ED Trade Routes EDMC plugin

This plugin synchronizes EDMC journal events with an ED Trade Routes server.
It uses outbound HTTPS only and keeps up to 500 events in a local queue while
the server is unavailable. Events are replayed in sequence order and use a
stable event ID so retries are safe.

## Installation

1. In EDMC, open **File → Settings → Plugins → Open**.
2. Extract the `EDTradeRoutes` directory from the release ZIP into the plugins directory.
3. Restart EDMC.
4. Open the plugin preferences and enter:
   - **Server URL**, for example `https://trade-routes.rindula.de`
   - **Sync key ID** and **Sync key** created in the web application
5. Use **Test connection** and then save.

The key is stored in EDMC's plugin data directory. Do not share the directory
or the key. Revoke the key in the web application if the installation is lost.

## Payload contract

- `POST /api/plugin/heartbeat` every 60 seconds.
- `POST /api/plugin/events` for journal events.
- Headers: `X-EDMC-Key-Id` and `X-EDMC-Key`.
- Event fields: `eventId`, `eventType`, `sequence`, `sourceTimestamp`, `payload`.
