# TileViewer theme adaptation

Bundled from the Pawtucket 2.0.10 TileViewer 3.0.2 source (upstream license remains in the file). Original SHA-256: `e2b8531e760c618045595f89d04f2bd2b8d49cbb9bc0490110572db1151b81a8`.

Theme changes are limited to keyboard registration and zoom controls:

- Shortcuts bind to the focusable image canvas, and the Tab shortcut is removed.
- Slider bounds cover the engine's configured maximum (normally 400%) and an already larger fitted image. The initial value matches the actual zoom; fractional steps avoid whole-percent jumps. Fit and subsequent zoom actions synchronize the existing widget through its value API, extending bounds when a resized fit requires it.
- Slider and button zoom use the center of the overscanned canvas. Absolute slider zoom preserves the image point at that anchor across levels and partial edge tiles.
- A new zoom press cancels the previous repeat timer, and document mouseup clears it immediately. A quick click zooms once on press; holding repeats until release. Canvas zoom modes follow the same lifecycle, so rapid clicks cannot leave a timer fighting later slider or minus actions.

Native tile loading, annotations and media callbacks remain intact. The theme asset loader substitutes this file for the native TileViewer script; the original is not loaded twice. Review these adaptations when upgrading Pawtucket.

The original adaptation's [upstream zoom regression](https://github.com/tadl/tadl-pawtucket2-theme/blob/12f2f11c186971713acd12d942908241ef47aca7/tests/tileviewer_zoom_browser_test.js) exercises the actual engine with native jQuery UI and synthetic images. It covers dragging, bounds, scale/position, Fit, keyboard/buttons and rapid press/release behavior. That test is provenance, not a runtime dependency. KCL owns this adaptation and tests its local asset substitution, document controls and standalone packaging in this repository.
