# Bundled runtime provenance

KCL became an independent theme on November 9, 2026. Its custom runtime and base
UI were adapted from [TADL's Pawtucket theme](https://github.com/tadl/tadl-pawtucket2-theme)
at `12f2f11c186971713acd12d942908241ef47aca7`, for Pawtucket 2.0.10. The copied
runtime is part of this repository, not a live dependency. TADL updates must be
reviewed and explicitly ported if wanted; normal KCL deployments copy only KCL.

The owned code includes scoped controllers/helpers/plugins, inherited result and
media views, accessibility/browser scripts, base CSS and the adapted TileViewer.
Existing KCL views and branding were retained. Literal view includes now resolve
locally. Whitespace was normalized in copied templates without changing multiline
PHP string contents. Historical `tadl*` names remain to preserve tested APIs,
selectors and cache/configuration contracts.

CollectiveAccess and TADL-derived theme code retain their source notices and GPLv3
license (`LICENSE`). `assets/pawtucket/css/main.css` and generic viewer graphics
come from stock Pawtucket 2.0.10; the two base background images come from TADL's
published theme. KCL overrides its public branding and surfaces.

The six Lato WOFF2 files are unmodified copies of Pawtucket's distributed Lato
faces. Their SIL Open Font License and copyright notice are included in
`assets/pawtucket/css/Lato/OFL.txt` (upstream notice maintained by
[Google Fonts](https://github.com/google/fonts/blob/main/ofl/lato/OFL.txt)). The
local font stylesheet declares only these faces; unused licensed-font examples
from the native stylesheet were not copied.

`assets/pawtucket/vendor/README.md` records the TileViewer origin and adaptations.
KCL's logo/favicon are library branding, documented separately in `README.md`.
No catalogue records, media originals, credentials, configured application files,
operational logs or private data are included.
