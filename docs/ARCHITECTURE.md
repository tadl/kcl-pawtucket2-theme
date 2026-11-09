# Architecture and configuration

KCL presents one newspaper collection through Pawtucket 2.0.10. It owns its custom
runtime, templates, configuration and assets. Stock Pawtucket provides the
application framework, default configuration/views, Bootstrap, jQuery and PDF.js.
There is no custom parent theme or build step. See the
[application requirements](../DEPENDENCIES.md) and
[source provenance](../BUNDLED_RUNTIME.md).

## Request flow

1. Pawtucket selects `themes/kcl`, loads its configuration/helpers and resolves
   its controllers and views. `inheritFrom = 0` and `allowThemeInheritance = 0`
   prevent dependency on another custom theme.
2. The local scope helpers resolve the configured `kcl` collection root and
   restrict material to that hierarchy. Native publication and ACL checks still
   determine whether a visitor can read each record, bundle or representation.
3. Search and browse use native result contexts, sorting and paging. The scoped
   browse adapter limits object IDs before native metadata sorting, then the
   rendering and media paths check access again.
4. The issue view renders a first-page preview, metadata, result navigation and
   media actions. The media action requests the native PDF.js overlay; OCR uses
   a separate access-checked document-text path.
5. Direct static media requests go through the web server's exact-path publication
   map. Application controller checks alone cannot protect a static media alias.

## Source map

| Change | Owning source |
| --- | --- |
| Header, navigation, search on interior pages | [pageHeader.php](../views/pageFormat/pageHeader.php) |
| Footer and PDF dialog compatibility bridge | [pageFooter.php](../views/pageFormat/pageFooter.php) |
| Homepage search, welcome text and decades | [front_page_html.php](../views/Front/front_page_html.php) |
| Supplied archive/OCR/use text | [archive_text_html.php](../views/About/archive_text_html.php) |
| Issue preview, title, metadata and actions | [ca_objects_default_html.php](../views/Details/ca_objects_default_html.php) |
| Brand styles and owned base UI | [theme.css](../assets/pawtucket/css/theme.css), [base.css](../assets/pawtucket/css/base.css) |
| Asset ordering and content-hash URLs | [assets.conf](../conf/assets.conf), [asset_versions.php](../helpers/asset_versions.php) |
| Institution scope and native readability | [catalogue_scope.php](../helpers/catalogue_scope.php), [record_access.php](../helpers/record_access.php) |
| Browse before sorting | [BrowseController.php](../controllers/BrowseController.php), [catalogue_browse.php](../helpers/catalogue_browse.php) |
| Detail and native media requests/actions | [DetailController.php](../controllers/DetailController.php), [image_actions_script.php](../views/Details/image_actions_script.php) |
| OCR disclosure and endpoint | [document_text_disclosure.php](../helpers/document_text_disclosure.php), [DocumentTextController.php](../controllers/DocumentTextController.php) |
| Public missing/private record response | [public_errors.php](../helpers/public_errors.php), [error view](../views/system/fatal_error_html.php) |
| Direct-media map generation | [generate_media_allowlist.php](../support/generate_media_allowlist.php), [media_allowlist.php](../support/media_allowlist.php) |

The repository includes broader runtime code adapted from TADL, such as gallery,
lightbox and finding-aid support. Its presence does not enable those features in
KCL's menu. Keep existing access checks on directly requested routes. Historical
`tadl*` symbols are local compatibility names; renaming them is not necessary to
make the theme independent. Future TADL fixes require explicit review and porting.

Native `app/helpers/preload.php` loads every file in the active theme's helper
directory, so a helper's top-level code affects all requests. Keep dependencies
local and avoid adding diagnostic scripts there. The local
[SearchController.php](../controllers/SearchController.php) guards legacy export
requests before loading the stock search controller. Browse and Detail retain
adapted 2.0.10 controller implementations; compare their native counterparts when
upgrading. The physical fatal-error view is also required because native fatal
error lookup does not use the ordinary inherited view resolver.

## Configuration contracts

| File | Important settings and behavior |
| --- | --- |
| [app.conf](../conf/app.conf) | Root `tadl_catalogue_root_idno = kcl`; no custom inheritance; native access enforcement; public search logs disabled; session-based result contexts; theme result reuse disabled |
| [global.conf](../conf/global.conf) | `kcl_issue_date_element = date.dates_value`, shared by decade links, sorting and issue display |
| [browse.conf](../conf/browse.conf) | Object-only Images/List browsing; no facets; Date/Title/Relevance sorts; 36 default results; `cache_timeout = 0` |
| [search.conf](../conf/search.conf) | Object search and sorting; no sample advanced-search forms |
| [detail.conf](../conf/detail.conf) | Native PDF download, navigation arrows, no filename caption, comments, sharing or record-summary PDF action |
| [media_display.conf](../conf/media_display.conf) | Static detail preview through TileViewer, `tilepic` with `mediumlarge` fallback; full PDF.js in the overlay |
| [front.conf](../conf/front.conf) | No featured set on the homepage |
| [thumbnail_focus.conf](../conf/thumbnail_focus.conf) | Optional private face-suggestion directory; missing suggestions fall back without a detector service |

The root must be exactly one nondeleted top-level collection whose hierarchy ID
matches its own ID. Missing, ambiguous or non-root matches deny scoped material.
Keep `tadl_catalogue_root_idno = kcl` nonempty: the generic runtime treats a blank
or absent setting as unscoped mode. Do not clear it to repair a failed lookup;
the configuration check requires the literal KCL setting.
Objects qualify through actual collection relationships; representations qualify
through their owning objects. Shared representations can be readable through an
eligible KCL owner. Menu choices and result facets are not access boundaries.

Issue dates are ISO-formatted **text** in this catalogue profile. A decade link
uses a native field query such as `ca_objects.date.dates_value:189*`, sorted by
Date ascending. The homepage validates the configured element path and uses an
empty-result query if it is invalid. A normalized date facet would require a
date-typed attribute; do not add one against this text field. When changing the
profile, update the shared mapping and verify the search index as well as display.

## Caching and media

Native cache storage is configured in the application's private `setup.php`, not
in this theme. Redis must use a database isolated from Providence and other
Pawtucket instances: the 2.0.10 Redis driver does not prefix keys with the app name.
See [operations](OPERATIONS.md#redis-and-session-isolation).

Redis being enabled does not mean browse results may be reused indefinitely.
Native browse TTL is zero and `disable_tadl_result_cache = 1` during import. Keep
these settings until catalogue/publication freshness is reviewed explicitly.
The browse speed improvement comes from limiting the scope **before** native
sorting; it does not depend on increasing a result-cache TTL. Request-local scope
memoization lasts only for that request. Browser CSS/JS URLs use content hashes,
which are separate from PHP's OPcache and native application caches.

The detail page needs a generated first-page image, normally `mediumlarge` for
PDFs without `tilepic`. It should not embed a collapsed PDF reader. The overlay
uses the application's PDF.js assets and original PDF URL. Native identifiers,
access checks, downloads and initialization remain intact. Local footer/scripts
adapt focus, scrolling and keyboard behavior; review them together when changing
the viewer. See the [accessibility review](../ACCESSIBILITY.md).

The [static-media guide](../support/MEDIA_ALLOWLIST.md) covers the separate web
server guard, byte ranges, exact rendition paths, refresh delay and Tilepic PHP
extractor restriction. Do not replace a missing preview or a denied PDF with an
unguarded shared-media URL.
