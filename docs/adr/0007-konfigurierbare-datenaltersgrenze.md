# Konfigurierbare Datenaltersgrenze für Routen

Für aktuelle Routenempfehlungen werden standardmäßig nur Marktbeobachtungen verwendet, die höchstens zwei Stunden alt sind. Die Grenze ist konfigurierbar; sie bezieht sich auf den Beobachtungszeitpunkt und gilt unabhängig von der Quelle gleichermaßen für EDMC und EDDN.

## Konsequenzen

- Veraltete Preise dürfen den erwarteten Stundenprofit nicht unbemerkt beeinflussen.
- Die Oberfläche muss die Datenfrische der Empfehlung sichtbar machen.
- Ein späterer automatischer Aktualisierungsmechanismus kann dieselbe Regel verwenden.
