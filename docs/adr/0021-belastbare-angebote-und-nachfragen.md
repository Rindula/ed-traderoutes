# Nur belastbare Angebot- und Nachfragedaten für Standardrouten

Die Standard-Routenberechnung berücksichtigt nur Trades mit bekanntem ausreichendem Angebot, bekannter Nachfrage und einer ableitbaren handelbaren Menge. Fehlende Daten werden nicht als unbegrenzt verfügbar interpretiert; unsichere Trades können später separat als Warnkandidaten angeboten werden.

## Konsequenzen

- Marktbeobachtungen müssen Angebots-/Nachfragequalität und handelbare Mengen unterscheiden können.
- Der erwartete Stundenprofit basiert auf realistisch transportierbaren Mengen.
- Eine Route kann trotz hohem Preisunterschied ausgeschlossen werden, wenn die Absatzsicherheit fehlt.
