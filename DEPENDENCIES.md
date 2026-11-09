# Runtime theme dependency

KCL depends on the sibling `tadl` theme. Install its complete source under
`themes/tadl/` before installing KCL under `themes/kcl/`, and preserve symlinks.
The initial dependency was inspected at TADL commit
`280b7eaf25d3e7ea8ccdfbfbb798443124831bd2` on Pawtucket 2.0.10.

`conf/app.conf` sets `inheritFrom = tadl` and
`tadl_catalogue_root_idno = kcl`. The configured institution root must resolve
to exactly one nondeleted top-level collection. Its complete descendant
hierarchy supplies KCL's collection, object and representation boundary;
native publication, bundle and ACL checks still apply within it. Missing or
ambiguous configured roots deny catalogue results and direct record/media
requests.

Pawtucket 2.0.10 applies inherited configuration after child definitions, which
would replace KCL's root and browse definitions with TADL's. KCL therefore sets
`allowThemeInheritance = 0` to disable that configuration merge. The native
`View` resolver independently follows `inheritFrom`, so it still inherits the
guarded TADL result views. Required application flags are explicit in KCL;
assets are supplied explicitly instead of relying on native asset inheritance.
Do not enable the configuration merge without checking effective runtime root,
browse types and detail types with an actual application bootstrap.

`conf/media_display.conf` links directly to TADL's configuration. This preserves
its document preview on issue pages and PDF.js in the full media overlay. View
inheritance alone would fall back to the native application's inline PDF.js
settings, which are unsuitable for the normal detail-page preview.

Pawtucket does not inherit controllers, helpers or plugins. These relative
directory links deliberately reuse the same tested implementation:

- `controllers -> ../tadl/controllers`
- `helpers -> ../tadl/helpers`
- `plugins -> ../tadl/plugins`

Pawtucket preloads the active theme's helpers. PHP resolves `__DIR__` to the
linked source files, so their internal relative includes remain within TADL.
KCL supplies its own home page, page header/footer and newspaper issue view;
inherited result/facet views retain institution filtering and result navigation.
Keep the complete TADL dependency in place rather than replacing these views
with unfiltered upstream defaults.

Fatal-error lookup checks a physical active-theme file instead of the normal
view resolver. KCL therefore has its own `views/system/fatal_error_html.php`;
missing and outside records return a generic KCL 404, while unexpected failures
remain server errors. The known native `ApplicationException('Invalid id')` on
Detail requests is mapped specifically to 404.

The theme boundary protects catalogue and controller-mediated media routes.
It does not authorize publishing an unrestricted static alias to the shared
Providence media tree. Review media storage URLs and web-server access rules
when provisioning the instance; see `support/MEDIA_ALLOWLIST.md` for the installed
exact-path guard. Keep its session and cache namespaces separate
from other Pawtucket instances.

Issue dates are profile text (`date.dates_value`), not native date attributes.
Decade links must use the supported field-bound text search; a native
`normalizedDates` facet cannot derive decades from this text field.
