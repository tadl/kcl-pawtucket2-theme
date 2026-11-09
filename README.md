# KCL newspaper archive theme

A standalone Pawtucket 2.0.10 theme for Kalkaska County Library's single newspaper
collection, covering 1879–2012. Install this checkout as `themes/kcl/`; no TADL
theme, sibling checkout, symlink, submodule, Composer install or frontend build
is required. See [DEPENDENCIES.md](DEPENDENCIES.md) for application prerequisites.

The home page has newspaper search, browse-all and 15 decade links, plus the
supplied welcome, OCR and use text. Issue details put the preview on the left and
title, metadata, result navigation and native media actions on the right; narrow
screens stack the columns. Imported filename captions are omitted. Available OCR
has an access-checked HTML disclosure. The document dialog preserves native
initialization, locks background scrolling and restores focus when closed.

## Design and source

Branding follows [KCL's website](https://www.kalkaskalibrary.org/): white space,
navy `#243f5d`, green `#267a38`, Lato and a muted gray search border that darkens
on focus. All theme CSS, JavaScript, fonts and viewer adaptations are local files.
No external font service is requested. The supplied archive wording is preserved,
including its fair-use link. See [ACCESSIBILITY.md](ACCESSIBILITY.md) for the
review, checks and document-content limits.

- Page chrome: `views/pageFormat/`.
- Home page and decade links: `views/Front/front_page_html.php`.
- Archive text: `views/About/archive_text_html.php`.
- Issue details: `views/Details/ca_objects_default_html.php`.
- KCL styling: `assets/pawtucket/css/theme.css`; owned base UI: `css/base.css`.
- Scope, access, browse and media behavior: `controllers/`, `helpers/`, `plugins/`.
- Issue date mapping: `conf/global.conf`.

The runtime was adapted from the TADL theme and now belongs to this repository;
future TADL changes do not propagate into KCL. Historical `tadl*` function/class
names, CSS classes and configuration keys remain internal compatibility names.
They do not load another theme. [BUNDLED_RUNTIME.md](BUNDLED_RUNTIME.md) records
source provenance and licenses.

The logo and favicon are local copies of KCL's published assets, retrieved
November 9, 2026:

- [Horizontal logo](https://www.kalkaskalibrary.org/wp-content/uploads/2024/07/KCL-Horizontal-Blue-Green-No-Tagline-2-300x77.png).
- [Library mark](https://www.kalkaskalibrary.org/wp-content/uploads/2024/07/cropped-KCL-Blue-Green-Mark-1-32x32.png).

Library branding remains its owner's property. Theme code follows
CollectiveAccess's GPLv3 license; source notices are retained. Bundled Lato is
licensed under SIL OFL 1.1. See `LICENSE` and `assets/pawtucket/css/Lato/OFL.txt`.

## Runtime and deployment

The initial instance was provisioned November 9, 2026 at
[history.kalkaskalibrary.org](https://history.kalkaskalibrary.org/), with a
separate Pawtucket 2.0.10 installation. Archive coverage and indexed text grow
while import proceeds. The planned 2.0.12 upgrade is separate work; review the
native controller and viewer API adaptations during that upgrade.

- `tadl_catalogue_root_idno = kcl` bounds results, details and media to the actual
  KCL hierarchy. Missing or ambiguous roots deny material. Native publication,
  bundle, type/source and ACL checks still apply.
- Date mapping is text `date.dates_value`. Decade links use native field-bound
  ISO-prefix queries, such as `ca_objects.date.dates_value:187*`, and chronological
  sorting. Native `normalizedDates` requires date-typed storage and is not used.
- Custom runtime, views, configuration and theme assets are regular local files.
  `inheritFrom = 0` and `allowThemeInheritance = 0`; standard application defaults
  and libraries remain supplied by Pawtucket itself.
- Browse applies fresh hierarchy membership before native metadata sorting,
  retains native access/order/paging/context and checks scope again afterward.
- Public search history uses sessions. Native browse TTL and theme result reuse
  remain disabled during import. Comments, sharing, lightboxes, registration and
  record-summary PDF export are disabled.
- Native cache uses Redis in its own database: Pawtucket 2.0.10 Redis keys do not
  include the application name. Keep sessions and cache storage isolated.
- Direct static media needs the exact-path Apache allowlist described in
  [MEDIA_ALLOWLIST.md](support/MEDIA_ALLOWLIST.md). PDF byte-range delivery remains
  native. Never expose the shared media tree through an unguarded alias or enable
  the unguarded Tilepic PHP extractor.

From this checkout, deploy only KCL with:

```sh
bash support/deploy-theme.sh --dry-run user@catalog.example.org
bash support/deploy-theme.sh user@catalog.example.org
```

Replace the synthetic target with the operator's SSH host. The helper targets
`/var/www/kcl/themes/kcl`, preserves caches/sessions/services and does not delete
server files. Inspect server-local overrides before using it. Deploying requires
operator authorization; cloning or pushing source is not deployment approval.

The optional enclosing workspace wrapper supports `./deploy kcl`, `./deploy tadl`
and `./deploy` for both independent themes. KCL selection calls this repository's
own helper and works without a TADL checkout.

After import, verify full coverage and finish legacy Omeka redirects from the
completed crosswalk. Do not guess matches from duplicate identifiers. The theme
displays existing OCR and uses native search; it does not process catalogue data.

## Checks and layout preview

Use PHP 8.3 with DOM, mbstring, SQLite, fileinfo and `proc_open`, Node.js and a
stock Pawtucket 2.0.10 reference checkout. Tests are synthetic and do not load a
configured application or database. Pass the reference path explicitly:

```sh
php support/check_config.php /path/to/pawtucket2
for test_file in tests/*_test.php; do php "$test_file" /path/to/pawtucket2 || exit 1; done
```

`tests/standalone_test.php` copies KCL into a private temporary directory with no
sibling custom theme. It exercises the actual native view resolver, configuration,
assets, media/access regressions and static preview while blocking reads from the
original sibling themes. `tests/deploy_theme_test.php` uses synthetic transfer
tools; it never contacts a server. GitHub Actions checks KCL against stock
Pawtucket only.

For a static preview of the actual home-page views, choose a new or empty output
directory, then serve it locally:

```sh
php support/render_preview.php /path/to/pawtucket2 /tmp/kcl-preview
php -S 127.0.0.1:8765 -t /tmp/kcl-preview
```

Search, catalogue data, cookies and document viewing need a running application.
Keep generated previews and operational files outside this repository.
