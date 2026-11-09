<?php
/** Run the standalone shell with synthetic transfer tools; no server access. */
error_reporting(E_ALL);
$checks = 0;
function kclDeployCheck(bool $condition, string $message): void {
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}
$fixture = sys_get_temp_dir().'/kcl-deploy-'.bin2hex(random_bytes(8));
$checkout = $fixture.'/Isolated KCL checkout with spaces';
foreach (['/support', '/conf', '/views'] as $directory) { mkdir($checkout.$directory, 0700, true); }
mkdir($fixture.'/bin', 0700);
mkdir($fixture.'/elsewhere', 0700);
copy(dirname(__DIR__).'/support/deploy-theme.sh', $checkout.'/support/deploy-theme.sh');
file_put_contents($checkout.'/conf/app.conf', '# Synthetic KCL configuration');
$mock = <<<'PHP'
#!/usr/bin/env php
<?php
$tool = basename($argv[0]);
file_put_contents(getenv('KCL_DEPLOY_TEST_TRACE'), json_encode([$tool, array_slice($argv, 1)]).PHP_EOL, FILE_APPEND);
exit($tool === 'rsync' ? (int)getenv('KCL_DEPLOY_TEST_EXIT') : 91);
PHP;
foreach (['rsync', 'ssh', 'apache2ctl', 'systemctl'] as $tool) {
	file_put_contents($fixture.'/bin/'.$tool, $mock);
	chmod($fixture.'/bin/'.$tool, 0700);
}
function kclRunDeploy(array $arguments, int $transferExit = 0, ?string $script = null): array {
	global $fixture, $checkout;
	$trace = $fixture.'/trace.jsonl';
	file_put_contents($trace, '');
	$environment = array_replace(getenv(), [
		'PATH' => $fixture.'/bin:'.getenv('PATH'),
		'KCL_DEPLOY_TEST_TRACE' => $trace,
		'KCL_DEPLOY_TEST_EXIT' => (string)$transferExit,
	]);
	$process = proc_open(array_merge(['bash', $script ?? $checkout.'/support/deploy-theme.sh'], $arguments),
		[0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $fixture.'/elsewhere', $environment);
	if (!is_resource($process)) { throw new RuntimeException('Could not start synthetic deployment.'); }
	$output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	$status = proc_close($process);
	$calls = array_map(static fn($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), file($trace, FILE_IGNORE_NEW_LINES));
	return [$status, $output, $calls];
}
try {
	[$status, $output, $calls] = kclRunDeploy(['root@catalog.example.org']);
	kclDeployCheck($status === 0 && str_contains($output, 'Deployment complete'), 'A successful standalone transfer must complete.');
	kclDeployCheck(array_column($calls, 0) === ['rsync'], 'KCL deployment must not call SSH, services or any sibling theme.');
	kclDeployCheck(array_slice($calls[0][1], -2) === [$checkout.'/', 'root@catalog.example.org:/var/www/kcl/themes/kcl/'], 'The isolated checkout must deploy only itself, preserving spaces.');
	kclDeployCheck(array_slice($calls[0][1], 0, -2) === ['--archive', '--verbose', '--itemize-changes', '--exclude=.git/', '--exclude=.gitignore', '--exclude=README.md', '--exclude=.DS_Store'], 'Preserve the established archive/exclusion policy without deletion.');
	[$status, $output, $calls] = kclRunDeploy(['--dry-run', 'root@catalog.example.org']);
	kclDeployCheck($status === 0 && count($calls) === 1 && in_array('--dry-run', $calls[0][1], true) && str_contains($output, 'Dry run complete'), 'Dry run must use native rsync preview.');
	[$status, $output, $calls] = kclRunDeploy(['root@catalog.example.org'], 23);
	kclDeployCheck($status === 23 && count($calls) === 1 && !str_contains($output, 'Deployment complete'), 'Transfer failure must preserve its exit status and omit success.');
	foreach ([[], ['-oProxyCommand=unsafe'], ['root@catalog.example.org;unsafe'], ['root@catalog.example.org', 'tadl'], ['--dry-run']] as $arguments) {
		[$status, $output, $calls] = kclRunDeploy($arguments);
		kclDeployCheck($status !== 0 && !$calls && str_contains($output, 'Usage:'), 'Invalid or extra arguments must be rejected before transfer.');
	}
	[$status, $output, $calls] = kclRunDeploy(['--help']);
	kclDeployCheck($status === 0 && !$calls && str_contains($output, 'Usage:'), 'Help must not transfer files.');
	// The optional workstation entrypoint must also work without a TADL checkout.
	$workspaceWrapper = dirname(__DIR__, 2).'/deploy';
	if (is_file($workspaceWrapper)) {
		mkdir($fixture.'/workspace/kcl/support', 0700, true);
		mkdir($fixture.'/workspace/kcl/conf', 0700);
		mkdir($fixture.'/workspace/kcl/views', 0700);
		copy($workspaceWrapper, $fixture.'/workspace/deploy');
		copy($checkout.'/support/deploy-theme.sh', $fixture.'/workspace/kcl/support/deploy-theme.sh');
		file_put_contents($fixture.'/workspace/kcl/conf/app.conf', '# Synthetic KCL configuration');
		[$status, $output, $calls] = kclRunDeploy(['kcl'], 0, $fixture.'/workspace/deploy');
		$transfer = array_slice($calls[0][1] ?? [], -2);
		kclDeployCheck($status === 0 && count($calls) === 1 && ($transfer[0] ?? '') === $fixture.'/workspace/kcl/' && str_ends_with($transfer[1] ?? '', ':/var/www/kcl/themes/kcl/'), 'The workspace KCL selector must not require a TADL checkout.');
		[$status, $output, $calls] = kclRunDeploy(['--dry-run', 'kcl'], 0, $fixture.'/workspace/deploy');
		kclDeployCheck($status === 0 && count($calls) === 1 && in_array('--dry-run', $calls[0][1], true), 'The workspace KCL preview must preserve the standalone dry-run option.');
		[$status, $output, $calls] = kclRunDeploy(['kcl', 'tadl'], 0, $fixture.'/workspace/deploy');
		kclDeployCheck($status !== 0 && !$calls, 'The workstation wrapper must reject extra selectors before transfer.');
	}
	unlink($checkout.'/conf/app.conf');
	[$status, $output, $calls] = kclRunDeploy(['root@catalog.example.org']);
	kclDeployCheck($status !== 0 && !$calls && str_contains($output, 'incomplete'), 'Missing source files must prevent deployment.');
	fwrite(STDOUT, 'KCL deployment passed: '.$checks." assertions (isolated checkout; synthetic commands only).\n");
} finally {
	$entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($entries as $entry) {
		if ($entry->isDir() && !$entry->isLink()) { rmdir($entry->getPathname()); }
		else { unlink($entry->getPathname()); }
	}
	rmdir($fixture);
}
