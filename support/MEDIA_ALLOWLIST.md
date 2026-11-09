# Public static media map

Run `generate_media_allowlist.php` against the KCL Pawtucket installation as its
maintenance user. It issues read-only catalogue/media queries and writes only
the private map, its lock and a temporary file in the map directory. It does
not scan the shared media tree, change records, process media or clear caches.

```sh
php themes/kcl/support/generate_media_allowlist.php \
  --app-root=/var/www/kcl \
  --output=/var/lib/kcl-pawtucket/media-allowlist.map \
  --url-prefix=media/ca_kcl
```

Create the output directory outside the web root, writable by that user and
traversable by Apache. The generator publishes mode0640 files owned by its
runner and current group, so use Apache's readable group. A nonblocking lock
prevents overlapping cron runs. Use `--quiet` for the once-per-minute scheduled
refresh during import; successful quiet runs print nothing. The generator uses
the helpers owned by this KCL checkout; no sibling theme is needed.

The root idno must be `kcl` and must resolve unambiguously. Public means access1
on both the nondeleted object and representation, within that institution's
actual collection hierarchy. Native bundle, type/source and configured
Pawtucket ACL checks are then applied for an anonymous audience. Workflow
status is not an additional publication criterion. A representation shared
with an eligible public KCL object qualifies through that public ownership.

All native stored representation renditions and attached multifile page/frame
renditions are enumerated. Declared multipage versions include each exact page
filename. Queued, icon-only, external and skipped versions are omitted. Unsafe
or unexpected URL namespaces abort publication. Keys are relative paths such
as `media/ca_kcl/original/1/ABC_issue.pdf`, with fixed value `1`; query strings do
not enter the map. No directory or basename wildcard is authorized.

Configure Apache's server/vhost `RewriteMap` as a text map and reject every
request below `/media/ca_kcl/` whose relative path is absent. Keep normal static
delivery for matched paths so PDFs retain range/conditional request support.
Do not let another Alias, alternate media namespace or rewrite bypass this
check. Encoded or unusual path spellings are intentionally absent from the
allowlist; use the exact native URLs. The generator uses same-directory atomic
rename, so Apache sees a complete map. Generation errors attempt an atomic
empty map and exit nonzero; monitor failures. Publication changes become
effective with the next successful refresh, so the map has the scheduled
refresh interval's revocation delay.

Stock Tilepic uses a `.tpc` container plus the separate
`viewers/apps/tilepic.php?p=...` extractor, rather than static tile subpaths.
The map includes an eligible container's exact path. Deny that PHP extractor
unless a separate request guard checks its `p` parameter against the same
allowlist. Do not assume the static-media rewrite protects PHP file readers.
KCL's PDF.js viewer normally uses the original PDF through static delivery.

Offline checks (no application setup or production connection):

```sh
php tests/media_allowlist_test.php
php -l support/generate_media_allowlist.php
php -l support/media_allowlist.php
```
