<?php
/** Check native config overlays without loading setup.php, caches or a database. */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
$core = realpath($argv[1] ?? __DIR__.'/../../../pawtucket2');
if (!$core || !is_file($core.'/app/lib/Configuration.php')) {
	fwrite(STDERR, "Usage: php support/check_config.php /path/to/pawtucket2\n");
	exit(1);
}
require $core.'/app/lib/Configuration.php';
$theme = dirname(__DIR__);
$configs = [];
foreach (glob($theme.'/conf/*.conf') as $file) {
	$class = new ReflectionClass(Configuration::class);
	$config = $class->newInstanceWithoutConstructor();
	$class->getProperty('ops_config_settings')->setValue($config, ['scalars' => [], 'lists' => [], 'assoc' => []]);
	$class->getProperty('ops_md5_path')->setValue($config, md5($file));
	$files = [$theme.'/conf/global.conf', $core.'/themes/default/conf/'.basename($file), $file];
	foreach (array_unique($files) as $source) {
		if (is_file($source) && !$config->loadFile($source)) {
			throw new RuntimeException('Native parser rejected '.basename($source));
		}
	}
	$configs[basename($file)] = $config;
}
function checkKclConfig(bool $condition, string $message): void {
	if (!$condition) { throw new RuntimeException($message); }
}
$browse = $configs['browse.conf'];
$types = $browse->getAssoc('browseTypes');
$facets = $browse->getAssoc('ca_objects')['facets'] ?? [];
checkKclConfig(array_keys($types) === ['objects'], 'Default unrelated browse types were retained.');
checkKclConfig(array_keys($types['objects']['views']) === ['images', 'list'], 'Unexpected browse views.');
checkKclConfig($facets === [], 'Text dates must not use a normalizedDates facet.');
$date_element = $configs['global.conf']->get('kcl_issue_date_element');
checkKclConfig($types['objects']['sortBy']['Date'] === 'ca_objects.'.$date_element, 'Issue date sort does not match the configured date field.');
checkKclConfig(array_keys($configs['search.conf']->getAssoc('multisearchTypes')) === ['objects'], 'Default unrelated searches were retained.');
checkKclConfig($configs['search.conf']->getAssoc('advancedSearchTypes') === [], 'Default sample advanced forms were retained.');
checkKclConfig(array_keys($configs['detail.conf']->getAssoc('detailTypes')) === ['objects'], 'Default unrelated detail types were retained.');
checkKclConfig((string)$configs['app.conf']->get('dont_enforce_access_settings') === '0', 'Native access enforcement must remain enabled.');
$app = $configs['app.conf'];
checkKclConfig((string)$app->get('allowThemeInheritance') === '0', 'KCL must retain its own configuration.');
checkKclConfig(!$app->get('inheritFrom') && (!$app->exists('inherit_from') || !$app->get('inherit_from')), 'KCL must not inherit another theme.');
checkKclConfig($app->get('tadl_catalogue_root_idno') === 'kcl', 'The active catalogue boundary must be KCL.');
checkKclConfig((string)$app->get('dont_use_search_log') === '1', 'Public searches must not write catalogue search logs.');
checkKclConfig((string)$app->get('always_use_session_based_storage_for_find_result_contexts') === '1', 'Navigation contexts must use native sessions.');
checkKclConfig(!$configs['front.conf']->get('front_page_set_code'), 'Default featured set leaked into the theme.');
fwrite(STDOUT, "Native configuration checks passed (".count($configs)." files; default overlays and date mapping).\n");
