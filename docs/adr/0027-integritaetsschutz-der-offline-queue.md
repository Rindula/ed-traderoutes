# Integritätsschutz der Offline-Queue

Die lokale EDMC-Offline-Queue wird verschlüsselt gespeichert und mit einem gerätebezogenen Schlüssel signiert. Die Anwendung prüft Signatur, Ereigniskette, Zeitstempel und Ereigniskennung; bei einer Prüfungslücke gilt der Frachtstatus als unsicher. Dieser Mechanismus schützt gegen einfache oder versehentliche Änderungen, ist aber kein Schutz gegen einen Benutzer mit vollständiger Kontrolle über seinen Rechner.

## Konsequenzen

- Der Schlüssel soll nach Möglichkeit im Betriebssystem-Schlüsselbund liegen.
- Ereignisse benötigen stabile Kennungen, Sequenzen und ursprüngliche Zeitstempel.
- Schlüsselverlust erfordert eine sichtbare Neuinitialisierung des Geräts und kann gepufferte Events unbrauchbar machen.
