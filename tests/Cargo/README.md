# Ticket #6 cargo contract tests

`CargoStateTest.php` exercises the existing public `CargoManifest` seam:

- `new CargoManifest(int $capacity)`
- `capacity(): int`
- `usedCapacity(): int`
- `remainingCapacity(): int`
- `positions(): array`
- `buy(string $commodity, int $quantity, int $unitPrice, ?string $station): void`
- `sell(string $commodity, int $quantity, int $unitPrice): int`

The six scenarios cover empty starting cargo, multi-commodity allocation, the
capacity invariant, partial sale, repeated station visits, and rejection of an
over-capacity purchase. No production, migration, configuration, or controller
files are changed by this test-only slice.
