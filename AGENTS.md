# KCL Pawtucket theme

Read `README.md` and `DEPENDENCIES.md` before changes. KCL owns its runtime;
no sibling theme, symlink or custom view/config inheritance may be required.
Use `docs/README.md` to find architecture, development and operations guides.

- Keep this focused on a single newspaper archive; preserve the supplied copy.
- Preserve native access checks and document-viewer initialization.
- Decade text searches, sorts and details share `conf/global.conf`'s date mapping.
- No instance or production actions are authorized by these files.
- Do not treat object-only menus or facets as a collection access boundary.
- Keep the configured `kcl` hierarchy boundary and guarded direct-media map.
- Keep all custom runtime, views, assets and configuration local regular files.
  Keep `inheritFrom = 0` and configuration inheritance disabled.
- Keep credentials, catalogue data, logs and preview output outside Git.
- Run `php support/check_config.php` against the workspace Pawtucket source,
  lint changed PHP, and check responsive layout when changing the views or CSS.
- Run `php tests/standalone_test.php /path/to/pawtucket2` for dependency changes.
  Native application libraries are allowed; another custom theme is not.
- Work on `main`; commit coherent verified changes. Source commits and pushes
  remain separate from instance setup and deployment.
- Update the relevant maintainer guide when configuration, deployment or test
  contracts change. Keep private operational evidence outside this repository.
