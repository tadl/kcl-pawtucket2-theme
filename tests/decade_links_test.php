<?php
/** Synthetic rendering check: no application, search index or database. */
function caNavUrl($request, $module, $controller, $action, $params = []): string {
	return '/'.$controller.'/'.$action.($params ? '?'.http_build_query($params) : '');
}
class KclDecadeFixtureView {
	public $request;
	public function __construct($mapping) {
		$this->request = (object)['config' => new class($mapping) {
			private $mapping;
			public function __construct($mapping) { $this->mapping = $mapping; }
			public function get($key) { return $key === 'kcl_issue_date_element' ? $this->mapping : null; }
		}];
	}
	public function render($path): string { return ''; }
	public function homepage(): string {
		ob_start();
		include dirname(__DIR__).'/views/Front/front_page_html.php';
		return ob_get_clean();
	}
}
function decadeCheck(bool $condition, string $message): void {
	if (!$condition) { throw new RuntimeException($message); }
}
function decadeLinks($mapping): array {
	$html = (new KclDecadeFixtureView($mapping))->homepage();
	$document = new DOMDocument();
	@$document->loadHTML('<?xml encoding="UTF-8">'.$html);
	$links = (new DOMXPath($document))->query('//ul[contains(@class,"kcl-decade-list")]/li/a');
	decadeCheck($links->length === 15, 'All 15 archive decades must remain available.');
	$output = [];
	foreach ($links as $link) {
		$url = parse_url($link->getAttribute('href'));
		decadeCheck(($url['path'] ?? '') === '/Search/objects', 'Decade links must use the native object search.');
		parse_str($url['query'] ?? '', $params);
		decadeCheck(($params['sort'] ?? '') === 'Date' && ($params['direction'] ?? '') === 'asc', 'Decades must open chronologically.');
		$output[] = $params['search'] ?? '';
	}
	return $output;
}
$queries = decadeLinks('date.dates_value');
foreach (range(1870, 2010, 10) as $index => $decade) {
	decadeCheck($queries[$index] === 'ca_objects.date.dates_value:'.substr((string)$decade, 0, 3).'*', 'A decade must search only the configured issue-date field.');
}
$custom = decadeLinks('publication_date');
decadeCheck($custom[0] === 'ca_objects.publication_date:187*', 'Single-field profile mappings must be supported.');
foreach (['', null, 'date.dates_value OR *', 'date"><script>alert(1)</script>'] as $invalid) {
	decadeCheck(array_unique(decadeLinks($invalid)) === ['ca_objects.object_id:0'], 'Invalid mappings must fail closed.');
}
fwrite(STDOUT, "Decade links passed: native field searches, configured dates, chronology and invalid mappings.\n");
