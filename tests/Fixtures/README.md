# Frozen regression fixtures

These are the original small synthetic datasets, moved from `src/DataFixtures/`
so functional tests retain their exact names, slugs, cardinalities and edge cases.
Only their namespace changed. User fixtures are still shared with the application.

`config/services_test.yaml` exposes these classes as test-only services with
`autoconfigure: false`: they carry no `doctrine.fixture.orm` tag and cannot be
selected by `doctrine:fixtures:load`. Tests load them explicitly into the separate
test database. Keep this directory independent of the portal demo content;
changes here should be motivated by regression coverage, not presentation.

Demo data definitions and deterministic image generation have separate database-free
unit coverage in `DemoFixtureDataTest` and `FixtureArtworkTest`. Those checks never
invoke `load()`.
