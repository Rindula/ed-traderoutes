# Heartbeat zur Erkennung der Plugin-Inaktivität

Das EDMC-Plugin sendet standardmäßig alle 60 Sekunden ein Lebenszeichen. Nach drei verpassten Lebenszeichen gilt es als inaktiv und der Benutzer wechselt in den manuellen Modus; beim nächsten gültigen Lebenszeichen wird der Aktivmodus wiederhergestellt.

## Konsequenzen

- Plugin-Aktivität wird über eine zeitbasierte Statusprüfung und nicht über eine dauerhafte Verbindung bestimmt.
- Ein Ausfall pausiert automatische Folgeaktionen, ersetzt aber kein gebundenes Leg.
- Aktivitätswechsel müssen für den Benutzer sichtbar sein.
