# ED Trade Routes — Spezifikation

## Problem Statement

Spieler von Elite Dangerous benötigen eine fortlaufend aktuelle Handelsroute, die von ihrem gegenwärtigen System aus den höchsten erwarteten Stundenprofit liefert. Marktinformationen ändern sich laufend und stammen aus unterschiedlichen Quellen. Außerdem muss die Empfehlung die Schiffsfähigkeit, Landeklassen, Sprunggrenzen, Frachtkapazität, Datenqualität und den tatsächlichen Fortschritt des Spielers berücksichtigen.

## Solution

Eine mehrbenutzerfähige Symfony-Webanwendung empfängt EDDN-Marktbeobachtungen sowie EDMC-Synchronisierungsereignisse. Ein EDMC-Plugin übermittelt Spielerkontext, Heartbeats und Spielereignisse über eine ausgehende HTTPS-REST-API. Die Anwendung berechnet galaxieweit mögliche, mehrgliedrige Handelsrouten mit Mehrfachladung und aktiviert automatisch die beste Route nach Credits pro Stunde. Das aktuelle Leg wird prominent angezeigt; die nächsten drei Stopps erscheinen als Vorschau. Ein bereits durch einen Kauf gebundenes Leg bleibt unveränderlich.

## User Stories

1. As an Elite Dangerous player, I want to sign in through the existing Authentik provider, so that I do not need a separate application password.
2. As an authenticated user, I want my Spielerkontext isolated from other users, so that personal route state is private.
3. As a user, I want to create, name, reveal, mask, and revoke EDMC synchronization keys, so that I can manage plugin installations safely.
4. As a user, I want to see the last activity of each synchronization key, so that I can detect stale or disconnected plugin instances.
5. As a user, I want to install an EDMC plugin that communicates only through outbound HTTPS, so that my computer needs no inbound port.
6. As a user, I want the plugin to synchronize my current system, ship, landing capability, jump range, cargo capacity, and relevant game events, so that route planning reflects my ship.
7. As a user, I want the plugin to send a heartbeat every 60 seconds, so that the application knows whether automatic features are trustworthy.
8. As a user, I want the application to switch to manual mode after approximately three missed heartbeats, so that stale automation cannot silently alter my plan.
9. As a user, I want manual route planning to remain available while the plugin is inactive, so that I can still use existing market data.
10. As a user, I want the plugin to queue events while offline and replay them chronologically, so that temporary network outages do not lose game progress.
11. As a user, I want duplicate or replayed plugin events to be harmless, so that retries cannot duplicate cargo or progress changes.
12. As a user, I want EDMC market observations to contribute to the shared market dataset by default, so that all users benefit from fresh observations.
13. As a user, I want shared observations to contain system, station, commodity, value, quantity, source, and observation time without exposing my Commander identity or personal movement history.
14. As a user, I want the newest observation to win when EDDN and EDMC disagree, so that the route uses the freshest known market value.
15. As a user, I want old raw EDDN messages removed after at most 72 hours, so that storage does not grow without bound.
16. As a user, I want normalized market history retained for 30 days and current values retained durably, so that recent trends remain useful without unlimited storage growth.
17. As a user, I want to choose a landing-class filter of small, medium, large, or “egal”, so that routes fit my ship and preferences.
18. As a user, I want to filter every station type independently, including orbital stations, outposts, planetary ports, Fleet Carriers, Megaships, and special markets, so that I can avoid inconvenient destinations.
19. As a user, I want to allow or exclude illegal commodities, so that default routes are safe while experienced players can opt into riskier trades.
20. As a user, I want to configure the maximum distance per hyperspace jump, so that the route respects my preferred jump limit.
21. As a user, I want the effective jump limit to respect both my configured filter and the ship’s loaded-range capability, so that every suggested jump is flyable.
22. As a user, I want to configure maximum total hyperspace jumps and maximum trade stops independently, so that I control route length precisely.
23. As a user, I want an open route or an optional return to the start system, so that I can choose between exploration and a closed trading circuit.
24. As a user, I want repeated systems and stations to be allowed, so that profitable revisits are not discarded automatically.
25. As a user, I want route calculation to start with an empty ship, so that unknown cargo from before planning does not distort the recommendation.
26. As a user, I want a route to carry multiple commodities at once, so that available cargo capacity can be allocated for the highest expected hourly profit.
27. As a user, I want supply, demand, and tradeable quantity to constrain recommendations, so that a high theoretical margin is not presented as a reliable trade without an executable market.
28. As a user, I want only markets within the configured data-age limit to be used, so that stale prices do not produce misleading recommendations.
29. As a user, I want the default data-age limit to be two hours and configurable, so that I can trade off freshness against route availability.
30. As a user, I want routes ranked by expected net credits per hour, so that travel time and cargo capacity matter alongside price margin.
31. As a user, I want fuel cost excluded from the first-version ranking, so that the agreed metric remains simple and transparent.
32. As a user, I want all known accessible systems considered galaxy-wide, so that the best route is not limited to a local bubble.
33. As a user, I want permit-locked or otherwise inaccessible systems excluded by default, so that recommendations are actually reachable.
34. As a user, I want configurable average times per jump and station type used in the estimate, so that hourly profit has an explicit and adjustable basis.
35. As a user, I want the best calculated route activated automatically, so that I receive actionable guidance without an extra confirmation.
36. As a user, I want to select an alternative route, so that I can trade off profit against convenience.
37. As a user, I want an alternative route to replace the active route immediately when no cargo is bound, so that my explicit choice takes effect.
38. As a user, I want a selected alternative deferred when cargo is bound, so that an already purchased load is never abandoned by automation.
39. As a user, I want the active Leg shown prominently, so that I immediately know where to fly and what to buy or sell.
40. As a user, I want the next three stops shown in a smaller preview, so that I can plan ahead without losing focus on the current Leg.
41. As a user, I want a Leg bound immediately when a purchase event is confirmed, so that later market updates cannot change the cargo commitment.
42. As a user, I want a bound Leg to remain unchanged until sale or confirmed completion, so that my live instructions match my cargo.
43. As a user, I want a Leg completed after docking and, when available, a market visit or sale event, so that arrival alone is not mistaken for a completed trade.
44. As a user, I want uncertain cargo state to fail safe and require confirmation, so that missed events cannot cause unsafe automatic replanning.
45. As a user, I want the existing route to remain visible while a recalculation runs, so that temporary processing does not leave the dashboard empty.
46. As a user, I want relevant EDDN updates, player-context changes, and filter changes to trigger debounced recalculation, so that recommendations stay current without excessive compute churn.
47. As an operator, I want weekly updates of the complementary system and station catalog, so that the galaxy index remains current without daily full-volume imports.
48. As an operator, I want PostgreSQL and Redis deployed inside Kubernetes with persistent storage, so that the service fits the existing cluster.
49. As an operator, I want PostgreSQL backups written by a dedicated job to the SMB share, so that application pods do not depend on a permanently mounted backup path.
50. As an operator, I want backup failures retried with backoff and alerted, so that an unavailable SMB share cannot silently erase backup coverage.

