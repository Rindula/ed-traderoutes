# Offline-Queue für Plugin-Ereignisse

Das EDMC-Plugin puffert Synchronisierungsereignisse lokal, wenn die Anwendung nicht erreichbar ist, und liefert sie nach Wiederherstellung der Verbindung chronologisch nach. Ereignisse behalten ihren ursprünglichen Spielzeitpunkt; erkannte Sequenzlücken führen serverseitig zu einem unsicheren Frachtstatus.

## Konsequenzen

- Die lokale Queue benötigt eine begrenzte Größe und eine sichtbare Fehleranzeige.
- Nachlieferung muss mit der idempotenten API-Verarbeitung zusammenspielen.
- Sehr alte oder beschädigte Ereignisse dürfen nicht stillschweigend den Frachtstatus verändern.
