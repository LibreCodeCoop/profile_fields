<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__, 3);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = $rootDir . $uri;

if ($uri !== '/' && file_exists($file) && !is_dir($file) && pathinfo($uri, PATHINFO_EXTENSION) !== 'php') {
	return false;
}

$dispatch = static function (string $script, string $requestUri) use ($rootDir): void {
	$_SERVER['SCRIPT_NAME'] = $script;
	$_SERVER['SCRIPT_FILENAME'] = $rootDir . $script;
	$_SERVER['PHP_SELF'] = $script;
	$_SERVER['PATH_INFO'] = substr($requestUri, strlen($script)) ?: '';
	require $rootDir . $script;
};

if (str_starts_with($uri, '/ocs/')) {
	$dispatch('/ocs/v2.php', $uri);
} elseif (str_starts_with($uri, '/remote.php')) {
	$dispatch('/remote.php', $uri);
} elseif (str_starts_with($uri, '/public.php')) {
	$dispatch('/public.php', $uri);
} elseif (str_starts_with($uri, '/status.php')) {
	$dispatch('/status.php', $uri);
} else {
	$dispatch('/index.php', $uri);
}
