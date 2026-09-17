# Wiederholungen erfordern eine zustandsbehaftete Frachtplanung

Eine Handelsroute darf Systeme und Stationen wiederholt besuchen. Wiederholte Besuche werden jedoch nur als gültige und profitable Planungsschritte bewertet, wenn die Berechnung den fortlaufenden Frachtzustand berücksichtigt: verfügbare Kapazität, geladene Waren, Teilmengen und Verkäufe an den jeweiligen Märkten.

## Konsequenzen

- Die Routenberechnung darf Handelsabschnitte nicht unabhängig voneinander bewerten.
- Ein Frachtplan muss neben der Stationsfolge ausgegeben oder nachvollziehbar sein.
- Jede neue Routenberechnung beginnt mit leerem Frachtraum; die aktuelle Fracht des Spielers wird nicht als Startbestand eingeplant.
- Die Route muss dennoch den während ihrer eigenen Abschnitte aufgebauten Frachtzustand fortschreiben.
