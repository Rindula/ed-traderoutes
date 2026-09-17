# Pluginstatus steuert den Betriebsmodus

Ein aktives EDMC-Plugin ist die Autorität für automatisch synchronisierte Spielerdaten und sperrt deren manuelle Änderung. Wird das Plugin inaktiv, werden automatische Synchronisierung, Fortschrittsfortschreibung und automatische Leg-Übergänge deaktiviert; manuelle Routenplanung mit vorhandenen Daten bleibt verfügbar.

## Konsequenzen

- Der Pluginstatus muss zeitbezogen und pro Benutzer geführt werden.
- Die Anwendung benötigt einen eindeutigen Übergang zwischen Aktiv- und manuellem Modus.
- Ein Statusindikator muss sichtbar machen, ob Automatikfunktionen derzeit vertrauenswürdig aktiv sind.
