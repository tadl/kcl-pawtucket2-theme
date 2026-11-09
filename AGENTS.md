# KCL Pawtucket theme

Read `README.md` before changes. This is a standalone theme, independent of
`tadl/` and `ncf/`, with stock Pawtucket `default` fallbacks.

- Keep this focused on a single newspaper archive; preserve the supplied copy.
- Preserve native access checks and document-viewer initialization.
- Date facets, sorts and details share `conf/global.conf`'s issue date mapping.
- No instance or production actions are authorized by these files.
- Do not treat object-only menus or facets as a collection access boundary.
- Keep credentials, catalogue data, logs and preview output outside Git.
- Run `php support/check_config.php` against the workspace Pawtucket source,
  lint changed PHP, and check responsive layout when changing the views or CSS.
- Work on `main`; commit coherent verified changes. Source commits and pushes
  remain separate from instance setup and deployment.
