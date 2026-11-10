<?php
/** Run the real theme checks from a private copy with no sibling custom theme. */
$core = realpath($argv[1] ?? __DIR__.'/../../../pawtucket2');
if (!$core || !is_file($core.'/app/lib/View.php')) {
	throw new RuntimeException('Pass a local Pawtucket source path.');
}
$source = dirname(__DIR__);
$temporary = sys_get_temp_dir().'/kcl-standalone-'.bin2hex(random_bytes(10));
if (!mkdir($temporary, 0700)) { throw new RuntimeException('Cannot create private standalone fixture.'); }
$temporary = realpath($temporary);
$theme = $temporary.'/themes/kcl';
mkdir($theme, 0700, true);

function standaloneCheck(bool $condition, string $message): void {
	if (!$condition) { throw new RuntimeException($message); }
}
function standaloneCopy(string $source, string $target): void {
	standaloneCheck(!is_link($source), 'Standalone theme source must not contain symlinks.');
	if (is_dir($source)) {
		if (!is_dir($target)) { mkdir($target, 0700); }
		foreach (new DirectoryIterator($source) as $entry) {
			if (!$entry->isDot()) { standaloneCopy($entry->getPathname(), $target.'/'.$entry->getFilename()); }
		}
	} else {
		standaloneCheck(is_file($source) && copy($source, $target), 'Cannot copy standalone theme fixture.');
	}
}
function standaloneRemove(string $path): void {
	if (is_link($path) || is_file($path)) { unlink($path); return; }
	foreach (new DirectoryIterator($path) as $entry) {
		if (!$entry->isDot()) { standaloneRemove($entry->getPathname()); }
	}
	rmdir($path);
}
function standaloneRun(array $command, string $workingDirectory): string {
	$process = proc_open($command, [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes, $workingDirectory);
	if (!is_resource($process)) { throw new RuntimeException('Cannot execute standalone check.'); }
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	$scripts = array_values(array_filter($command, static fn($argument) => str_ends_with($argument, '.php')));
	standaloneCheck(proc_close($process) === 0, 'Standalone '.basename($scripts[0] ?? $command[0]).' failed: '.$output.$error);
	return $output;
}
class MemoryCache {
	private static array $values = [];
	public static function contains($key, $namespace): bool { return isset(self::$values[$namespace][$key]); }
	public static function save($key, $value, $namespace): void { self::$values[$namespace][$key] = $value; }
	public static function fetch($key, $namespace) { return self::$values[$namespace][$key] ?? null; }
}
function caGetOption($key, $values, $default = null, $options = []) { return $values[$key] ?? $default; }

try {
	// Copy only shipped runtime/check directories; no VCS data, operational files,
	// sibling theme, configured application setup.php, sessions or database.
	foreach (['assets','conf','controllers','helpers','plugins','support','tests','views'] as $directory) {
		standaloneCopy($source.'/'.$directory, $theme.'/'.$directory);
	}
	standaloneCheck(!file_exists(dirname($theme).'/tadl'), 'The fixture must have no sibling custom theme.');
	define('__CA_LIB_DIR__', $core.'/app/lib');
	define('__CA_THEME_DIR__', $theme);
	define('__CA_THEMES_DIR__', dirname($theme));
	define('__CA_APP_CONFIG__', $theme.'/conf/app.conf');
	require_once $core.'/app/lib/Configuration.php';
	$class = new ReflectionClass(Configuration::class);
	$app = $class->newInstanceWithoutConstructor();
	$class->getProperty('ops_config_settings')->setValue($app, ['scalars'=>[], 'lists'=>[], 'assoc'=>[]]);
	$class->getProperty('ops_md5_path')->setValue($app, md5(__CA_APP_CONFIG__));
	standaloneCheck($app->loadFile(__CA_APP_CONFIG__), 'Native parser must accept the standalone app config.');
	standaloneCheck(!(bool)$app->get('allowThemeInheritance')
		&& (!$app->exists('inheritFrom') || !$app->get('inheritFrom'))
		&& (!$app->exists('inherit_from') || !$app->get('inherit_from')),
		'Standalone KCL must not inherit another custom theme.');
	MemoryCache::save(__CA_APP_CONFIG__, $app, 'ConfigurationInstances');
	require_once $core.'/app/lib/View.php';
	$request = new class($core) {
		public function __construct(private string $core) {}
		public function getDefaultThemeDirectoryPath(): string { return $this->core.'/themes/default'; }
		public function getController(): string { return 'About'; }
	};
	$view = new View($request, $theme.'/views');
	standaloneCheck($view->getViewPaths() === [$core.'/themes/default/views', $theme.'/views'],
		'Native view resolution may use only KCL and the application default.');
	foreach (['Browse/browse_results_html.php','Search/tadl_search_results_subview_html.php','Details/detail_field_helpers.php',
		'Details/image_actions_script.php','mediaViewers/viewerWrapper.php','mediaViewers/pdfjs.php','system/error_content_html.php'] as $file) {
		standaloneCheck(is_file($theme.'/views/'.$file) && $view->viewExists($file), 'A required standalone view is missing.');
	}
	$html = $view->render('About/archive_text_html.php', true);
	standaloneCheck(str_contains($html, 'Kalkaska County Library') && str_contains($html, 'Optical Character Recognition'),
		'Native view rendering must read KCL content from the isolated theme.');

	// Restrict child PHP reads to the isolated fixture and stock application.
	// Child test temp files also stay here. An include of the original sibling theme
	// cannot silently make a broken standalone fixture pass.
	$allowed = $temporary.PATH_SEPARATOR.$core;
	mkdir($temporary.'/runtime-tmp', 0700);
	$php = [PHP_BINARY, '-d', 'open_basedir='.$allowed, '-d', 'sys_temp_dir='.$temporary.'/runtime-tmp'];
	$commands = ['support/check_config.php','tests/assets_test.php','tests/media_display_test.php',
		'tests/semantic_html_test.php','tests/navigation_test.php','tests/media_panel_test.php','tests/decade_links_test.php',
		'tests/error_view_test.php','tests/media_allowlist_test.php','tests/catalogue_scope_test.php',
		'tests/catalogue_browse_scope_test.php','tests/record_access_test.php','tests/result_cache_test.php',
		'tests/image_download_test.php'];
	foreach ($commands as $file) {
		standaloneRun([...$php, $theme.'/'.$file, $core], $theme);
	}
	standaloneRun([...$php, $theme.'/support/render_preview.php', $core, $temporary.'/preview'], $theme);
	$preview = file_get_contents($temporary.'/preview/index.html');
	standaloneCheck(str_contains($preview, 'Kalkaska County Library') && str_contains($preview, 'Search newspapers')
		&& is_file($temporary.'/preview/assets/pawtucket/graphics/kcl-logo.png'), 'The isolated static preview must render KCL and its local branding.');
	echo 'Standalone passed: native view resolver/render, '.count($commands).' isolated checks, and static preview; no sibling theme or symlinks.'.PHP_EOL;
} finally {
	standaloneRemove($temporary);
}
