# KCL newspaper archive theme

A small Pawtucket 2.0.10 theme for Kalkaska County Library's single newspaper
collection, covering 1879–2012. It lives beside `tadl/` and `ncf/` and reuses
TADL's tested collection boundary and result/media runtime. Install the complete
sibling TADL theme as described in [DEPENDENCIES.md](DEPENDENCIES.md).

The landing page includes a prominent newspaper search, a browse-all link,
15 decade links, and the supplied welcome, OCR and use text. Issue details use
TADL's page preview with click-to-open document viewer, downloads and result navigation. The public
navigation has no multi-collection directory, gallery or account menu.

## Design and content

Branding follows [KCL's website](https://www.kalkaskalibrary.org/): white space,
navy `#243f5d`, green `#267a38`, Lato, and orange-accented search. The default
theme already supplies the Lato fonts; no external font service is requested.
The supplied archive wording is preserved, including its fair-use link.

- Page chrome: `views/pageFormat/`.
- Homepage and decade links: `views/Front/front_page_html.php`.
- OCR and use text: `views/About/archive_text_html.php`.
- Issue details: `views/Details/ca_objects_default_html.php`.
- Colors and responsive layout: `assets/pawtucket/css/theme.css`.
- Issue date mapping: `conf/global.conf`.

The logo and favicon are local copies of KCL's published assets, retrieved
November 9, 2026:

- [Horizontal logo](https://www.kalkaskalibrary.org/wp-content/uploads/2024/07/KCL-Horizontal-Blue-Green-No-Tagline-2-300x77.png).
- [Library mark](https://www.kalkaskalibrary.org/wp-content/uploads/2024/07/cropped-KCL-Blue-Green-Mark-1-32x32.png).

Library branding remains its owner's property. Theme code follows
CollectiveAccess's GPLv3 license; native media-panel setup and the issue template
are adapted from the default theme, copyright Whirl-i-Gig. See `LICENSE`.

## Runtime and deployment

The initial instance was provisioned November 9, 2026 at
[history.kalkaskalibrary.org](https://history.kalkaskalibrary.org/), with a
separate Pawtucket 2.0.10 installation. The newspaper import is ongoing;
available records and indexed text grow as it finishes. The planned upgrade to
2.0.12 is separate work; recheck the inheritance workaround during that upgrade.

- `tadl_catalogue_root_idno = kcl` restricts results, record details and native
  media routes to the actual KCL hierarchy. Missing or ambiguous roots deny
  material. Native publication, bundle, type/source and ACL checks still apply.
- The date mapping is `date.dates_value`, a text attribute containing ISO issue
  dates. Decade links use native field-bound prefix queries, such as
  `ca_objects.date.dates_value:187*`, and chronological sorting. They do not use
  native `normalizedDates`, which requires date-typed storage.
- Controllers/helpers/plugins and selected assets link to the sibling TADL
  source. Keep relative links intact. Required application flags are explicit:
  native configuration inheritance is disabled because 2.0.10 would let parent
  settings overwrite KCL's root. View inheritance remains enabled independently.
- The physical `views/system/fatal_error_html.php` supplies KCL recovery pages
  and correct missing/private-record statuses. Native fatal-error lookup does
  not use ordinary view inheritance.
- Public search history uses sessions rather than database search logging.
  Result caching is disabled while import is running. Comments, sharing,
  lightboxes, registration and record-summary PDF export are disabled.
- Direct static media needs the exact-path Apache allowlist described in
  [MEDIA_ALLOWLIST.md](support/MEDIA_ALLOWLIST.md). The installed read-only
  generator refreshes it each minute; PDF byte-range delivery remains native.
  Do not expose the shared media tree through an unguarded alternate alias or
  enable the unguarded Tilepic PHP extractor.

In the enclosing workspace, `./deploy kcl` installs the TADL runtime dependency
into the KCL instance, then this theme. `./deploy tadl` updates only the TADL
instance; no argument updates both. `./deploy --dry-run kcl` previews transfers.
Routine theme deployment preserves application caches, sessions and services.
The workstation wrapper delegates to TADL's tracked `support/deploy-themes.sh`.

After import completes, verify full archive coverage and finish the legacy
Omeka URL redirects using the completed crosswalk. Do not guess object matches
from duplicate identifiers. The theme uses native OCR indexing and does not
perform OCR or change catalogue records itself.

## Local checks and layout preview

Use PHP 8+ and a local Pawtucket 2.0.10 source tree. The default source path is
the workspace's sibling `pawtucket2/`; pass another path when working elsewhere.
The checks below never load application `setup.php` or a database. The separate
production media-map generator deliberately loads the configured application.

```sh
php support/check_config.php /path/to/pawtucket2
php tests/decade_links_test.php
php tests/assets_test.php
php tests/media_display_test.php
php tests/error_view_test.php
php tests/media_allowlist_test.php
find views support -name '*.php' -exec php -l {} \;
```

For a static preview of the actual homepage views, choose a new or empty output
directory, then serve that directory locally:

```sh
php support/render_preview.php /path/to/pawtucket2 /tmp/kcl-preview
php -S 127.0.0.1:8765 -t /tmp/kcl-preview
```

The preview copies only layout assets and uses synthetic framework adapters.
It checks appearance without a live catalogue; search, browse, cookies and the
document viewer require Pawtucket. Keep preview output outside the theme.
