# Mehrgliedrige offene und geschlossene Handelsrouten

Die Routenplanung unterstützt fortlaufende Handelsrouten mit mehreren Stationen und Systemen, statt auf einen einzelnen Zwei-Stationen-Zyklus beschränkt zu sein. Eine Route kann offen enden; optional kann sie innerhalb konfigurierbarer Grenzen zum Startsystem zurückkehren. Die Grenzen werden getrennt als maximale Hyperspace-Sprünge insgesamt und maximale Handelsstopps festgelegt.

## Konsequenzen

- Die Routenberechnung muss Zyklen, bereits besuchte Systeme und beide Längengrenzen behandeln.
- Die Rückkehr zum Startsystem ist eine Planungsbedingung und nicht automatisch Bestandteil jeder Route.
- Die Länge muss für Nutzer eindeutig als Sprünge, Handelsabschnitte oder beides konfigurierbar bzw. angezeigt werden.
