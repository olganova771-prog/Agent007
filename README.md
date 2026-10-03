# Site Factory 3.0

Autonomous deterministic WordPress site factory.

## Core workflow

Project → Site Plan → draft generation → QA → publish.

The plugin runs without a runtime AI dependency. Ukrainian is the primary language; an optional Russian layer can be generated when enabled. Generation and indexability are deliberately separated so large geo plans can be built without automatically indexing weak pages.

## Included

- Structured project wizard instead of the legacy product meta-box workflow.
- One fully populated demo product: **Aurora Mini**.
- Complete bundled Ukraine city dataset: **463 cities**.
- Product and Product+City planning.
- Full-coverage Product+City generation for all bundled cities when enabled.
- Separate geo indexability switch; weak geo pages remain noindex by default.
- UA primary + optional RU version with hreflang links.
- 30 configurable editorial traits.
- Five visual profiles: CREATOR, BUSINESSMAN, BANDIT, CARTEL, MASTER.
- Draft-first rendering.
- Leased/recoverable queue with retries.
- QA gate before publication.
- Canonical/meta/robots/Open Graph/JSON-LD/hreflang output.
- Internal links and optional WooCommerce draft-product synchronization.
- CI across PHP 8.0–8.3.
- Automatic installable ZIP artifact.

## Install

Upload the generated site-factory-3.0.0.zip in WordPress → Plugins → Add Plugin → Upload Plugin.

After activation, Site Factory creates a demo project with one complete demo product and a small geo sample. New projects default to full-city planning; geo pages are generated independently from their indexing decision.

## Production rule

Generated pages start as drafts. Publication is blocked by critical QA issues.
