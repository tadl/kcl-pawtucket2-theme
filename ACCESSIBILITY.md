# Accessibility review

The November 9, 2026 review targets the KCL theme interface against
[WCAG 2.2 AA](https://www.w3.org/TR/WCAG22/). It covers the home and About pages,
populated and empty results, Tiles/List browse layouts, issue details, the HTML
OCR disclosure and the PDF reader's controls. This is an engineering review,
not a claim that every archived newspaper PDF conforms to WCAG.

## Interface changes

- Page language, banner/main/search/navigation landmarks, associated search
  labels, a working skip link and consecutive heading levels.
- Accessible names match visible media-action labels. Attached-document
  thumbnails describe their function rather than an imported filename.
- Thumbnail focus frames remain visible inside clipped image containers. Search
  focus darkens the existing border; forced-colors mode adds an inset system
  outline. Other controls retain the bundled base UI's two-color focus indicator.
- The PDF dialog locks background scrolling, traps focus and restores the opener.
  Collapsed PDF sidebars are inert. Native toolbar states and find results are
  exposed through ARIA; nested dialogs receive focus and return it on closing.
- A scoped compatibility fix keeps native PDF.js page shortcuts from consuming
  Enter/Space on buttons. Escape closes secondary PDF controls before closing
  the reader. Native handlers, downloads and viewer initialization remain intact.
- Issue navigation, media actions and the close button have 44-pixel targets.
  Smaller PDF toolbar buttons meet the 24-pixel AA minimum. Inline text links
  retain normal prose spacing.
- Issue content stacks on narrow screens. Footer addresses wrap when text is
  enlarged. The supplied archive copy and OCR caveat are preserved.

## Verification

Standalone regressions render actual theme views and execute the real local
dialog adapter and bundled PDF keyboard handler against synthetic controls:

```sh
php support/check_config.php ../../pawtucket2
php tests/assets_test.php
php tests/media_display_test.php
php tests/media_panel_test.php
php tests/semantic_html_test.php
```

KCL owns its scope, browse, access, cache and media/download regressions. The
standalone check runs its actual views/runtime from an isolated copy without a
sibling custom theme, against stock Pawtucket 2.0.10 and PHP 8.3. Earlier TADL
test results describe the source baseline; KCL no longer needs that checkout.

Live Chrome checks include keyboard-only controls, skip navigation, thumbnail
focus, dialog/secondary-dialog focus and Escape behavior, reader-only scrolling,
320-pixel reflow, 200% text enlargement, WCAG text-spacing overrides, and forced
colors. Temporary browser test styles and emulation settings are removed after
checking. Axe-core 4.14.0 is used only as a development audit tool, never shipped
to visitors. Automated results supplement manual checks; they do not establish
conformance by themselves.

Measured contrast includes body text 11.93:1, ordinary links 7.59:1, muted text
6.40:1 and green text 5.35:1 on white. The search border is 3.25:1 against its
containing panel and 3.57:1 against white; its focused border is darker. These
meet the applicable AA thresholds. The border-only search focus does not claim
the additional AAA focused/unfocused contrast requirement.

## Content and upgrade limits

OCR text is provided in HTML when available through the existing native access
checks. Its accuracy and reading order depend on the original scan and OCR.
This review does not remediate or certify original PDF tagging, document reading
order, tables, illustrations or every issue's text alternative. Dedicated
screen-reader testing with VoiceOver/NVDA remains a separate validation step.
Automated contrast checks cannot resolve transparent OCR layers over scanned
page images or every textured PDF-toolbar surface.

Recheck the native viewer compatibility bridge, access restrictions and focus
behavior when upgrading Pawtucket or PDF.js. Do not remove the bridge solely
because the application version changes; verify the relevant native behavior.
