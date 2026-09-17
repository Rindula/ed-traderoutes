# Fail-safe bei unsicherem Frachtstatus

Wenn das EDMC-Plugin offline war oder eine Ereignislücke erkannt wird, gilt der Frachtstatus als unsicher. Ein gebundenes aktives Leg bleibt gesperrt, bis ein verlässlicher Status oder eine manuelle Bestätigung vorliegt; automatische Neuberechnung darf die Frachtbindung nicht umgehen.

## Konsequenzen

- Synchronisierte Events müssen Lücken oder widersprüchliche Sequenzen erkennbar machen.
- Die Benutzeroberfläche muss den unsicheren Zustand und die erforderliche Aktion anzeigen.
- Die Anwendung benötigt einen manuellen Bestätigungsweg.
