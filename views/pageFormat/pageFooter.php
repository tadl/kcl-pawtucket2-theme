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
			}
		});
	</script>
	<?= $this->render('Cookies/banner_html.php'); ?>
</body>
</html>
