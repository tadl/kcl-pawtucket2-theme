<?php
/** Render the actual KCL view boundary with synthetic framework/media adapters. */
function _t($text, ...$arguments) {
	foreach ($arguments as $index => $value) { $text = str_replace('%'.($index + 1), (string)$value, $text); }
	return $text;
}
function caNavUrl($request, $module, $controller, $action, $params = []): string {
	return '/'.$controller.'/'.$action.($params ? '?'.http_build_query($params) : '');
}
function caGetPageCSSClasses(): string { return 'class="synthetic-page"'; }
function caGetUserAccessValues($request): array { return [1]; }
function caObjectRepresentationThumbnails($request, $representation, $object, $options): array {
	return [101 => '<a href="#" id="repThumb_0" onclick="return setItem(0);" data-representation_id="101"><img src="/synthetic-1.png" alt="opaque-file-1.pdf"></a>',
		102 => '<a href="#" id="repThumb_1" onclick="return setItem(1);" data-representation_id="102"><img src="/synthetic-2.png" alt="opaque-file-2.pdf"></a>'];
}
function tadlDetailNavigation($view, $action, $label): string {
	$GLOBALS['semantic_navigation'] = [$action, $label];
	return '<nav class="tadl-record-nav" aria-label="Record navigation"><a href="/Browse/objects">Back to results</a><a href="/Detail/objects/100" aria-label="Go to previous object">Previous</a><a href="/Detail/objects/102" aria-label="Go to next object">Next</a></nav>';
}
function tadlObjectDocumentTextDisclosure($request, $object): string {
	$GLOBALS['semantic_document_object'] = $object;
	return '<details class="tadl-document-text"><summary>Document text</summary><div><a href="/DocumentText/View?object_id=101">Open document text</a></div></details>';
}
class MetaTagManager {
	public static function getWindowTitle(): string { return 'Synthetic newspaper archive'; }
	public static function getHTML(): string { return ''; }
}
class AssetLoadManager { public static function getLoadHTML($request): string { return ''; } }
class SemanticObject {
	public $denied = [];
	public function isReadable($request, $bundle): bool { return !in_array($bundle, $this->denied, true); }
	public function get($bundle, $options): string {
		if (($options['checkAccess'] ?? null) !== [1] || empty($options['stripTags'])) {
			throw new RuntimeException('Issue metadata must retain native access/plain-text options.');
		}
		return ['ca_objects.preferred_labels.name' => 'Synthetic newspaper issue', 'ca_objects.idno' => 'SYN-001',
			'ca_objects.date.dates_value' => '1900-01-01', 'ca_objects.description' => 'Synthetic issue description.'][$bundle] ?? '';
	}
}
class SemanticView {
	public $request;
	public $object;
	public function __construct(string $controller) {
		$this->object = new SemanticObject();
		$this->request = new class($controller) {
			private $controller;
			public $config;
			public function __construct($controller) {
				$this->controller = $controller;
				$this->config = new class { public function get($key) { return $key === 'kcl_issue_date_element' ? 'date.dates_value' : null; } };
			}
			public function getController(): string { return $this->controller; }
			public function getThemeUrlPath(): string { return '/themes/kcl'; }
		};
	}
	public function getVar($key) { return ['item'=>$this->object, 'representation_id'=>101, 'representationViewerPrimaryOnly'=>false][$key] ?? null; }
	public function render(string $path): string {
		$views = realpath(dirname(__DIR__).'/views');
		$file = realpath($views.'/'.$path);
		if (!$file || !is_file($file) || !str_starts_with($file, $views.'/')) {
			throw new RuntimeException('A semantic fixture view must resolve inside KCL.');
		}
		ob_start(); include $file; return ob_get_clean();
	}
	public function page(string $path): string {
		return $this->render('pageFormat/pageHeader.php').$this->render($path).'</div></main></div></div></div></body></html>';
	}
}
function semanticCheck(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
foreach ([['Front', 'Front/front_page_html.php'], ['About', 'About/Index.php'], ['Detail', 'Details/ca_objects_default_html.php']] as [$controller, $path]) {
	$view = new SemanticView($controller);
	$html = $view->page($path);
	$document = new DOMDocument();
	$document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$xpath = new DOMXPath($document);
	semanticCheck($document->documentElement->getAttribute('lang') === 'en', 'Page language must be specified.');
	semanticCheck($xpath->query('//main[@id="main" and @tabindex="-1"]')->length === 1, 'Skip navigation needs one focusable main target.');
	semanticCheck($xpath->query('//a[@class="kcl-skip-link" and @href="#main"]')->length === 1, 'Every normal page needs the skip link.');
	semanticCheck($xpath->query('//body/header//div[@class="kcl-utility"]//a')->length === 1, 'The repeated utility link must be inside the banner landmark.');
	semanticCheck($xpath->query('//h1')->length === 1, 'Every normal page must have one primary heading.');
	$level = 0;
	foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6') as $heading) {
		$next = (int)substr($heading->tagName, 1);
		semanticCheck($next <= $level + 1, 'Heading levels must describe the actual nesting.');
		$level = $next;
	}
	foreach ($xpath->query('//input[@type="search"]') as $input) {
		$id = $input->getAttribute('id');
		semanticCheck($id !== '' && $xpath->query('//label[@for="'.$id.'"]')->length === 1, 'Search fields need associated labels.');
	}
	if ($controller === 'Detail') {
		semanticCheck(!str_contains($html, 'From the newspaper archive'), 'Issue metadata must not repeat the removed eyebrow.');
		semanticCheck(($GLOBALS['semantic_navigation'] ?? null) === ['objects', 'Browse newspapers'], 'Issue navigation must reuse the local context-aware helper.');
		semanticCheck(($GLOBALS['semantic_document_object'] ?? null) === $view->object, 'Document text must use the same access-checked issue.');
		semanticCheck($xpath->query('//article//header/h1')->length === 1 && $xpath->query('//dl/div/dt')->length === 2, 'The issue title/date/identifier semantics must remain available.');
		semanticCheck($xpath->query('//article/div[@class="kcl-issue-layout"]/div[contains(@class,"kcl-issue-media")]/div[@class="kcl-issue-viewer"]')->length === 1, 'The native preview must remain in the left media column.');
		semanticCheck($xpath->query('//article/div/div[contains(@class,"kcl-issue-info")]//nav[@class="tadl-record-nav"]')->length === 1, 'Context-aware navigation must be in the right metadata column.');
		semanticCheck($xpath->query('//article/div/div[contains(@class,"kcl-issue-info")]//*[@id="tadlObjectMediaActions" and @role="group" and @aria-label="Issue media actions"]')->length === 1, 'Native media controls need their right-column destination.');
		semanticCheck(str_contains($html, 'function tadlPlaceImageToolbar(reset = false)'), 'The established local toolbar adapter must be rendered unchanged.');
		$summary = $xpath->query('//article/details[@class="tadl-document-text"]/summary')->item(0);
		semanticCheck($summary && $summary->textContent === 'Document text', 'A native keyboard-accessible OCR disclosure must follow issue information.');
		foreach ([101, 102] as $index => $representation) {
			$link = $xpath->query('//*[@id="repThumb_'.$index.'"]')->item(0);
			semanticCheck($link && $link->getAttribute('data-representation_id') === (string)$representation && $link->getAttribute('onclick') === 'return setItem('.$index.');', 'Document thumbnail naming must preserve native callbacks and IDs.');
			semanticCheck($link->getElementsByTagName('img')->item(0)->getAttribute('alt') === 'Select document '.($index + 1), 'Attached-document controls need meaningful function names.');
		}
	}
}
echo "Semantic HTML passed: banner/main/search labels, heading structure, local navigation, named native thumbnails and OCR disclosure.\n";
