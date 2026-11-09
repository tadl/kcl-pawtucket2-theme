				</div>
			</main>
		</div></div>
	</div>
	<footer id="footer" class="kcl-footer">
		<div class="container kcl-shell kcl-footer-inner">
			<div>
				<strong>Kalkaska County Library</strong>
				<p>Digital newspaper archive · 1879–2012</p>
			</div>
			<div class="kcl-footer-links">
				<a href="mailto:info@kalkaskalibrary.org">info@kalkaskalibrary.org</a>
				<a href="tel:2312589411">231-258-9411</a>
			</div>
		</div>
		<div class="container kcl-shell kcl-footer-credit">
			<span>Powered by <a href="https://www.collectiveaccess.org/">CollectiveAccess</a></span>
			<?php if (CookieOptionsManager::cookieManagerEnabled()) { ?>
				<a href="<?= htmlspecialchars(caNavUrl($this->request, '', 'Cookies', 'manage'), ENT_QUOTES, 'UTF-8'); ?>">Manage cookies</a>
			<?php } ?>
		</div>
	</footer>
	<?= TooltipManager::getLoadHTML(); ?>
	<div id="caMediaPanel" role="dialog" aria-modal="true" aria-label="Document and image viewer" aria-hidden="true" tabindex="-1">
		<div id="caMediaPanelContentArea"></div>
	</div>
	<script>
		var caMediaPanel;
		jQuery(document).ready(function () {
			if (caUI.initPanel) {
				caMediaPanel = caUI.initPanel({
					panelID: 'caMediaPanel',
					panelContentID: 'caMediaPanelContentArea',
					onCloseCallback: function (data) {
						if (data && data.url) { window.location = data.url; }
					},
					exposeBackgroundColor: '#FFFFFF',
					exposeBackgroundOpacity: 0.7,
					panelTransitionSpeed: 400,
					allowMobileSafariZooming: true,
					mobileSafariViewportTagID: '_msafari_viewport',
					closeButtonSelector: '.close'
				});
				if (window.tadlAccessiblePanel) {
					window.tadlAccessiblePanel(caMediaPanel, document.getElementById('caMediaPanel'));
				}
				// PDF.js owns its controls and keyboard actions; mirror its existing
				// class-based state into ARIA without changing the reader's handlers.
				var panel = document.getElementById('caMediaPanel');
				var content = document.getElementById('caMediaPanelContentArea');
				var observers = [], nested = null, nestedBackground = [], nestedOpener = null;
				var escapedSecondary = false, escapeReturn = null;
				function visible(node) { return node && !node.hidden && !node.classList.contains('hidden'); }
				function restoreNested() {
					nestedBackground.forEach(function (saved) {
						if (!saved.inert) { saved.node.removeAttribute('inert'); }
						if (saved.hidden === null) { saved.node.removeAttribute('aria-hidden'); }
						else { saved.node.setAttribute('aria-hidden', saved.hidden); }
					});
					nestedBackground = [];
					if (nested && panel.getAttribute('aria-hidden') === 'false') {
						var target = nestedOpener && nestedOpener.isConnected && nestedOpener.getClientRects().length ? nestedOpener
							: content.querySelector('#secondaryToolbarToggle');
						if (target && target.getClientRects().length) { target.focus(); }
					}
					nested = nestedOpener = null;
				}
				function watch(node, update) {
					if (!node) { return; }
					update();
					var observer = new MutationObserver(update);
					observer.observe(node, {attributes: true, attributeFilter: ['class', 'hidden']});
					observers.push(observer);
				}
				function preparePdf() {
					observers.forEach(function (observer) { observer.disconnect(); }); observers = [];
					restoreNested();
					var reader = content.querySelector('#outerContainer');
					if (!reader) { return; }
					function node(id) { return reader.querySelector('#' + id); }
					function disclosure(buttonId, targetId, stateNode, isOpen, hideCollapsed) {
						var button = node(buttonId), target = node(targetId);
						if (!button || !target) { return; }
						button.setAttribute('aria-controls', targetId);
						watch(stateNode, function () {
							var open = isOpen();
							button.setAttribute('aria-expanded', open ? 'true' : 'false');
							if (hideCollapsed) {
								// PDF.js slides its sidebar offscreen without removing its controls
								// from keyboard navigation or the accessibility tree.
								target.setAttribute('aria-hidden', open ? 'false' : 'true');
								if (open) { target.removeAttribute('inert'); }
								else { target.setAttribute('inert', ''); if (target.contains(document.activeElement)) { button.focus(); } }
							}
						});
					}
					disclosure('sidebarToggle', 'sidebarContainer', reader, function () { return reader.classList.contains('sidebarOpen'); }, true);
					disclosure('viewFind', 'findbar', node('findbar'), function () { return visible(node('findbar')); });
					disclosure('secondaryToolbarToggle', 'secondaryToolbar', node('secondaryToolbar'), function () { return visible(node('secondaryToolbar')); });
					['viewThumbnail', 'viewOutline', 'viewAttachments', 'cursorSelectTool', 'cursorHandTool',
						'scrollVertical', 'scrollHorizontal', 'scrollWrapped', 'spreadNone', 'spreadOdd', 'spreadEven'].forEach(function (id) {
						var button = node(id);
						watch(button, function () { button.setAttribute('aria-pressed', button.classList.contains('toggled') ? 'true' : 'false'); });
					});
					['findResultsCount', 'findMsg'].forEach(function (id) {
						var status = node(id);
						if (status) { status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite'); status.setAttribute('aria-atomic', 'true'); }
					});
					var pages = node('viewerContainer'), pageNumber = node('pageNumber'), zoom = node('scaleSelect'), find = node('findInput'), error = node('errorWrapper');
					if (pages) { pages.setAttribute('role', 'region'); pages.setAttribute('aria-label', 'Newspaper document pages'); }
					if (pageNumber) { pageNumber.setAttribute('aria-label', 'Page number'); }
					if (zoom) { zoom.setAttribute('aria-label', zoom.getAttribute('title') || 'Zoom'); }
					if (find) { find.setAttribute('aria-label', 'Find in document'); }
					if (error) { error.setAttribute('role', 'alert'); }
					var dialogs = [
						{id: 'documentPropertiesOverlay', label: 'Document properties', focus: 'documentPropertiesClose', opener: 'documentProperties'},
						{id: 'passwordOverlay', label: 'PDF password', focus: 'password', opener: null},
						{id: 'printServiceOverlay', label: 'Print document', focus: 'printCancel', opener: 'print'}
					];
					dialogs.forEach(function (dialog) {
						dialog.node = node(dialog.id);
						if (dialog.node) {
							dialog.node.setAttribute('role', 'dialog'); dialog.node.setAttribute('aria-modal', 'true');
							dialog.node.setAttribute('aria-label', dialog.label); dialog.node.setAttribute('tabindex', '-1');
						}
					});
					function synchronizeDialog() {
						var active = visible(node('overlayContainer')) ? dialogs.find(function (dialog) { return visible(dialog.node); }) : null;
						if (active === nested) { return; }
						restoreNested();
						if (!active) { return; }
						nested = active;
						nestedOpener = active.opener ? node(active.opener) : document.activeElement;
						// Hide only siblings along the nested dialog's branch. This also
						// keeps the outer close/toolbar out of the inner dialog's Tab order.
						var branch = active.node, parent = branch.parentElement;
						while (parent) {
							Array.prototype.forEach.call(parent.children, function (sibling) {
								if (sibling === branch || /^(SCRIPT|STYLE|LINK)$/.test(sibling.tagName)) { return; }
								nestedBackground.push({node: sibling, inert: sibling.hasAttribute('inert'), hidden: sibling.getAttribute('aria-hidden')});
								sibling.setAttribute('inert', ''); sibling.setAttribute('aria-hidden', 'true');
							});
							if (parent === panel) { break; }
							branch = parent; parent = parent.parentElement;
						}
						if (!active.node.contains(document.activeElement)) {
							(node(active.focus) || active.node).focus();
						}
					}
					dialogs.forEach(function (dialog) { watch(dialog.node, synchronizeDialog); });
					watch(node('overlayContainer'), synchronizeDialog);
				}
				// AJAX replaces direct children here. PDF page/text-layer mutations
				// below them do not rebuild observers or reinitialize the reader.
				new MutationObserver(preparePdf).observe(content, {childList: true});
				preparePdf();
				document.addEventListener('keydown', function (event) {
					if (panel.getAttribute('aria-hidden') !== 'false' || !content.querySelector('#outerContainer')) { return; }
					if (event.target.tagName === 'BUTTON' && content.contains(event.target) && !event.altKey && !event.ctrlKey && !event.metaKey
						&& (event.key === 'Enter' || event.key === ' ' || event.keyCode === 13 || event.keyCode === 32)) {
						// The legacy viewer's window shortcut treats button Enter/Space as
						// page navigation. Keep target handlers and native button activation.
						event.stopPropagation();
					}
				});
				document.addEventListener('keydown', function (event) {
					if (panel.getAttribute('aria-hidden') !== 'false') { return; }
					if (event.key === 'Escape' || event.keyCode === 27) {
						var find = content.querySelector('#findbar'), tools = content.querySelector('#secondaryToolbar');
						if (nested || visible(find) || visible(tools)) {
							escapedSecondary = true;
							escapeReturn = nested ? null : content.querySelector(visible(find) ? '#viewFind' : '#secondaryToolbarToggle');
						}
					}
					if (!nested || event.key !== 'Tab') { return; }
					var controls = Array.prototype.filter.call(nested.node.querySelectorAll('a[href],button,input:not([type="hidden"]),select,textarea,[tabindex]'), function (control) {
						return !control.disabled && control.tabIndex >= 0 && control.getClientRects().length && !control.closest('[inert]');
					});
					var index = controls.indexOf(document.activeElement);
					if (!controls.length) { event.preventDefault(); nested.node.focus(); }
					else if (index < 0 || (event.shiftKey && index === 0) || (!event.shiftKey && index === controls.length - 1)) {
						event.preventDefault(); controls[event.shiftKey ? controls.length - 1 : 0].focus();
					}
				}, true);
				document.addEventListener('keyup', function (event) {
					if ((event.key !== 'Escape' && event.keyCode !== 27) || !escapedSecondary) { return; }
					escapedSecondary = false;
					// PDF.js already handled keydown. Keep native outer-panel keyup
					// handling for the next Escape after its secondary UI has closed.
					event.stopPropagation();
					if (escapeReturn && escapeReturn.getClientRects().length) { escapeReturn.focus(); }
					escapeReturn = null;
				}, true);
			}
		});
	</script>
	<?= $this->render('Cookies/banner_html.php'); ?>
</body>
</html>
