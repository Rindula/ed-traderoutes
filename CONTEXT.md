# Elite Dangerous Trade Routes

Dieses Projekt ermittelt profitable Handelsrouten aus Marktbeobachtungen und dem aktuellen Spielerkontext. Marktinformationen können aus EDDN und aus dem EDMC-Plugin stammen und müssen anhand ihrer Herkunft und Aktualität beurteilt werden.

## Spielerkontext

**Spielerkontext**:
Die für eine Routenempfehlung relevanten aktuellen Daten des Spielers: Position, Schiff, Landeflächen-Kompatibilität und Sprungreichweite.

**EDMC-Plugin**:
Die optionale lokale Erweiterung, die Spielerkontext sowie vom Spiel beobachtete Daten an die Anwendung synchronisiert.

**Benutzer**:
Eine authentifizierte Person mit eigenem Spielerkontext, eigenen Synchronisierungsdaten und persönlichen Routenpräferenzen.

**Persönlicher Synchronisierungsschlüssel**:
Ein verschlüsselt gespeicherter Zugangsschlüssel, mit dem das EDMC-Plugin ausschließlich Daten des zugehörigen Benutzers synchronisiert. Er wird standardmäßig maskiert und nur nach erneuter Authentifizierung vollständig angezeigt.

**Synchronisierungsereignis**:
Ein zeitlich geordnetes EDMC-Spielereignis, das der Anwendung zur Aktualisierung von Spielerkontext, Frachtzustand oder Routenfortschritt übermittelt wird.

**Authentik-Benutzer**:
Ein Benutzerkonto, dessen Identität durch den bestehenden Authentik-Identity-Provider mittels OpenID Connect bestätigt wird.

## Markt und Datenherkunft

**Marktbeobachtung**:
Ein zu einem bestimmten Zeitpunkt beobachteter Marktwert für eine Ware an einer Station, einschließlich seiner Datenherkunft.

**EDDN-Beobachtung**:
Eine Marktbeobachtung, die über das Elite Dangerous Data Network empfangen wurde.

**EDMC-Beobachtung**:
Eine Marktbeobachtung, die vom EDMC-Plugin direkt aus einer lokalen Spielbeobachtung synchronisiert wurde.

**Rohes persönliches Ereignis**:
Ein vom Plugin übermitteltes Spielereignis, das dem jeweiligen Benutzer gehört und nicht automatisch anderen Benutzern zugänglich ist.

**Gemeinschaftliche Marktbeobachtung**:
Eine aus EDMC- oder EDDN-Daten abgeleitete Marktbeobachtung, die ohne persönliche Ereignisdetails zur Verbesserung der Routen für alle Benutzer verwendet werden darf.

**Datenaktualität**:
Die zeitliche Nähe einer Marktbeobachtung zum Zeitpunkt, für den eine Route berechnet wird.

**Datenaltersgrenze**:
Die höchstens zulässige Zeit zwischen Marktbeobachtung und Routenberechnung. Der Standardwert beträgt zwei Stunden und ist konfigurierbar.

**Aktuelle Marktbeobachtung**:
Die für einen Marktwert maßgebliche Beobachtung; sie ist immer die zeitlich neueste bekannte Beobachtung, unabhängig davon, ob sie aus EDMC oder EDDN stammt.

## Routenplanung

**Handelsroute**:
Eine geordnete Folge von Handelsabschnitten zwischen Märkten, bei der jeder Abschnitt einen Flug und einen profitablen Warentransport umfasst. Eine Handelsroute kann offen enden oder zum Startsystem zurückführen.

**Handelsabschnitt**:
Ein einzelner Transport von einem Abflugmarkt zu einem Zielmarkt innerhalb einer Handelsroute.

**Aktuelles Leg**:
Der nächste noch nicht abgeschlossene Handelsabschnitt, den der Spieler derzeit ausführen soll. Er gilt nach bestätigtem Docking und, sofern verfügbar, nach Marktbesuch oder Verkauf als abgeschlossen.

**Leg-Abschluss**:
Die bestätigte Ausführung eines Handelsabschnitts; die stärksten Nachweise sind Docking sowie anschließend Marktbesuch oder Verkauf.

**Gebundenes Leg**:
Ein aktuelles Leg, das durch ein bestätigtes Kaufereignis Fracht bindet und deshalb bis zum Verkauf oder bestätigten Abschluss nicht automatisch ersetzt werden darf.

