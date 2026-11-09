# KCL newspaper archive theme

A small Pawtucket 2.0.10 theme for Kalkaska County Library's single newspaper
collection, covering 1879–2012. It lives beside `tadl/` and `ncf/` and falls back
to Pawtucket's stock `default` theme, independently of those sibling themes.

The landing page includes a prominent newspaper search, a browse-all link,
15 decade links, and the supplied welcome, OCR and use text. Issue details use
the native document viewer, downloads and result navigation. The public
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

## Instance setup still to do

This change creates the theme only. It does not configure a running instance,
connect to a database, change publication settings, index PDFs or deploy files.
For later provisioning:

1. Install this folder as `themes/kcl` alongside the stock `themes/default`,
   and select `kcl` using the instance's normal theme configuration.
2. Check the issue date field in `conf/global.conf`. It defaults to
   `date.dates_value`, matching the existing workspace profile. It must be a
   native date attribute for normalized decade browsing and chronological sorting.
3. Confirm that the public catalogue contains only the intended KCL newspapers.
   These object-only display settings do **not** isolate a collection in a
   shared database. If the new instance shares a larger catalogue, add and test
   a collection boundary across results, direct details and media before launch.
   No collection identifier or database ID has been guessed here.
4. Confirm that native search indexes the newspaper OCR/PDF text, that intended
   objects and representations are public, and that PDF derivatives/viewing work.
   The theme sends queries to native `Search/objects`; it does not implement OCR.
5. Check search, first/middle/last decade results, issue dates, paging, direct
   links, keyboard access, document zoom and downloads in the real instance.

Native record, representation, bundle, type/source and ACL checks remain in
place. Comments, sharing, lightboxes, registration and record-summary PDF export
are disabled in the theme's normal interface; original-media downloads retain
the native access-checked policy. This theme does not add route-level account or
collection access controls.

## Local checks and layout preview

Use PHP 8+ and a local Pawtucket 2.0.10 source tree. The default source path is
the workspace's sibling `pawtucket2/`; pass another path when working elsewhere.
These helpers never load application `setup.php` or a database.

```sh
php support/check_config.php /path/to/pawtucket2
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
