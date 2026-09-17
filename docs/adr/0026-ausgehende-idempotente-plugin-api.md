# Ausgehende idempotente Plugin-API

Das EDMC-Plugin synchronisiert ausschließlich über ausgehende HTTPS-REST-Aufrufe mit einem persönlichen Synchronisierungsschlüssel. Heartbeats und Synchronisierungsereignisse verwenden dieselbe API; Wiederholungen sind erlaubt und müssen idempotent verarbeitet werden.

## Konsequenzen

- Die Anwendung benötigt keine eingehende Verbindung zum Spieler-PC.
- Jedes Ereignis muss eine stabile externe Kennung und einen Beobachtungszeitpunkt besitzen.
- Netzwerkfehler und Wiederholungen dürfen keine doppelten Fracht- oder Fortschrittsänderungen erzeugen.