## Implementation Decisions

- Build a multi-user Symfony application with Authentik as the OpenID Connect identity provider. Every successfully authenticated Authentik user may access the application; first login provisions the local user record.
- Keep personal Spielerkontext, raw EDMC events, route preferences, active route state, synchronization keys, and cargo/progress state user-scoped. EDDN data and normalized EDMC market observations form a shared market dataset.
- Store synchronization keys encrypted at rest so they can be revealed later after re-authentication. Mask them by default, audit reveals, and support individual revocation.
- Restrict synchronization keys to heartbeat submission, event/market submission, and the owner’s plugin-status endpoint. They cannot read global market data or change application settings.
- Use an outbound HTTPS REST API for the EDMC plugin. Events have stable identifiers, source timestamps, sequence information, and idempotent server handling.
- Use a bounded local offline queue in the plugin. Replay events chronologically; detect gaps or contradictory sequences and mark cargo state uncertain.
- Receive EDDN asynchronously and process it through background workers. Keep raw messages only briefly, retain normalized history for 30 days, and retain current market observations durably.
- Reconcile EDDN and EDMC by observation time: the newest observation is current regardless of source. Preserve source and timestamps for auditability and confidence display.
- Use a complementary, replaceable system/station catalog provider for coordinates, accessibility, station types, and landing classes; refresh the catalog weekly with an optional manual run.
- Implement a stateful route planner that supports open and closed multi-stop routes, repeated systems/stations, multiple commodities, partial quantities, cargo capacity, supply, demand, illegal-commodity policy, landing-class filters, station-type filters, jump limits, total jump limits, and total stop limits.
- Rank routes by expected net credits per hour. Net trading profit is revenue minus purchase cost; fuel costs are excluded from ranking in the first version. Time is estimated using configurable averages per jump and station type.
- Exclude inaccessible systems and trades lacking reliable supply or demand from standard recommendations. Illegal commodities are excluded by default but filterable.
- Automatically activate the highest-ranked route after calculation. Keep alternatives selectable; do not replace a cargo-bound active Leg, and defer a selected alternative until that Leg completes.
- Treat the purchase event as the binding point for the active Leg. Complete the Leg only after docking plus market visit or sale when available; provide manual confirmation when required events are unavailable.
- Switch between Plugin-Aktivmodus and Manueller Modus using the 60-second heartbeat and approximately three-minute inactivity threshold. In manual mode, stop automated synchronization/progress transitions but keep manual route planning available.
- Preserve the last valid route during asynchronous recalculation and replace it atomically after successful computation.
- Deploy web, worker, EDDN consumer, route-calculation workers, catalog job, and cleanup/backup jobs as separate Kubernetes workloads. Run PostgreSQL and Redis in-cluster on the existing StorageClass. Mount the SMB share only for dedicated backup jobs; backup failure must not stop the application.

