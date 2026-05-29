<?php

// Holds a MySQL advisory lock for the duration of doctrine:schema:update,
// so only one replica applies schema changes at a time. Other replicas
// block on GET_LOCK, then run schema:update against the already-updated
// schema (a no-op).

$url = getenv('DATABASE_URL');
if (!$url) {
    fwrite(STDERR, "DATABASE_URL is not set\n");
    exit(1);
}

$p = parse_url($url);
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s',
    $p['host'],
    $p['port'] ?? 3306,
    ltrim($p['path'] ?? '', '/')
);

$pdo = new PDO($dsn, $p['user'] ?? null, $p['pass'] ?? null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$lockName = 'carmeets_migrate';
$timeout = 120;

$got = $pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockName) . ", $timeout)")->fetchColumn();
if ($got !== '1' && $got !== 1) {
    fwrite(STDERR, "Could not acquire migration lock '$lockName' within {$timeout}s\n");
    exit(1);
}
fwrite(STDOUT, "Acquired migration lock '$lockName'\n");

$status = 0;
passthru('php bin/console doctrine:schema:update --force --no-interaction', $status);

$pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")");
fwrite(STDOUT, "Released migration lock '$lockName'\n");

exit($status);
