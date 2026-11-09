<?php
$is_home = ($this->request->getController() === 'Front');
$home_url = caNavUrl($this->request, '', 'Front', 'Index');
$search_url = caNavUrl($this->request, '', 'Search', 'objects');
$theme_url = $this->request->getThemeUrlPath();
$page_title = MetaTagManager::getWindowTitle() ?: 'Kalkaska County Library Newspaper Archive';
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
	<link rel="icon" type="image/png" href="<?= htmlspecialchars($theme_url.'/assets/pawtucket/graphics/favicon.png', ENT_QUOTES, 'UTF-8'); ?>">
	<?= MetaTagManager::getHTML(); ?>
	<?= AssetLoadManager::getLoadHTML($this->request); ?>
</head>
<body class="kcl-theme<?= $is_home ? ' kcl-home' : ''; ?>">
	<a class="kcl-skip-link" href="#main">Skip to main content</a>
	<div class="kcl-utility">
		<div class="container kcl-shell">
			<span>Kalkaska County Library</span>
			<a href="https://www.kalkaskalibrary.org/">Visit the library website <span aria-hidden="true">↗</span></a>
		</div>
	</div>
	<header class="kcl-header">
		<div class="container kcl-shell kcl-masthead">
			<a class="kcl-brand" href="<?= htmlspecialchars($home_url, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Kalkaska County Library newspaper archive home">
				<img src="<?= htmlspecialchars($theme_url.'/assets/pawtucket/graphics/kcl-logo.png', ENT_QUOTES, 'UTF-8'); ?>" width="300" height="77" alt="Kalkaska County Library">
			</a>
			<span class="kcl-site-name">Digital newspaper archive <span>1879–2012</span></span>
		</div>
	</header>
	<nav class="kcl-nav" aria-label="Primary navigation">
		<div class="container kcl-shell">
			<a href="<?= htmlspecialchars($home_url, ENT_QUOTES, 'UTF-8'); ?>"<?= $is_home ? ' aria-current="page"' : ''; ?>>Archive home</a>
			<a href="<?= htmlspecialchars($home_url.'#decades', ENT_QUOTES, 'UTF-8'); ?>">Browse by decade</a>
			<a href="<?= htmlspecialchars($home_url.'#about-archive', ENT_QUOTES, 'UTF-8'); ?>">About the archive</a>
		</div>
	</nav>
<?php if (!$is_home) { ?>
	<div class="kcl-interior-search">
		<form class="container kcl-shell kcl-search-form" role="search" method="get" action="<?= htmlspecialchars($search_url, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Search the newspaper archive">
			<input type="hidden" name="sort" value="Relevance">
			<label class="sr-only" for="kcl-header-search">Search newspapers</label>
			<input id="kcl-header-search" type="search" name="search" placeholder="Search newspapers…" required>
			<button type="submit">Search</button>
		</form>
	</div>
<?php } ?>
	<div class="container kcl-shell kcl-content">
		<div class="row"><div class="col-xs-12">
			<main id="main" tabindex="-1">
				<div id="pageArea" <?= caGetPageCSSClasses(); ?>>
