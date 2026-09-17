# SMB-Share als optional eingebundenes Backupziel

Das vorhandene SMB-Share dient als clusterunabhängiges Backupziel, ist aber nicht dauerhaft verbunden. Ein dedizierter Kubernetes-Backup-Job bindet es bei der Ausführung über die passende Storage-/CSI-Konfiguration ein und schreibt PostgreSQL-Backups dorthin; die Anwendungspods benötigen keinen Zugriff auf das Share.

## Konsequenzen

- SMB-Zugangsdaten werden als Kubernetes-Secret verwaltet.
- Backupfehler dürfen den Anwendungsbetrieb nicht blockieren.
- Fehlgeschlagene oder ausbleibende Backups müssen überwacht und sichtbar gemeldet werden.
- Ein unerreichbares Share führt zu Wiederholungsversuchen mit Backoff und einem Alarm; ein lokaler Puffer ist höchstens eine kurzzeitige optionale Ergänzung.