### Testing seam

Use one highest-level seam: the authenticated application boundary, combining HTTP API requests, queued domain events, persisted state, and rendered route/status responses. Exercise the same seam with deterministic clock and market/catalog fixtures. Add contract tests for the EDMC REST API and focused property/invariant tests for route-state transitions where the public boundary alone cannot efficiently cover combinatorial route planning.

## Testing Decisions

- Tests verify externally observable behavior: route result, active-Leg immutability, mode transitions, data freshness, ownership, API responses, and rendered status—not private implementation details.
- Test the EDMC API contract for authentication, idempotency, event ordering, heartbeat expiry, offline replay, source timestamps, and uncertain cargo state.
- Test market reconciliation with newer/older EDDN and EDMC observations, equal timestamps, stale records, retention boundaries, and shared-vs-private data separation.
- Test route-planner invariants: every jump is within effective range, stop/jump limits hold, inaccessible systems are absent, cargo never exceeds capacity, supply/demand cap quantities, and bound Legs remain unchanged.
- Test multi-commodity allocation, repeated systems, open/closed routes, illegal-commodity filtering, landing-class and station-type filters, alternative-route deferral, and hours-profit ranking.
- Test asynchronous recalculation preserves the last valid route on failure and atomically exposes a successful replacement.
- Test Authentik/OIDC provisioning and user isolation at the application boundary.
- Test Kubernetes manifests, worker liveness, scheduled jobs, persistent volume configuration, SMB backup success/failure, retries, and alert signals in deployment-level checks.
- There is no existing application test prior art; establish the first test fixtures and contracts around the authenticated application boundary.

## Out of Scope

- Direct integration with the Elite Dangerous game client beyond the EDMC plugin.
- Using current pre-existing cargo as the initial planning inventory; calculations start with an empty ship.
- Fuel, repair, insurance, mission opportunity costs, or other non-trading costs in the first-version ranking.
- Local Commander movement history or personal event details being shared with other users.
- Local passwords, separate account registration, or a second identity provider.
- Admin group-based access control; every authenticated Authentik user may access the application.
- Daily full catalog imports; the initial catalog cadence is weekly.
- Guaranteed exact travel time; route duration is an explicit estimate.
- Automatic completion based solely on arrival in the target system.
- Replacing a cargo-bound active Leg through recalculation or alternative selection.
- A full native desktop client; the EDMC plugin is the local integration.

## Further Notes

- The workspace currently contains the domain glossary and ADRs but no Symfony source code or initialized Git repository.
- The external catalog provider remains replaceable and must be selected and verified against current availability during implementation.
- The proposed test seam is the authenticated application boundary with deterministic infrastructure adapters; it minimizes coupling to implementation details while covering the route lifecycle end to end.
