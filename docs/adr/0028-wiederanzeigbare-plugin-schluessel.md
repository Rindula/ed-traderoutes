# Wiederanzeigbare Plugin-Schlüssel verschlüsselt speichern

Persönliche EDMC-Synchronisierungsschlüssel sollen später erneut angezeigt werden können. Deshalb werden sie verschlüsselt und nicht als unumkehrbare Hashes gespeichert; standardmäßig erscheinen sie maskiert, die vollständige Anzeige erfordert eine erneute Authentifizierung und wird protokolliert.

## Konsequenzen

- Der Entschlüsselungsschlüssel wird unabhängig von der Datenbank als Kubernetes-Secret verwaltet.
- Ein Datenbankleck darf die Plugin-Schlüssel nicht unmittelbar offenlegen.
- Schlüssel müssen einzeln widerrufbar und neu erzeugbar sein.
