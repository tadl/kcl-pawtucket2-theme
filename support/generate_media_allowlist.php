<?php
/** Read-only catalogue projection for Apache's exact-path RewriteMap. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/media_allowlist.php';
$options = getopt('', ['app-root:', 'output:', 'url-prefix:', 'quiet', 'diagnostics', 'help']);
if (isset($options['help']) || empty($options['app-root']) || empty($options['output'])) {
	fwrite(STDOUT, "Usage: php support/generate_media_allowlist.php --app-root=/path/to/pawtucket --output=/private/path/media-allowlist.map [--url-prefix=media/ca_kcl] [--quiet] [--diagnostics]\n");
	exit(isset($options['help']) ? 0 : 1);
}
$output = (string)$options['output'];
$prefix = trim((string)($options['url-prefix'] ?? 'media/ca_kcl'), '/');
if (!preg_match('~\Amedia/[A-Za-z0-9_-]+\z~', $prefix) || $output[0] !== '/') {
	fwrite(STDERR, "Use a safe media namespace and an absolute map filename.\n"); exit(1);
}
$lock = null;
try {
	$app_root = realpath((string)$options['app-root']);
	$parent = realpath(dirname($output));
	if (!$app_root || !is_file($app_root.'/setup.php') || !$parent
		|| $parent === $app_root || str_starts_with($parent, $app_root.'/')
		|| is_link($output.'.lock')) { throw new RuntimeException('Invalid application or private map location.'); }
	$lock = fopen($output.'.lock', 'c');
	if (!$lock || !chmod($output.'.lock', 0640)) { throw new RuntimeException('The generator lock could not be opened.'); }
	if (!flock($lock, LOCK_EX | LOCK_NB)) { exit(0); }
	chdir($app_root);
	define('__CA_APP_TYPE__', 'PAWTUCKET');
	define('__CA_SEARCH_IS_FOR_PUBLIC_DISPLAY__', 1);
	$_GET = $_POST = $_COOKIE = $_REQUEST = [];
	require $app_root.'/setup.php';
	require_once __CA_LIB_DIR__.'/Controller/Request/RequestHTTP.php';
	require_once __CA_LIB_DIR__.'/Controller/Response.php';
	require_once __CA_LIB_DIR__.'/Media/MediaInfoCoder.php';
	$_SERVER['HTTP_HOST'] = defined('__CA_SITE_HOSTNAME__') ? __CA_SITE_HOSTNAME__ : 'localhost';
	$GLOBALS['g_request'] = $request = new RequestHTTP(new Response(), [
		'no_headers' => true,
		'simulateWith' => ['SCRIPT_NAME' => '/index.php', 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/',
			'PATH_INFO' => '/', 'REMOTE_ADDR' => '192.0.2.1', 'GET' => [], 'POST' => [], 'COOKIE' => []]
	]);
	if (__CA_APP_TYPE__ !== 'PAWTUCKET' || $request->isLoggedIn()
		|| tadlCatalogueRootCode($request) !== 'kcl' || $request->config->get('dont_enforce_access_settings')) {
		throw new RuntimeException('The public KCL instance policy is required.');
	}
	$scope = tadlCatalogueScope($request);
	if (!$scope || !$scope->root) { throw new RuntimeException('The KCL catalogue root did not resolve.'); }
	$db = Datamodel::getInstanceByTableName('ca_collections', false)->getDb();
	$representation_ids = [];
	foreach (array_chunk(kclMediaAllowlistCandidates($db, (int)$scope->root), 500) as $candidates) {
		// Native bundle, type/source and ACL checks remain authoritative after SQL.
		foreach (tadlReadableMediaRows($request, $candidates, [1], true) as $row) {
			$representation_ids[(int)$row['representation_id']] = true;
		}
	}
	$keys = [];
	$read = static function ($rows) use (&$keys, $prefix): void {
		if (!$rows) { throw new RuntimeException('Stored media metadata could not be read.'); }
		while ($rows->nextRow()) {
			$metadata = $rows->getMediaInfo('media');
			if (!$metadata) { continue; }
			$coder = new MediaInfoCoder($metadata);
			foreach (kclMediaAllowlistPaths($metadata,
				static fn($version, $page) => $coder->getMediaUrl($version, ['page' => $page, 'localOnly' => true]), $prefix) as $key) {
				$keys[$key] = true;
			}
		}
	};
	foreach (array_chunk(array_keys($representation_ids), 500) as $ids) {
		$read($db->query('SELECT media FROM ca_object_representations WHERE representation_id IN (?) AND deleted = 0 AND access = 1', [$ids]));
		// Document page/frame derivatives inherit the public owning representation.
		$read($db->query('SELECT media FROM ca_object_representation_multifiles WHERE representation_id IN (?)', [$ids]));
	}
	kclMediaAllowlistWrite($output, array_keys($keys), $prefix);
	if (!isset($options['quiet'])) { fwrite(STDOUT, count($representation_ids).' public representations; '.count($keys)." exact media paths published.\n"); }
} catch (Throwable $error) {
	$closed = false;
	try { kclMediaAllowlistWrite($output, [], $prefix); $closed = true; } catch (Throwable $ignored) {}
	// Native exceptions may contain database configuration; never echo them here.
	fwrite(STDERR, 'KCL media map generation failed; '.($closed ? 'deny-all map installed.' : 'deny-all map could not be installed.').PHP_EOL);
	if (isset($options['diagnostics'])) {
		// File/line/function locations identify the failing branch without values,
		// SQL, credentials, native exception messages or call arguments.
		fwrite(STDERR, get_class($error).' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
		foreach ($error->getTrace() as $frame) {
			fwrite(STDERR, basename($frame['file'] ?? '').':'.($frame['line'] ?? 0).' '.($frame['function'] ?? '').PHP_EOL);
		}
	}
	exit(1);
} finally {
	if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
