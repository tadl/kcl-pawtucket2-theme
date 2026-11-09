# Application requirements

KCL is self-contained as a theme. There is no custom parent theme, symlink,
sibling checkout, submodule or external theme path to install. Its controllers,
helpers, plugins, views, configuration, CSS, scripts, fonts and adapted viewer
are owned regular files. Historical `tadl*` symbols are internal names; source
provenance is recorded in [BUNDLED_RUNTIME.md](BUNDLED_RUNTIME.md).

Install the repository as `themes/kcl/` in a working Pawtucket 2.0.10 application
connected to the intended Providence database and media. The verified web target
is PHP 8.3. Pawtucket still supplies its standard libraries and application assets,
including Bootstrap, jQuery, PDF.js, native media processing and optional Dompdf.
The theme has no separate Composer or npm runtime installation.

`conf/app.conf` sets `inheritFrom = 0`, `allowThemeInheritance = 0` and
`tadl_catalogue_root_idno = kcl`. The institution root must resolve to exactly one
nondeleted top-level collection. Its descendants supply the collection/object/
representation boundary, supplemented by native publication, bundle, type/source
and ACL checks. Missing, non-root or ambiguous configured roots deny material.
Never replace a missing boundary with unrestricted native templates.

The local `conf/media_display.conf` preserves the issue-page first-page preview
and full PDF.js dialog. Native viewers, downloads, callbacks and media identifiers
retain their original access checks. The physical fatal-error view supplies
missing/private 404 responses; native fatal-error lookup does not use ordinary
view resolution.

Issue dates are profile text (`date.dates_value`), so decade links use native
field-bound text search rather than normalized date facets. Fresh request-local
scope limits native wildcard browse before metadata sorting; the later result
and media guards remain. Keep native `cache_timeout = 0` to avoid stale results
or permissions. The separate revision-checked result cache has its own flag.

The theme boundary does not protect an unrestricted static alias to the shared
media tree. Provision the [exact-path media guard](support/MEDIA_ALLOWLIST.md),
keep private application files outside public routes and isolate sessions/cache
storage. For native Redis, give each application a distinct Redis database:
Pawtucket 2.0.10 keys omit the application name. Optional face-focus suggestions
use `conf/thumbnail_focus.conf`'s KCL-specific private directory; missing suggestions
fall back to the native image without a detector service.

Review native controllers, view APIs and the PDF keyboard/focus bridge when
upgrading the application. No core patch is required by this theme.
