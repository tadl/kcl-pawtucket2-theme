# Development and verification

Read [AGENTS.md](../AGENTS.md), [README.md](../README.md) and
[DEPENDENCIES.md](../DEPENDENCIES.md) before changing KCL. Work from this
repository, preserve unrelated changes and use synthetic fixtures. Keep configured
applications, credentials, catalogue exports, media and generated previews outside
Git.

## Local requirements

- PHP 8.3 CLI with DOM/XML, JSON, mbstring, PDO SQLite, fileinfo and zlib;
  `proc_open` must be available. Check that `php` selects the intended interpreter.
- Node.js; CI uses Node 24. Some PHP suites execute JavaScript through Node.
- Bash; deployment checks use fake transfer tools and work with macOS Bash 3.2.
- A stock Pawtucket 2.0.10 reference checkout. It supplies native source and
  application assets for checks; it does not need an installed database or
  configured `setup.php`. Pass its absolute path explicitly.

There is no theme-specific Composer installation, npm installation or asset build.
CI pins the reference revision in
[checks.yml](../.github/workflows/checks.yml). Review that revision and the native
controller/viewer contracts when changing the supported application version.

## Checks

From `/path/to/kcl`, start with the suites related to the change:

| Area | Focused checks |
| --- | --- |
| Configuration and date searches | `php support/check_config.php /path/to/pawtucket2`; `php tests/decade_links_test.php` |
| Hierarchy boundary, browse and permissions | `php tests/catalogue_scope_test.php`; `php tests/catalogue_browse_scope_test.php`; `php tests/record_access_test.php` |
| CSS, scripts, fonts and rendered structure | `php tests/assets_test.php /path/to/pawtucket2`; `php tests/semantic_html_test.php` |
| Document preview, dialog and keyboard bridge | `php tests/media_display_test.php /path/to/pawtucket2`; `php tests/media_panel_test.php /path/to/pawtucket2` |
| Downloads and media guard | `php tests/image_download_test.php`; `php tests/media_allowlist_test.php` |
| Cache, errors and deployment helper | `php tests/result_cache_test.php`; `php tests/error_view_test.php`; `php tests/deploy_theme_test.php` |

Run the complete set before submitting a runtime change:

```sh
php support/check_config.php /path/to/pawtucket2
for test_file in tests/*_test.php; do
  php "$test_file" /path/to/pawtucket2 || exit 1
done
```

Match CI's syntax checks and review whitespace:

```sh
find controllers helpers plugins support tests views -name '*.php' -print0 |
  xargs -0 -n 1 php -l
for script in assets/pawtucket/js/*.js; do
  node --check "$script" || exit 1
done
bash -n support/deploy-theme.sh
git diff --check
```

The suites use synthetic native API adapters, in-memory SQLite and private
temporary files. They do not bootstrap a configured catalogue, contact a
production server or deploy files. The deployment suite substitutes its own
`rsync`, `ssh` and service commands. The operational
`support/generate_media_allowlist.php` is separate: it deliberately loads an
application and is not a local test command.

For dependency or packaging changes, run the isolation check directly:

```sh
php tests/standalone_test.php /path/to/pawtucket2
```

It copies shipped runtime/check directories into a private temporary theme with
no sibling custom theme, rejects symlinks, exercises the native configuration
parser and view resolver, and runs its selected regressions with `open_basedir`
blocking the original checkout. It also renders the static preview and removes
the fixture afterward. This proves those exercised paths are independent; it
does not replace real database, web-server, media-processing or browser checks.

Image-download tests use a synthetic PDF renderer by default. Their optional
`KCL_TEST_COMPOSER_AUTOLOAD` and `KCL_TEST_MAGICK` settings exercise an existing
local Dompdf installation and ImageMagick instead; they are not runtime
dependencies installed by this repository.

## Static layout preview

Choose a new or empty output directory outside the checkout:

```sh
php support/render_preview.php /path/to/pawtucket2 /path/to/empty-preview
php -S 127.0.0.1:8765 -t /path/to/empty-preview
```

Open the loopback server in a browser; stop it with Ctrl-C when finished. The
preview renders the actual home-page views and copies their local layout assets.
Search, browsing, catalogue content, cookies and the document reader require a
separately configured development application.

## Browser review

The Node-backed dialog tests use synthetic DOM controls; CI does not currently
run a full browser audit. For UI or viewer changes, check an authorized test
instance with representative synthetic/public fixtures:

- Search and decade links; populated/empty results; Tiles/List controls, paging,
  sort direction, browser Back and issue Previous/Next/result-return links.
- Desktop and 320-pixel layouts, 200% text enlargement and WCAG text-spacing
  overrides. Check long labels and footer addresses for overflow or clipping.
- Keyboard-only navigation, skip link, visible thumbnail/control focus, accessible
  labels and forced-colors mode.
- Document preview, zoom and downloads; modal scroll lock, focus containment,
  collapsed sidebars, toolbar Enter/Space, nested dialogs and Escape. Closing the
  reader must restore focus to its opener.
- Missing/private records and media. Confirm guards deny access without exposing
  catalogue details; never relax a boundary to make a fixture pass.

Use automated accessibility tools as an additional check and remove temporary
audit styles/emulation afterward. See [ACCESSIBILITY.md](../ACCESSIBILITY.md):
passing tests does not certify archived PDF tagging, OCR accuracy/reading order
or assistive-technology behavior. Screen-reader review remains separate.

## Maintaining the owned runtime

All custom controllers, helpers, plugins, views, configuration and assets belong
to KCL. Keep them as local regular files with `inheritFrom = 0` and configuration
inheritance disabled. Historical `tadl*` names preserve internal contracts;
renaming them is not necessary for independence.

[BUNDLED_RUNTIME.md](../BUNDLED_RUNTIME.md) records provenance and licenses.
Review and explicitly port any useful upstream/TADL changes, preserving source
notices and updating focused regressions. Keep native access checks, the KCL
hierarchy boundary, media guards and viewer callbacks intact. Update the relevant
guides when a documented contract or check changes. Commit coherent verified
source separately from any operator-authorized deployment.
