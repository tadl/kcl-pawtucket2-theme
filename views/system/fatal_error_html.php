<?php
require_once dirname(__DIR__, 2).'/helpers/public_errors.php';
$error_request = class_exists('AppController', false) && AppController::instanceExists()
	? AppController::getInstance()->getRequest() : null;
$existing_status = $error_request ? AppController::getInstance()->getResponse()->getHTTPResponseCode() : (http_response_code() ?: 200);
$status = tadlPublicErrorStatus($e ?? null, $g_errored ?? false, $existing_status);
// Native 2.0.10 throws before the theme guard when an object does not exist.
if ($error_request && $error_request->getController() === 'Detail'
	&& isset($e) && get_class($e) === 'ApplicationException' && $e->getMessage() === 'Invalid id') {
	$status = 404;
}
tadlPublicErrorHeaders($status);
$title = $status === 404 ? 'Page not found' : ($status === 410 ? 'Page unavailable' : 'Archive temporarily unavailable');
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$home = $error_request ? caNavUrl($error_request, '', 'Front', 'Index') : '/';
$browse = $error_request ? caNavUrl($error_request, '', 'Browse', 'objects') : '/Browse/objects';
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $escape($title); ?> | Kalkaska County Library</title>
</head>
<body>
	<main>
		<p>Kalkaska County Library · Newspaper archive</p>
		<h1><?= $escape($title); ?></h1>
		<p><?= $status === 404 ? 'This page is unavailable or could not be found.' : 'Please return to the archive and try again shortly.'; ?></p>
		<p><a href="<?= $escape($home); ?>">Archive home</a> · <a href="<?= $escape($browse); ?>">Browse all newspapers</a></p>
	</main>
</body>
</html>
