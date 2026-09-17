# Authentik als OIDC-Loginprovider

Die Anwendung verwendet die bestehende Authentik-Installation als zentralen Loginprovider über OpenID Connect. Benutzer werden beim ersten erfolgreichen Login automatisch angelegt; die Anwendung speichert keine lokalen Passwörter und verwaltet nur ihre eigenen benutzerbezogenen Daten und Synchronisierungsschlüssel.

## Konsequenzen

- Die Symfony-Anwendung benötigt eine stabile externe Benutzerkennung aus Authentik.
- Logout, Sperrung und Gruppen-/Rollenverwaltung hängen teilweise vom OIDC-Provider ab.
- OIDC-Clientdaten und Secrets müssen über die Laufzeitkonfiguration verwaltet werden.
