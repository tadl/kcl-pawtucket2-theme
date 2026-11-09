<?php
// Use the real configuration parser and native app defaults, without setup/DB.
$theme = dirname(__DIR__);
$core = realpath($argv[1] ?? $theme.'/../../pawtucket2');
if (!$core) { throw new RuntimeException('Pawtucket source is required.'); }
require $core.'/app/lib/Configuration.php';
function mediaDisplayFixture(array $files): Configuration {
	$class = new ReflectionClass(Configuration::class);
	$config = $class->newInstanceWithoutConstructor();
	$class->getProperty('ops_config_settings')->setValue($config, ['scalars' => [], 'lists' => [], 'assoc' => []]);
	$class->getProperty('ops_md5_path')->setValue($config, md5(implode('|', $files)));
	foreach ($files as $file) {
		if (!$config->loadFile($file)) { throw new RuntimeException('Media display configuration was rejected.'); }
	}
	return $config;
}
$base = $core.'/app/conf/media_display.conf';
$native = mediaDisplayFixture([$base]);
$parent = mediaDisplayFixture([$base, $theme.'/../tadl/conf/media_display.conf']);
$kcl = mediaDisplayFixture([$base, $theme.'/conf/media_display.conf']);
if (!is_link($theme.'/conf/media_display.conf')
	|| realpath($theme.'/conf/media_display.conf') !== realpath($theme.'/../tadl/conf/media_display.conf')) {
	throw new RuntimeException('KCL must reuse the complete TADL media display configuration.');
}
$detail = $kcl->getAssoc('detail')['pdf'];
$overlay = $kcl->getAssoc('media_overlay')['pdf'];
if ($native->getAssoc('detail')['pdf']['viewer'] !== 'pdfjs'
	|| $detail !== $parent->getAssoc('detail')['pdf']
	|| $detail['viewer'] !== 'TileViewer' || $detail['display_version'] !== 'tilepic'
	|| $detail['alt_display_version'] !== 'mediumlarge' || (int)$detail['no_overlay'] !== 0
	|| $overlay !== $parent->getAssoc('media_overlay')['pdf'] || $overlay['viewer'] !== 'pdfjs') {
	throw new RuntimeException('PDF details must match TADL previews while retaining its full PDF overlay.');
}
echo "Media display passed: physical TADL configuration, detail preview and full PDF overlay.\n";
