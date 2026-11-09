<?php
/** Render the real theme views with synthetic framework adapters; never boots CA. */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$core = realpath($argv[1] ?? __DIR__.'/../../../pawtucket2');
$output = $argv[2] ?? null;
if (!$core || !$output || !is_file($core.'/themes/default/conf/app.conf')) {
	fwrite(STDERR, "Usage: php support/render_preview.php /path/to/pawtucket2 /path/to/empty-output\n");
	exit(1);
}
if (file_exists($output) && (!is_dir($output) || count(scandir($output)) > 2)) {
	fwrite(STDERR, "Preview output must be a new or empty directory.\n");
	exit(1);
}
if (!is_dir($output) && !mkdir($output, 0755, true)) {
	throw new RuntimeException('Cannot create preview output directory.');
}
$theme = dirname(__DIR__);

function copyPreviewFile(string $source, string $target): void {
	if (!is_dir(dirname($target))) { mkdir(dirname($target), 0755, true); }
	if (!copy($source, $target)) { throw new RuntimeException('Cannot copy preview asset.'); }
}

copyPreviewFile($core.'/assets/bootstrap/css/bootstrap.min.css', $output.'/default/bootstrap.min.css');
copyPreviewFile($core.'/assets/jquery/js/jquery-3.7.1.min.js', $output.'/default/jquery.min.js');
foreach (['css/main.css', 'css/fonts.css', 'css/base.css', 'css/accessibility.css', 'css/theme.css',
	'graphics/kcl-logo.png', 'graphics/favicon.png', 'graphics/content-bg.jpg', 'graphics/header-bg.jpg'] as $file) {
	copyPreviewFile($theme.'/assets/pawtucket/'.$file, $output.'/assets/pawtucket/'.$file);
}
foreach (['Regular', 'Bold', 'Italic', 'BoldItalic', 'Light', 'LightItalic'] as $face) {
	$file = 'css/Lato/Lato-'.$face.'.woff2';
	copyPreviewFile($theme.'/assets/pawtucket/'.$file, $output.'/assets/pawtucket/'.$file);
}

function caNavUrl($request, $module, $controller, $action, $params = []): string {
	return '/'.$controller.'/'.$action.($params ? '?'.http_build_query($params) : '');
}
function caGetPageCSSClasses(): string { return 'class="front"'; }
class MetaTagManager {
	public static function getWindowTitle(): string { return 'Kalkaska County Library Newspaper Archive'; }
	public static function getHTML(): string { return ''; }
}
class AssetLoadManager {
	public static function getLoadHTML($request): string {
		return '<link rel="stylesheet" href="/default/bootstrap.min.css">'
			.'<link rel="stylesheet" href="/assets/pawtucket/css/main.css">'
			.'<link rel="stylesheet" href="/assets/pawtucket/css/fonts.css">'
			.'<link rel="stylesheet" href="/assets/pawtucket/css/base.css">'
			.'<link rel="stylesheet" href="/assets/pawtucket/css/accessibility.css">'
			.'<link rel="stylesheet" href="/assets/pawtucket/css/theme.css">'
			.'<script src="/default/jquery.min.js"></script><script>var caUI = {};</script>';
	}
}
class CookieOptionsManager {
	public static function cookieManagerEnabled(): bool { return false; }
}
class TooltipManager {
	public static function getLoadHTML(): string { return ''; }
}
class KclPreviewRequest {
	public $config;
	public function __construct() {
		$this->config = new class {
			public function get($key): ?string { return $key === 'kcl_issue_date_element' ? 'date.dates_value' : null; }
		};
	}
	public function getController(): string { return 'Front'; }
	public function getThemeUrlPath(): string { return ''; }
}
class KclPreviewView {
	public $request;
	private $theme;
	public function __construct(string $theme) {
		$this->theme = $theme;
		$this->request = new KclPreviewRequest();
	}
	public function render(string $path): string {
		if ($path === 'Cookies/banner_html.php') { return ''; }
		ob_start();
		include $this->theme.'/views/'.$path;
		return ob_get_clean();
	}
}
$view = new KclPreviewView($theme);
$html = $view->render('pageFormat/pageHeader.php')
	.$view->render('Front/front_page_html.php')
	.$view->render('pageFormat/pageFooter.php');
if (file_put_contents($output.'/index.html', $html) === false) {
	throw new RuntimeException('Cannot write preview HTML.');
}
fwrite(STDOUT, "Static layout preview written to {$output}/index.html\nCatalogue routes and the PDF viewer require a running Pawtucket instance.\n");
