# Installation and operations

These instructions maintain the theme in an existing CollectiveAccess deployment.
They do not import newspapers, install Providence, create catalogue records,
provision DNS/TLS/Anubis, or upgrade the application. Keep environment-specific
configuration and rollback evidence outside this public repository.

## Prepare an instance

Use a working Pawtucket 2.0.10 application with its required libraries, PHP 8.3
extensions, database connection and media configuration. Verify the installed
Providence/Pawtucket versions rather than assuming a theme checkout supplies them.
See [DEPENDENCIES.md](../DEPENDENCIES.md).

1. Install this repository as `themes/kcl/`, with regular local files. Do not
   install a TADL theme, add sibling symlinks or enable custom theme inheritance.
2. In the application's private `setup.php`, select KCL for all devices:

   ```php
   $_CA_THEMES_BY_DEVICE = ['_default_' => 'kcl'];
   ```

   Retire any device-specific selections of other themes if they are not intended.
   Give this application its own `__CA_APP_NAME__` and writable cache/session/log
   locations. Configure the correct public URL and media namespace in the native
   instance settings. Keep `setup.php` private and out of this repository.
3. Confirm that the intended catalogue has a unique top-level collection with
   identifier `kcl`, and issues belong to that hierarchy. Both objects and their
   representations need native readable/public access. Do not disable access
   checks to make missing records appear. Keep `tadl_catalogue_root_idno = kcl`:
   clearing/removing that setting selects the generic runtime's unscoped mode.
4. Verify the text issue-date field matches `conf/global.conf`, its values use ISO
   year prefixes, and native search/OCR indexing is available. The theme does not
   create OCR, index records or generate first-page derivatives.
5. Configure isolated native Redis storage as described below. Keep browse/result
   reuse disabled while import changes membership and access.
6. Provision the [exact-path static media guard](../support/MEDIA_ALLOWLIST.md)
   and its scheduled refresh before exposing shared media. Preserve PDF range
   responses. Deny private application/configuration paths, alternate unguarded
   media aliases and the unguarded Tilepic PHP extractor.
7. If the instance uses a reverse proxy and Anubis, configure them separately.
   Check forwarded public scheme/host, TLS, normal browser access and operator
   approved network bypasses. Keep challenge keys, policies and address inventories
   in private operational records.

## Redis and session isolation

Update the existing definitions in private `setup.php`; do not append conflicting
definitions after Pawtucket has initialized. An illustrative local Redis setup is:

```php
define('__CA_CACHE_BACKEND__', 'redis');
define('__CA_REDIS_HOST__', '127.0.0.1');
define('__CA_REDIS_PORT__', 6379);
define('__CA_REDIS_DB__', 7); // Example only: reserve an unused database for this instance.
```

Use the deployment's supported PHP Redis extension and native credentials/network
configuration. Database `7` is an example, not a production assignment. Audit
existing consumers before choosing it. Pawtucket 2.0.10's Redis driver does not
namespace keys by application name; a distinct app name alone is insufficient.
Keep the Redis database and application/session namespace separate from every
other instance.

Verify the effective backend/database through a native application bootstrap as
the web user, without printing secrets. A synthetic cache write/read/delete may
check the native adapter; remove only its exact test key. Do not use `FLUSHDB` or
`FLUSHALL` for theme deployment or to troubleshoot stale HTML/PHP.

`conf/browse.conf` still sets `cache_timeout = 0`, and `conf/app.conf` sets
`disable_tadl_result_cache = 1`. These control result reuse independently of
native Redis storage. Enabling reuse after import requires checking freshness,
access revocations and the cache identity/invalidation behavior.

## Deploy a theme update

Run the relevant [development checks](DEVELOPMENT.md) and review the source diff
before transferring files. A source push and a production deployment are separate
actions. Keep a private backup of the current target, including any local overrides.

From the KCL checkout:

```sh
bash support/deploy-theme.sh --dry-run user@catalog.example.org
bash support/deploy-theme.sh user@catalog.example.org
```

The second command writes to the server; run it only for an authorized deployment.
The helper accepts one SSH host, targets `/var/www/kcl/themes/kcl/`, and uses
`rsync --archive --verbose --itemize-changes`. It excludes `.git/`, `.gitignore`,
`README.md` and `.DS_Store`. It does not delete obsolete target files, transfer
another theme, provision the application, restart services or purge caches.
Tracked files can overwrite same-path local overrides; inspect the dry run.
Review obsolete files explicitly when a change removes or renames runtime code.

