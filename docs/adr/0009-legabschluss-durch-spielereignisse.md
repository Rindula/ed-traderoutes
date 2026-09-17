# Leg-Abschluss durch Spielereignisse

Ein aktuelles Leg wird nicht allein bei der Ankunft im Zielsystem abgeschlossen. Der bevorzugte Abschlussnachweis ist Docking, ergänzt durch ein verfügbares Marktbesuchs- oder Verkaufsereignis. Fehlen diese Ereignisse, bleibt das Leg offen und kann manuell bestätigt werden.

## Konsequenzen

- Das EDMC-Plugin muss relevante Spielereignisse mit Zeitstempel übertragen.
- Die Fortschrittsanzeige benötigt mindestens die Zustände geplant, unterwegs und abgeschlossen.
- Fehlende Events dürfen nicht stillschweigend als erfolgreicher Handel interpretiert werden.
