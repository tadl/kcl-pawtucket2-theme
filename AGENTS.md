# KCL Pawtucket theme

Read `README.md` and `DEPENDENCIES.md` before changes. This theme reuses the
sibling TADL runtime through deliberate relative links and view inheritance.

- Keep this focused on a single newspaper archive; preserve the supplied copy.
- Preserve native access checks and document-viewer initialization.
- Decade text searches, sorts and details share `conf/global.conf`'s date mapping.
- No instance or production actions are authorized by these files.
- Do not treat object-only menus or facets as a collection access boundary.
- Keep the configured `kcl` hierarchy boundary and guarded direct-media map.
- Do not enable configuration inheritance without a native runtime check;
  Pawtucket 2.0.10 otherwise overwrites child configuration with parent values.
- Keep credentials, catalogue data, logs and preview output outside Git.
- Run `php support/check_config.php` against the workspace Pawtucket source,
  lint changed PHP, and check responsive layout when changing the views or CSS.
- Work on `main`; commit coherent verified changes. Source commits and pushes
  remain separate from instance setup and deployment.