If the enclosing workspace has its optional dispatcher, `./deploy kcl` selects
this helper; `./deploy tadl` selects TADL, and `./deploy` selects both independent
themes. The dispatcher is not required to deploy KCL from this repository.

After transfer, verify effective theme configuration and source checksums, then
check the public site in a browser:

- Home search, browse-all and a decade query return KCL material.
- A populated Images/List result page retains sorting, pagination and Back links.
- An issue has a preview, right-side information/actions and no imported filename
  caption; narrow screens stack the layout.
- The PDF opens, renders, advances pages, closes and returns focus to its opener;
  the document scrolls while the background page is locked. OCR opens when present.
- Missing/outside/private records remain inaccessible. Direct unknown/outside
  media stays denied, while an allowed PDF byte-range request returns `206`.
- Theme assets resolve from `/themes/kcl/`; no `/themes/tadl/` requests, theme
  symlinks or custom inherited paths are required. Review new application errors.

Use known synthetic fixtures in development. Keep production IDs, responses,
screenshots, logs and checksum inventories in private evidence rather than Git.

## Replacing an older symlinked installation

The original KCL installation linked runtime files to a sibling TADL theme. The
current repository does not. For that structural migration, stage a complete
regular-file theme, inspect local overrides and activate it with a reversible
directory switch. Do not assume a routine overlay transfer removes old symlinks,
obsolete controllers or the former parent directory.

PHP workers can retain compiled paths to old symlink targets. A fresh CLI check
can pass while web requests fail with missing helper paths or same-file duplicate
function/class declarations. Check the web SAPI and actual include origins.
On Apache mod_php, instance-scoped `opcache.revalidate_path` can help path changes;
the corresponding configuration mechanism differs for PHP-FPM. If needed, have
the operator invalidate only the affected application's cached scripts in the
web SAPI, including callers with old include bindings, then recheck fresh requests.
A CLI OPcache operation does not establish that web-worker state was refreshed.
Do not leave a public maintenance endpoint or a permanent core patch behind.

Before retiring the former dependency, verify zero symlinks and parent includes,
local configuration/controllers/views, and public browse/detail/PDF behavior with
the old directory absent. Keep the old theme and parent together in private rollback
storage until the migration is accepted.

## Troubleshooting

| Symptom | Check first |
| --- | --- |
| No issues, or every issue is missing | Effective `kcl` root, hierarchy relationships, native object/representation access and any type/source/ACL restrictions |
| Decade links empty or dates misordered | Shared text-date mapping, ISO values and native index; do not substitute a normalized date facet for text storage |
| Browse slows down | Active local `TadlObjectBrowse` and its early scope filter; compare the same page/sort/size; confirm Redis independently rather than increasing TTLs |
| Missing first-page preview or inline PDF on detail | Local `media_display.conf`, TileViewer fallback and ready `mediumlarge` derivative |
| PDF controls appear but document does not render | Original PDF request/status, publication map refresh, native access, Anubis/proxy response and application PDF.js assets |
| PDF range request denied | Exact native path/namespace and refreshed map; keep the guard intact while investigating |
| Missing old TADL paths or duplicate declarations | Residual symlinks, wrong deployed tree and web-worker OPcache bindings after a structural migration |
| Wrong styles, fonts or viewer controls | KCL asset URLs/content hashes, local font files, loader order and browser network errors |
| Next/Previous differs from current results | Native session and result context, requested sort/page and catalogue changes during import |

## Rollback and application upgrades

Restore the reviewed previous theme and its matching configuration as a coherent
set, then repeat public access/media checks. If restoring the old symlinked theme,
restore its parent first; a symlinked rollback without its targets is incomplete.
Preserve current files and catalogue/session/media state. Address PHP path bindings
in the web SAPI if necessary; do not globally reset caches or alter access controls
as a substitute for restoring consistent source.

The repository baseline is 2.0.10; 2.0.12 is planned separately. Before upgrading,
compare native controller and view APIs, configuration resolution, result contexts,
Redis key behavior and the PDF.js/TileViewer keyboard bridge. Run the offline suite
against the candidate application, then repeat browser/access/static-media checks
in staging. The CI reference remains pinned until compatibility is verified.
