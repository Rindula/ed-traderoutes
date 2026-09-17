# Begrenzte Historie für Marktbeobachtungen

Die jeweils aktuelle Marktbeobachtung je Ware und Station bleibt dauerhaft erhalten. Normalisierte historische Beobachtungen werden 30 Tage aufbewahrt; rohe EDDN-Nachrichten dienen nur der Fehlersuche und werden spätestens nach 72 Stunden gelöscht.

## Konsequenzen

- Ein automatischer Bereinigungsjob ist Teil des Betriebsmodells.
- Die Datenbank enthält eine kompakte, routenrelevante Historie statt unbegrenzt wachsender Rohdaten.
- Globale EDDN-Daten werden nicht benutzerbezogen gelöscht.
