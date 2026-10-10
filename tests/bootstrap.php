<?php

$packageRoot = realpath(__DIR__.'/..');

$storageDir = $packageRoot.'/storage/framework/views';
$cacheDir = $packageRoot.'/bootstrap/cache';

foreach ([$storageDir, $cacheDir] as $dir) {
    if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
        throw new RuntimeException(sprintf('Unable to create directory: %s', $dir));
    }
}

foreach (['TMPDIR', 'TEMP', 'TMP'] as $key) {
    putenv(sprintf('%s=%s', $key, $packageRoot.'/.tmp'));
}

if (! is_dir($packageRoot.'/.tmp') && ! mkdir($packageRoot.'/.tmp', 0777, true) && ! is_dir($packageRoot.'/.tmp')) {
    throw new RuntimeException(sprintf('Unable to create temporary directory: %s', $packageRoot.'/.tmp'));
}

putenv(sprintf('VIEW_COMPILED_PATH=%s', $storageDir));

require __DIR__.'/../vendor/autoload.php';
