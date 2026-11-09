<?php
/** Synthetic SQL/media fixtures only; never bootstrap an application database. */
require dirname(__DIR__).'/support/media_allowlist.php';
$checks = 0;
function mediaCheck(bool $condition, string $message): void {
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function mediaReject(callable $callback, string $message): void {
	try { $callback(); } catch (RuntimeException $error) { mediaCheck(true, $message); return; }
	mediaCheck(false, $message);
}
$prefix = 'media/ca_kcl';
mediaCheck(kclMediaAllowlistKey('https://archive.example.org/media/ca_kcl/original/1/ABC_issue.pdf?download=1', $prefix)
	=== 'media/ca_kcl/original/1/ABC_issue.pdf', 'URL queries must not enter RewriteMap keys.');
foreach (['media/ca_other/original/file.pdf', 'media/ca_kcl/../private/file.pdf', 'media/ca_kcl/%2e%2e/private.pdf',
	'media/ca_kcl/preview/file.pdf extra', "media/ca_kcl/preview/file.pdf\nother 1", 'media/ca_kcl/preview\\outside.pdf',
	'media/ca_kcl//preview/file.pdf'] as $invalid) {
	mediaReject(static fn() => kclMediaAllowlistKey($invalid, $prefix), 'Unsafe or outside paths must be rejected.');
}
$metadata = [
	'original' => ['FILENAME' => 'issue.pdf', 'MAGIC' => 'AAA'],
	'preview' => ['FILENAME' => 'page.jpg', 'MAGIC' => 'BBB', 'PAGES' => 3],
	'tilepic' => ['FILENAME' => 'image.tpc', 'MAGIC' => 'CCC'],
	'queued' => ['FILENAME' => 'unfinished.jpg', 'QUEUED' => 1],
	'icon' => ['FILENAME' => 'icon.jpg', 'USE_ICON' => 'generic'],
	'external' => ['FILENAME' => 'elsewhere.jpg', 'EXTERNAL_URL' => 'https://example.org/elsewhere.jpg'],
	'alias' => ['FILENAME' => 'alias.jpg', 'SKIP' => 1],
	'INPUT' => ['MIMETYPE' => 'application/pdf']
];
$calls = [];
$paths = kclMediaAllowlistPaths($metadata, static function ($version, $page) use (&$calls) {
	$calls[] = [$version, $page];
	return 'https://archive.example.org/media/ca_kcl/'.[
		'original' => 'original/1/AAA_issue.pdf', 'preview' => 'preview/1/BBB_page_'.$page.'.jpg',
		'tilepic' => 'tilepic/1/CCC_image.tpc'
	][$version];
}, $prefix);
mediaCheck(count($paths) === 6 && in_array('media/ca_kcl/preview/1/BBB_page_3.jpg', $paths, true)
	&& in_array('media/ca_kcl/preview/1/BBB_page.jpg', $paths, true)
	&& in_array('media/ca_kcl/tilepic/1/CCC_image.tpc', $paths, true), 'Exact originals, page variants, declared basename and tilepic container must be enumerated.');
mediaCheck(count($calls) === 5 && !in_array(['queued', 1], $calls, true), 'Queued/icon/external/alias metadata must not become local paths.');
mediaReject(static fn() => kclMediaAllowlistPaths(['preview'=>['FILENAME'=>'x.jpg', 'PAGES'=>100001]], static fn()=>'unused', $prefix), 'Invalid page counts must fail closed.');

class MediaFixtureRows {
	private $row;
	public function __construct(private PDOStatement $statement) {}
	public function nextRow() { return ($this->row = $this->statement->fetch(PDO::FETCH_ASSOC)) !== false; }
	public function get($key) { return $this->row[$key]; }
}
class MediaFixtureDB {
	public PDO $pdo;
	public function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
	public function query($sql, $values) {
		mediaCheck((bool)preg_match('/\ASELECT\b/', $sql), 'Generator cohort SQL must be read-only.');
		$statement = $this->pdo->prepare($sql); $statement->execute($values);
		return new MediaFixtureRows($statement);
	}
}
$db = new MediaFixtureDB();
$db->pdo->exec('CREATE TABLE ca_collections (collection_id INTEGER, hier_collection_id INTEGER, deleted INTEGER);
CREATE TABLE ca_objects (object_id INTEGER, access INTEGER, deleted INTEGER);
CREATE TABLE ca_objects_x_collections (object_id INTEGER, collection_id INTEGER);
CREATE TABLE ca_object_representations (representation_id INTEGER, access INTEGER, deleted INTEGER);
CREATE TABLE ca_objects_x_object_representations (object_id INTEGER, representation_id INTEGER);
INSERT INTO ca_collections VALUES (10,10,0),(11,10,0),(20,20,0),(12,10,1);
INSERT INTO ca_objects VALUES (100,1,0),(101,0,0),(102,1,1),(103,1,0),(104,1,0),(105,1,0),(106,1,0);
INSERT INTO ca_objects_x_collections VALUES (100,11),(101,11),(102,11),(103,20),(104,11),(105,11),(106,12);
INSERT INTO ca_object_representations VALUES (1000,1,0),(1001,1,0),(1002,1,0),(1003,1,0),(1004,0,0),(1005,1,1),(1006,1,0);
INSERT INTO ca_objects_x_object_representations VALUES (100,1000),(101,1001),(102,1002),(103,1003),(104,1004),(105,1005),(106,1006);');
mediaCheck(kclMediaAllowlistCandidates($db, 10) === [['object_id'=>100, 'representation_id'=>1000]],
	'Only the actual hierarchy and public, nondeleted object/representation pair may qualify.');
mediaReject(static fn() => kclMediaAllowlistCandidates($db, 0), 'Missing roots must not become unrestricted membership queries.');

$directory = sys_get_temp_dir().'/kcl-media-test-'.bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) { throw new RuntimeException('Cannot create private fixture directory.'); }
$output = $directory.'/media.map';
try {
	kclMediaAllowlistWrite($output, $paths, $prefix);
	clearstatcache(true, $output);
	$first = file_get_contents($output); $inode = fileinode($output);
	mediaCheck((fileperms($output) & 0777) === 0640, 'Published map must be mode0640.');
	mediaCheck(str_contains($first, 'media/ca_kcl/original/1/AAA_issue.pdf 1'."\n"), 'Map must use exact relative paths and fixed value1.');
	kclMediaAllowlistWrite($output, ['media/ca_kcl/preview/1/NEW_issue.jpg'], $prefix);
	clearstatcache(true, $output);
	mediaCheck(fileinode($output) !== $inode && !str_contains(file_get_contents($output), 'AAA_issue.pdf'), 'Publication must replace the complete file atomically rather than append or truncate it in place.');
	$before = file_get_contents($output);
	mediaReject(static fn() => kclMediaAllowlistWrite($output, ["media/ca_kcl/file.jpg\nother 1"], $prefix), 'Invalid map keys must never publish directives.');
	mediaCheck(file_get_contents($output) === $before, 'Invalid output must leave the previously complete map intact.');
	$process = proc_open([PHP_BINARY, dirname(__DIR__).'/support/generate_media_allowlist.php',
		'--app-root='.$directory, '--output='.$output], [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
	if (!is_resource($process)) { throw new RuntimeException('Cannot launch isolated generator failure check.'); }
	$stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	mediaCheck(proc_close($process) === 1 && $stdout === '' && str_contains($stderr, 'deny-all map installed.'),
		'CLI bootstrap failure must return failure and replace old grants with a deny-all map.');
	mediaCheck(!preg_match('/^[^#].* 1$/m', file_get_contents($output)), 'Failed generation must not leave stale public grants.');
	$process = proc_open([PHP_BINARY, dirname(__DIR__).'/support/generate_media_allowlist.php',
		'--app-root='.$directory, '--output='.$output, '--diagnostics'], [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
	if (!is_resource($process)) { throw new RuntimeException('Cannot launch isolated safe diagnostic check.'); }
	stream_get_contents($pipes[1]); $diagnostic = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	mediaCheck(proc_close($process) === 1 && str_contains($diagnostic, 'RuntimeException at generate_media_allowlist.php:')
		&& !str_contains($diagnostic, $directory) && !str_contains($diagnostic, 'Invalid application'),
		'Diagnostics must show source locations without paths, exception messages or native values.');
	kclMediaAllowlistWrite($output, [], $prefix);
	mediaCheck(!preg_match('/^[^#].* 1$/m', file_get_contents($output)), 'An empty map must deny every media path.');
} finally {
	if (is_file($output)) { unlink($output); }
	rmdir($directory);
}
echo 'Media allowlist passed: '.$checks." checks (synthetic SQL, paths, page derivatives, exclusions and atomic publication).\n";