**Unsicherer Frachtstatus**:
Ein Zustand, in dem wegen fehlender oder lückenhafter EDMC-Ereignisse nicht zuverlässig bekannt ist, ob die geplante Fracht an Bord ist. In diesem Zustand bleibt ein gebundenes Leg gesperrt.

**Plugin-Aktivmodus**:
Der Betriebsmodus, in dem ein EDMC-Plugin regelmäßig Lebenszeichen und Spielereignisse liefert. Automatisch verwaltete Spielerdaten dürfen in diesem Modus nicht manuell überschrieben werden.

**Manueller Modus**:
Der Betriebsmodus bei inaktivem EDMC-Plugin. Automatische Synchronisierung und Fortschrittsfunktionen sind deaktiviert, während manuelle Routenplanung mit vorhandenen Daten möglich bleibt.

**Routenvorschau**:
Die kompakte Anzeige der voraussichtlich folgenden Handelsstopps; standardmäßig werden drei Stopps gezeigt.

**Offene Route**:
Eine Handelsroute, die nach ihrem letzten Zielmarkt endet und nicht zum Startsystem zurückführen muss.

**Geschlossene Route**:
Eine Handelsroute, deren letzter Abschnitt wieder im konfigurierten Startsystem endet.

**Routenlänge**:
Die beiden konfigurierten Grenzen einer Handelsroute: maximale Hyperspace-Sprünge insgesamt und maximale Handelsstopps.

**Maximale Sprunganzahl**:
Die höchstens erlaubte Summe aller Hyperspace-Sprünge vom Startsystem über alle Handelsabschnitte bis zum Routenende.

**Sprungreichweite**:
Die maximale Entfernung eines einzelnen Hyperspace-Sprungs unter Berücksichtigung von Schiff und aktueller Fracht.

**Sprungdistanzfilter**:
Eine optionale Nutzergrenze für die Entfernung jedes einzelnen Hyperspace-Sprungs.

**Maximale Handelsstopps**:
Die höchstens erlaubte Anzahl von Zielmärkten, an denen die Route einen Handelsabschnitt abschließt.

**Frachtzustand**:
Die zum jeweiligen Zeitpunkt der Routenplanung erwartete Ladung des Schiffes einschließlich Waren, Mengen und verfügbarer Restkapazität.

**Leerer Startzustand**:
Die Annahme, dass eine neue Routenberechnung ohne bereits geladene Waren beginnt; nur Schiffskapazität und Spielerkontext werden übernommen.

**Frachtplan**:
Die Abfolge von Einkäufen, Transporten und Verkäufen, die den Frachtzustand über die gesamte Handelsroute verändert.

**Mehrfachladung**:
Eine Fracht, die mehrere verschiedene Waren gleichzeitig enthält und deren Mengen die verfügbare Frachtraumkapazität gemeinsam belegen.

**Routenempfehlung**:
Die anhand von Profitabilität, Flugdistanz, Spielerkontext und Datenaktualität ausgewählte Handelsroute.

**Aktive Route**:
Die vom System automatisch aktivierte beste Route oder eine vom Benutzer ausgewählte alternative Route, deren aktuelles Leg verfolgt wird.

**Routenvorschlag**:
Eine berechnete, noch nicht ausgewählte Route, die als Alternative zur aktiven Route angezeigt wird.

**Stundenprofit**:
Der erwartete Netto-Handelsgewinn einer Route geteilt durch die geschätzte Gesamtdauer der Fahrt. Er ist die primäre Kennzahl für die Routenempfehlung.

**Netto-Handelsgewinn**:
Der erwartete Erlös aus dem Verkauf der Ladung abzüglich der Einkaufskosten; weitere Kosten wie Treibstoff werden nur berücksichtigt, wenn sie belastbar berechnet werden können.

**Belastbarer Trade**:
Ein Handelsvorschlag mit ausreichend bekanntem Angebot am Einkaufsmarkt, bekannter Nachfrage am Verkaufsmarkt und einer daraus ableitbaren handelbaren Menge.

**Erreichbarer Markt**:
Ein Markt, dessen System und Station für den Benutzer innerhalb der konfigurierten Fluggrenzen zugänglich sind und dessen Zugangsbedingungen erfüllt oder als zulässig bekannt sind.

**Landefläche**:
Die benötigte Stations-Landeklasse für das Schiff: klein, mittel oder groß; zusätzlich kann die Auswahl „egal“ verwendet werden.

**Landeklasse**:
Die kleinste Stations-Landefläche, die ein Handelsstopp für das Schiff bereitstellen muss.
