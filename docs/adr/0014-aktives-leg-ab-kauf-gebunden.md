# Aktives Leg ab dem Kauf unveränderlich binden

Sobald das EDMC-Plugin ein bestätigtes Kaufereignis für das aktive Leg synchronisiert, wird dieses Leg gebunden. Neue Marktbeobachtungen und Neuberechnungen dürfen das gebundene Leg nicht ersetzen; sie dürfen nur nachfolgende Legs neu planen. Die Bindung endet nach Verkauf oder bestätigtem Leg-Abschluss.

## Konsequenzen

- Kaufereignisse müssen zuverlässig und idempotent verarbeitet werden.
- Der Frachtzustand und das gebundene Leg gehören zur persönlichen Laufzeit eines Benutzers.
- Ein Preisverfall nach dem Kauf erzeugt eine Warnung, aber keine automatische Umleitung des aktiven Legs.
