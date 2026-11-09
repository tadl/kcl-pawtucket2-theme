<?php
/**
 * Newspaper issue detail, using Pawtucket's native viewer and navigation.
 * Adapted from CollectiveAccess's default object detail template.
 * Copyright 2013–2022 Whirl-i-Gig; GNU General Public License version 3.
 * See https://www.gnu.org/licenses/gpl-3.0.html.
 */
if (!function_exists('tadlDetailNavigation')) {
	require_once dirname(__DIR__, 3).'/tadl/views/Details/detail_field_helpers.php';
}
if (!function_exists('tadlObjectDocumentTextDisclosure')) {
	require_once dirname(__DIR__, 2).'/helpers/document_text_disclosure.php';
}
require_once dirname(__DIR__, 2).'/helpers/media_names.php';
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
<article class="kcl-issue tadl-object-detail" aria-labelledby="kcl-issue-title">
	<div class="kcl-issue-layout">
		<div class="kcl-issue-media tadl-object-media">
			<div class="kcl-issue-viewer">
				{{{representationViewer}}}
			</div>
<?php if ($thumbnails) { ?>
			<div class="kcl-issue-thumbnails" role="group" aria-label="Select an attached document">
				<ul id="detailRepresentationThumbnails">
<?php $document_position = 0; foreach ($thumbnails as $representation_id => $thumbnail) { $document_position++; ?>
					<li id="detailRepresentationThumbnail<?= (int)$representation_id; ?>"><?= tadlMediaImageNames($thumbnail, _t('Select document %1', $document_position)); ?></li>
<?php } ?>
				</ul>
			</div>
<?php } ?>
		</div>
		<div class="kcl-issue-info tadl-object-info">
			<?= tadlDetailNavigation($this, 'objects', _t('Browse newspapers')); ?>
			<header class="kcl-issue-header">
				<h1 id="kcl-issue-title"><?= $escape($title ?: 'Newspaper issue'); ?></h1>
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
			<div id="tadlObjectMediaActions" class="tadl-object-media-actions kcl-issue-media-actions" role="group" aria-label="Issue media actions"></div>
<?php if ($description !== '') { ?>
			<section class="kcl-issue-description" aria-labelledby="kcl-issue-description-title">
				<h2 id="kcl-issue-description-title">About this issue</h2>
				<p><?= nl2br($escape($description)); ?></p>
			</section>
<?php } ?>
		</div>
	</div>
	<?= tadlObjectDocumentTextDisclosure($this->request, $object); ?>
</article>
<?= $this->render('Details/image_actions_script.php'); ?>
