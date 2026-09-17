# Mehrbenutzerfähigkeit mit getrenntem Datenbesitz

Die Anwendung wird mehrbenutzerfähig. Spielerkontext, EDMC-Ereignisse und Routenpräferenzen gehören jeweils einem Benutzer und werden voneinander getrennt; gemeinsam nutzbare EDDN-Marktbeobachtungen werden zentral verarbeitet.

## Konsequenzen

- Synchronisierungszugriffe müssen einem Benutzer eindeutig zugeordnet und autorisiert werden.
- Persönliche Daten dürfen nicht über Benutzergrenzen hinweg in Routenempfehlungen einfließen.
- Der EDMC-Zugang benötigt persönliche, widerrufbare Synchronisierungsschlüssel, die verschlüsselt gespeichert und nach erneuter Authentifizierung erneut angezeigt werden können.
- Jeder erfolgreich über Authentik authentifizierte Benutzer darf die Anwendung nutzen; eine Gruppenfreigabe ist nicht erforderlich.
