# Beste Route automatisch aktivieren, Alternativen auswählbar machen

Nach einer erfolgreichen Berechnung wird die beste Route automatisch als aktive Route gesetzt. Der Benutzer darf eine angezeigte Alternative auswählen. Ist das aktuelle Leg jedoch bereits durch einen Kauf gebunden, darf keine Auswahl oder Neuberechnung dieses Leg ersetzen; eine alternative Route kann nur für die Folgeplanung vorgemerkt werden.

## Konsequenzen

- Die Anwendung muss aktive Route und Routenvorschläge getrennt behandeln.
- Ein Wechsel ohne gebundene Fracht ist sofort möglich.
- Ein Wechsel mit gebundener Fracht wird als spätere Route bzw. Folgeplanung gespeichert.
