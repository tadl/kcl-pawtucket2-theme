# KCL maintainer documentation

Start with the [theme README](../README.md) for its purpose, layout and quick
commands. These guides cover maintaining KCL independently of other themes.

| Guide | Contents |
| --- | --- |
| [Architecture and configuration](ARCHITECTURE.md) | Request flow, source map, collection boundary, dates, caching and viewer contracts |
| [Development and verification](DEVELOPMENT.md) | Local requirements, regression checks, static preview and browser checks |
| [Installation and operations](OPERATIONS.md) | Instance setup, Redis isolation, deployment, rollback, troubleshooting and upgrades |
| [Application requirements](../DEPENDENCIES.md) | Stock Pawtucket prerequisites and the standalone boundary |
| [Accessibility](../ACCESSIBILITY.md) | WCAG 2.2 AA interface review, evidence and content/testing limits |
| [Static media guard](../support/MEDIA_ALLOWLIST.md) | Exact-path publication map, scheduled refresh and direct-media protection |
| [Bundled runtime provenance](../BUNDLED_RUNTIME.md) | TADL source baseline, owned code and licenses |

The supported baseline is Pawtucket 2.0.10 and PHP 8.3. The planned application
upgrade needs its own compatibility review; these guides do not establish that
a newer version has been tested.

Keep credentials, host inventories, Anubis keys/policies, library IP allowlists,
catalogue exports, logs and deployment backups in private operational records.
Examples here are illustrative and contain no production connection settings.
