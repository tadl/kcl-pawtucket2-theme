<?php
/**
 * Newspaper issue detail, using Pawtucket's native viewer and navigation.
 * Adapted from CollectiveAccess's default object detail template.
 * Copyright 2013–2022 Whirl-i-Gig; GNU General Public License version 3.
 * See https://www.gnu.org/licenses/gpl-3.0.html.
 */
$object = $this->getVar('item');
$access_values = caGetUserAccessValues($this->request);
$escape = static function ($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$read_text = function ($bundle) use ($object, $access_values) {
	// Container permissions cover the complete native attribute bundle.
	$bundle_root = explode('.', $bundle)[0];
	if (!$object || !$object->isReadable($this->request, $bundle_root)) { return ''; }
	return trim((string)$object->get('ca_objects.'.$bundle, [
		'checkAccess' => $access_values,
		'stripTags' => true,
		'convertLineBreaks' => false,
		'delimiter' => "\n"
	]));
};

$title = $read_text('preferred_labels.name');
$identifier = $read_text('idno');
$description = $read_text('description');
$date_element = trim((string)$this->request->config->get('kcl_issue_date_element'));
// An invalid profile mapping must not become an unrelated metadata lookup.
$issue_date = preg_match('/\A[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)*\z/', $date_element)
	? $read_text($date_element) : '';
$thumbnails = caObjectRepresentationThumbnails($this->request, $this->getVar('representation_id'), $object, [
	'returnAs' => 'array',
	'linkTo' => 'basic',
	'currentRepClass' => 'active',
	'dontShowCurrentRep' => false,
	'primaryOnly' => $this->getVar('representationViewerPrimaryOnly') ? 1 : 0
]);
?>
<article class="kcl-issue">
	<nav class="kcl-issue-nav" aria-label="Issue navigation">
		{{{previousLink}}}{{{resultsLink}}}{{{nextLink}}}
	</nav>
	<header class="kcl-issue-header">
		<p class="kcl-eyebrow">From the newspaper archive</p>
		<h1><?= $escape($title ?: 'Newspaper issue'); ?></h1>
<?php if ($issue_date !== '' || $identifier !== '') { ?>
		<dl class="kcl-issue-metadata">
<?php if ($issue_date !== '') { ?>
			<div><dt>Issue date</dt><dd><?= nl2br($escape($issue_date)); ?></dd></div>
<?php } ?>
<?php if ($identifier !== '') { ?>
			<div><dt>Identifier</dt><dd><?= $escape($identifier); ?></dd></div>
<?php } ?>
		</dl>
<?php } ?>
	</header>
	<div class="kcl-issue-viewer">
		{{{representationViewer}}}
	</div>
<?php if ($thumbnails) { ?>
	<div class="kcl-issue-thumbnails" role="group" aria-label="Select an attached document">
		<ul id="detailRepresentationThumbnails">
<?php foreach ($thumbnails as $representation_id => $thumbnail) { ?>
			<li id="detailRepresentationThumbnail<?= (int)$representation_id; ?>"><?= $thumbnail; ?></li>
<?php } ?>
		</ul>
	</div>
<?php } ?>
<?php if ($description !== '') { ?>
	<section class="kcl-issue-description" aria-labelledby="kcl-issue-description-title">
		<h2 id="kcl-issue-description-title">About this issue</h2>
		<p><?= nl2br($escape($description)); ?></p>
	</section>
<?php } ?>
</article>
