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
$facet = $browse->getAssoc('ca_objects')['facets']['decade_facet'] ?? [];
checkKclConfig(array_keys($types) === ['objects'], 'Default unrelated browse types were retained.');
checkKclConfig(array_keys($types['objects']['views']) === ['images', 'list'], 'Unexpected browse views.');
checkKclConfig($types['objects']['availableFacets'] === ['decade_facet'], 'Decade facet is unavailable.');
checkKclConfig(($facet['type'] ?? null) === 'normalizedDates' && ($facet['normalization'] ?? null) === 'decades', 'Decade facet must normalize native dates.');
$date_element = $configs['global.conf']->get('kcl_issue_date_element');
checkKclConfig(($facet['element_code'] ?? null) === $date_element, 'Date mapping did not interpolate.');
checkKclConfig($types['objects']['sortBy']['Date'] === 'ca_objects.'.$date_element, 'Issue date sort does not match the facet.');
checkKclConfig(array_keys($configs['search.conf']->getAssoc('multisearchTypes')) === ['objects'], 'Default unrelated searches were retained.');
checkKclConfig($configs['search.conf']->getAssoc('advancedSearchTypes') === [], 'Default sample advanced forms were retained.');
checkKclConfig(array_keys($configs['detail.conf']->getAssoc('detailTypes')) === ['objects'], 'Default unrelated detail types were retained.');
checkKclConfig((string)$configs['app.conf']->get('dont_enforce_access_settings') === '0', 'Native access enforcement must remain enabled.');
checkKclConfig(!$configs['front.conf']->get('front_page_set_code'), 'Default featured set leaked into the theme.');
fwrite(STDOUT, "Native configuration checks passed (".count($configs)." files; default overlays and date mapping).\n");
