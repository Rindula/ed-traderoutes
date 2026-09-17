# Kubernetes als Zielumgebung

Die Anwendung wird für den Betrieb in einem Kubernetes-Cluster entwickelt. Webanwendung, EDDN-Verarbeitung und Routenberechnung werden als getrennt skalierbare Laufzeitkomponenten betrachtet; PostgreSQL und Redis werden ebenfalls im Cluster betrieben. Lokale Container-Orchestrierung dient nur der Entwicklung.

## Konsequenzen

- Zustände müssen außerhalb einzelner Pods persistiert werden.
- Worker und geplante Katalogaktualisierungen benötigen eigene Kubernetes-Ressourcen.
- Konfiguration und Secrets für Authentik, EDDN und Datenbanken werden clustergeeignet verwaltet.
- PostgreSQL und Redis benötigen persistente Volumes sowie einen vom Pod-Lebenszyklus unabhängigen Betriebs- und Backupmechanismus.
- Das vorhandene SMB-Share wird ausschließlich durch dedizierte Backup-Jobs eingebunden; Web- und Worker-Pods bleiben davon unabhängig.
