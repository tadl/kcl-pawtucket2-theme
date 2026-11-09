<?php
/** Pure path/map operations shared by the CLI publisher and synthetic checks. */

function kclMediaAllowlistKey(string $url, string $prefix): string {
	$path = parse_url($url, PHP_URL_PATH);
	$key = is_string($path) ? ltrim($path, '/') : '';
	if ($key === '' || !preg_match('~\A[A-Za-z0-9_-]+(?:/[A-Za-z0-9_.-]+)+\z~', $key)
		|| preg_match('~(?:\A|/)\.{1,2}(?:/|\z)~', $key)
		|| !str_starts_with($key, $prefix.'/')) {
		throw new RuntimeException('A media URL is outside the configured safe namespace.');
	}
	return $key;
}

/** Enumerate stored versions, including native numbered-page variants. */
function kclMediaAllowlistPaths(array $metadata, callable $url, string $prefix): array {
	$paths = [];
	foreach ($metadata as $version => $info) {
		if (!is_array($info) || empty($info['FILENAME']) || !empty($info['SKIP'])
			|| !empty($info['QUEUED']) || !empty($info['USE_ICON']) || !empty($info['EXTERNAL_URL'])) { continue; }
		$pages = $info['PAGES'] ?? 1;
		if (!is_numeric($pages) || (int)$pages != $pages || $pages < 1 || $pages > 100000) {
			throw new RuntimeException('Invalid stored media page count.');
		}
		for ($page = 1; $page <= (int)$pages; $page++) {
			$address = $url((string)$version, $page);
			if (!is_string($address) || $address === '') { throw new RuntimeException('A stored media URL could not be resolved.'); }
			$key = kclMediaAllowlistKey($address, $prefix);
			$paths[$key] = true;
			if ($page === 1 && $pages > 1) {
				// BaseModel's older URL accessor uses FILENAME even for paged media.
				// Permit that exact declared basename as well as MediaInfoCoder's pages.
				$filename = (string)($info['MAGIC'] ?? '').'_'.(string)$info['FILENAME'];
				if (!preg_match('/\A[A-Za-z0-9_.-]+\z/', $filename) || in_array($filename, ['.', '..'], true)) {
					throw new RuntimeException('Invalid stored media filename.');
				}
				$paths[kclMediaAllowlistKey(dirname($key).'/'.$filename, $prefix)] = true;
			}
		}
	}
	return array_keys($paths);
}

/** The native publication flags and actual hierarchy are checked in SQL first. */
function kclMediaAllowlistCandidates($db, int $root): array {
	if ($root < 1) { throw new RuntimeException('The KCL catalogue root did not resolve.'); }
	$rows = $db->query('SELECT DISTINCT o.object_id, r.representation_id
		FROM ca_collections c
		INNER JOIN ca_objects_x_collections oc ON oc.collection_id = c.collection_id
		INNER JOIN ca_objects o ON o.object_id = oc.object_id
		INNER JOIN ca_objects_x_object_representations oxr ON oxr.object_id = o.object_id
		INNER JOIN ca_object_representations r ON r.representation_id = oxr.representation_id
		WHERE c.hier_collection_id = ? AND c.deleted = 0
		AND o.deleted = 0 AND o.access = 1 AND r.deleted = 0 AND r.access = 1', [$root]);
	if (!$rows) { throw new RuntimeException('Public media membership could not be read.'); }
	$candidates = [];
	while ($rows->nextRow()) {
		$candidates[] = ['object_id' => (int)$rows->get('object_id'), 'representation_id' => (int)$rows->get('representation_id')];
	}
	return $candidates;
}

/** Publish by same-directory rename; Apache sees either complete map. */
function kclMediaAllowlistWrite(string $output, array $keys, string $prefix): void {
	$directory = realpath(dirname($output));
	if ($output === '' || $output[0] !== '/' || !$directory || !is_writable($directory) || is_link($output)) {
		throw new RuntimeException('The map must be a regular file in a writable absolute directory.');
	}
	$keys = array_values(array_unique($keys));
	foreach ($keys as $key) {
		if (!is_string($key) || kclMediaAllowlistKey($key, $prefix) !== $key) {
			throw new RuntimeException('Invalid map key.');
		}
	}
	sort($keys, SORT_STRING);
	$temporary = tempnam($directory, '.kcl-media-');
	if (!$temporary) { throw new RuntimeException('The map temporary file could not be created.'); }
	try {
		$stream = fopen($temporary, 'wb');
		if (!$stream) { throw new RuntimeException('The map temporary file could not be opened.'); }
		try {
			$write = static function (string $line) use ($stream): void {
				if (fwrite($stream, $line) !== strlen($line)) { throw new RuntimeException('The map could not be written completely.'); }
			};
			$write("# KCL public media; generated from native catalogue metadata\n");
			foreach ($keys as $key) { $write($key." 1\n"); }
			if (!fflush($stream)) { throw new RuntimeException('The map could not be flushed.'); }
		} finally { fclose($stream); }
		if (!chmod($temporary, 0640) || !rename($temporary, $output)) { throw new RuntimeException('The map could not be published.'); }
	} finally {
		if (is_file($temporary)) { unlink($temporary); }
	}
}
