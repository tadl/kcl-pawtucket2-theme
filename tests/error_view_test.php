<?php
class ApplicationException extends Exception {}
class KclErrorRequest { public function getController() { return 'Detail'; } }
class KclErrorResponse { public function getHTTPResponseCode() { return 200; } }
class AppController {
	public static function instanceExists() { return true; }
	public static function getInstance() { return new self(); }
	public function getRequest() { return new KclErrorRequest(); }
	public function getResponse() { return new KclErrorResponse(); }
}
function caNavUrl($request, $module, $controller, $action) { return '/'.$controller.'/'.$action; }
require_once dirname(__DIR__).'/helpers/public_errors.php';
foreach ([
	[new TadlPublicNotFoundException('private synthetic detail'), 404],
	[new ApplicationException('Invalid id'), 404],
	[new RuntimeException('synthetic internal connection failure'), 500],
	[new TadlResultExportDisabledException('synthetic export'), 410]
] as [$e, $expected]) {
	ob_start();
	include dirname(__DIR__).'/views/system/fatal_error_html.php';
	$html = ob_get_clean();
	if (http_response_code() !== $expected || !str_contains($html, 'Kalkaska County Library')
		|| str_contains($html, $e->getMessage()) || str_contains($html, 'Stack trace')) {
		throw new RuntimeException('Public error response failed.');
	}
}
echo "KCL error views passed: missing/private404, internal500, retired410, no exception disclosure.\n";
