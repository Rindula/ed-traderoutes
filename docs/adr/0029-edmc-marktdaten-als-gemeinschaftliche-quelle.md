# EDMC-Marktdaten als gemeinschaftliche Quelle

Normalisierte Marktbeobachtungen aus EDMC werden standardmäßig gemeinsam mit EDDN-Daten für alle Benutzer nutzbar gemacht. Die rohen Synchronisierungsereignisse, Commander-Identität und persönliche Bewegungsdaten bleiben privat; globale Marktwerte enthalten notwendigerweise System, Station, Ware, Wert und Beobachtungszeitpunkt für den Abgleich.

## Konsequenzen

- Die Datenpipeline muss persönliche Ereignisdaten von globalen Marktwerten trennen.
- Marktbeobachtungen dürfen keine Benutzer- oder Commander-Identitäten und keinen persönlichen Bewegungsverlauf offenlegen.
- Die zeitlich neueste Beobachtung gewinnt unabhängig von EDDN- oder EDMC-Herkunft.
- Benutzer können das gemeinschaftliche Teilen deaktivieren.
