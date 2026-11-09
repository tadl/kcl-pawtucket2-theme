<?php
$search_url = caNavUrl($this->request, '', 'Search', 'objects');
$browse_url = caNavUrl($this->request, '', 'Browse', 'objects', ['clear' => 1]);
?>
<section class="kcl-intro" aria-labelledby="archive-title">
	<p class="kcl-eyebrow">Kalkaska County, Michigan · <span class="kcl-date-range">1879–2012</span></p>
	<h1 id="archive-title">Newspaper archive</h1>
	<p class="kcl-welcome">Welcome to Kalkaska County Library's digital newspaper archive, featuring issues of <em>The Leader and Kalkaskian</em> and predecessor newspapers, from 1879 to 2012.</p>
</section>

<section class="kcl-search-panel" aria-labelledby="search-title">
	<h2 id="search-title">Search the newspapers</h2>
	<form class="kcl-search-form" role="search" method="get" action="<?= htmlspecialchars($search_url, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Search the newspaper archive">
		<input type="hidden" name="sort" value="Relevance">
		<label class="sr-only" for="kcl-archive-search">Search by name, place, or keyword</label>
		<input id="kcl-archive-search" type="search" name="search" placeholder="Enter a name, place, or keyword…" aria-describedby="kcl-search-help" required>
		<button type="submit">Search newspapers <span aria-hidden="true">→</span></button>
	</form>
	<p id="kcl-search-help">Search across the archive, or <a href="<?= htmlspecialchars($browse_url, ENT_QUOTES, 'UTF-8'); ?>">browse all newspaper issues</a>.</p>
</section>

<section id="decades" class="kcl-decades" aria-labelledby="decades-title">
	<div class="kcl-section-heading">
		<h2 id="decades-title">Browse by decade</h2>
		<span>Find an issue by date</span>
	</div>
	<ul class="kcl-decade-list">
<?php for ($decade = 1870; $decade <= 2010; $decade += 10) {
	$decade_url = caNavUrl($this->request, '', 'Browse', 'objects', [
		'clear' => 1, 'facet' => 'decade_facet', 'id' => $decade.'s'
	]);
?>
		<li><a href="<?= htmlspecialchars($decade_url, ENT_QUOTES, 'UTF-8'); ?>"><span class="sr-only">Browse newspapers from the </span><?= $decade; ?>s<?php if ($decade === 1870) { ?><small>1879</small><?php } elseif ($decade === 2010) { ?><small>2010–2012</small><?php } ?></a></li>
<?php } ?>
	</ul>
	<p class="kcl-coverage-note">The archive spans 1879–2012. Availability varies by year.</p>
</section>

<section id="about-archive" class="kcl-about" aria-labelledby="about-title">
	<h2 id="about-title">About the archive</h2>
	<?= $this->render('About/archive_text_html.php'); ?>
</section>
