<?php

require 'src/Warmup.php';

function error2exception($errno, $errstr) {
  throw new \Exception($errstr, $errno);
}

set_error_handler('error2exception');
ini_set('user_agent','Mozilla/4.0 (compatible; MSIE 6.0)');

$dbFile = 'warmup.sqlite';

if (!file_exists($dbFile)) {
    $db = new \SQLite3($dbFile, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    $db->exec("CREATE TABLE `urls` (
        `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
        `url` TEXT NOT NULL UNIQUE,
        `status` INTEGER NOT NULL DEFAULT 0
    )");

    echo "Database created", PHP_EOL;
}

$db = new \PDO('sqlite:' . $dbFile);
$warmup = new \Kassner\WebsiteWarmup\Warmup('www.example.com', $db);
$warmup->addUrl('http://www.example.com/');
$warmup->run();
