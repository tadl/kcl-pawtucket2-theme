<?php
/** Exercise the native asset loader with synthetic config/cache/request adapters. */
$core = realpath($argv[1] ?? __DIR__.'/../../../pawtucket2');
$theme = dirname(__DIR__);
if (!$core || !is_file($core.'/app/lib/AssetLoadManager.php')) {
	throw new RuntimeException('Pass a local Pawtucket source path.');
}
define('__CA_LIB_DIR__', $core.'/app/lib');
define('__CA_APP_CONFIG__', 'kcl-synthetic-app.conf');
require_once $core.'/app/lib/Configuration.php';
require_once $core.'/app/lib/AssetLoadManager.php';
require_once $theme.'/helpers/asset_versions.php';
class MemoryCache {
	private static $values = [];
	public static function contains($key, $namespace): bool { return isset(self::$values[$namespace][$key]); }
	public static function save($key, $value, $namespace): void { self::$values[$namespace][$key] = $value; }
	public static function fetch($key, $namespace) { return self::$values[$namespace][$key] ?? null; }
}
function caGetOption($key, $values, $default = null, $options = []) { return $values[$key] ?? $default; }
function caAppIsPawtucket(): bool { return true; }
function caGetAnalyticsIntegrationValues(): array { return []; }
function assetFixtureConfig(array $files): Configuration {
	$class = new ReflectionClass(Configuration::class);
	$config = $class->newInstanceWithoutConstructor();
	$class->getProperty('ops_config_settings')->setValue($config, ['scalars' => ['asset_suffix' => 'synthetic'], 'lists' => [], 'assoc' => []]);
	$class->getProperty('ops_md5_path')->setValue($config, md5(implode('|', $files)));
	foreach ($files as $file) {
		if (is_file($file) && !$config->loadFile($file)) { throw new RuntimeException('Asset fixture config rejected.'); }
	}
	return $config;
}
function assetCheck(bool $condition, string $message): void {
	if (!$condition) { throw new RuntimeException($message); }
}
$app = assetFixtureConfig([$theme.'/conf/app.conf']);
$assets = assetFixtureConfig([$core.'/app/conf/assets.conf', $core.'/themes/default/conf/assets.conf', $theme.'/conf/assets.conf']);
assetCheck(!(bool)$app->get('allowThemeInheritance') && !$app->get('inheritFrom'), 'KCL must retain its own config without an external theme.');
MemoryCache::save(__CA_APP_CONFIG__, $app, 'ConfigurationInstances');
MemoryCache::save('assets.conf', $assets, 'ConfigurationInstances');
$g_asset_config = $assets;
$g_asset_load_list = [];
AssetLoadManager::useMinified(false);
// Stock 2.0.10 includes the absent jquery/tools package in its default load set.
// Match native startup, then require every KCL declaration to register.
AssetLoadManager::register('_default');
foreach ($assets->getAssoc('themeLoadSets')['_default'] as $asset) {
	[$package, $library] = explode('/', $asset, 2);
	assetCheck(AssetLoadManager::register($package, $library), 'KCL runtime asset must register.');
}
$request = new class($core, $theme) {
	private $core;
	private $theme;
	public function __construct($core, $theme) { $this->core = $core; $this->theme = $theme; }
	public function getBaseUrlPath(): string { return ''; }
	public function getThemeUrlPath(): string { return '/themes/kcl'; }
	public function getDefaultThemeUrlPath(): string { return '/themes/default'; }
	public function getThemeDirectoryPath(): string { return $this->theme; }
	public function getDefaultThemeDirectoryPath(): string { return $this->core.'/themes/default'; }
};
$html = tadlAssetLoadHTML($request);
foreach (['main.css', 'fonts.css', 'base.css', 'accessibility.css'] as $file) {
	assetCheck(!is_link($theme.'/assets/pawtucket/css/'.$file) && is_file($theme.'/assets/pawtucket/css/'.$file), 'KCL CSS must be a regular local file.');
	assetCheck(str_contains($html, '/themes/kcl/assets/pawtucket/css/'.$file), 'Native loader must include local KCL styles.');
}
foreach (['user-features', 'display-preferences', 'thumbnail-focus', 'image-downloads', 'document-text', 'navigation'] as $file) {
	assetCheck(!is_link($theme.'/assets/pawtucket/js') && is_file($theme.'/assets/pawtucket/js/'.$file.'.js'), 'Runtime scripts must be local files.');
	assetCheck(str_contains($html, '/themes/kcl/assets/pawtucket/js/'.$file.'.js'), 'Native loader must include local runtime scripts.');
}
foreach (['Regular', 'Bold', 'Italic', 'BoldItalic', 'Light', 'LightItalic'] as $face) {
	assetCheck(is_file($theme.'/assets/pawtucket/css/Lato/Lato-'.$face.'.woff2'), 'Bundled Lato faces must resolve locally.');
}
assetCheck(!str_contains($html, '/themes/tadl/') && !str_contains($html, '/themes/default/assets/pawtucket/'), 'Theme packages must resolve to KCL without another theme.');
assetCheck(strpos($html, 'base.css') < strpos($html, 'accessibility.css') && strpos($html, 'accessibility.css') < strpos($html, 'css/theme.css?'), 'KCL branding must follow its base and accessibility styles.');
assetCheck(str_contains($html, 'css/theme.css?rev=synthetic&amp;v='.substr(hash_file('sha256', $theme.'/assets/pawtucket/css/theme.css'), 0, 16)), 'KCL stylesheet must receive its content hash while preserving the native suffix.');
assetCheck(!str_contains($html, 'recent-writing.js') && !str_contains($html, 'fonts.googleapis.com'), 'KCL must not load the unrelated writing feed or external fonts.');
fwrite(STDOUT, "Assets passed: native loader, local CSS/fonts/scripts, override order and content hashes.\n");
