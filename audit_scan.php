<?php

$dirs = ['app', 'routes', 'config', 'resources/views/admin'];
$results = [];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        $results[$dir] = 'Directory not found';
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    $fileList = [];
    foreach ($it as $file) {
        if ($file->isFile()) {
            $fileList[] = str_replace('\\', '/', $file->getPathname());
        }
    }
    sort($fileList);
    $results[$dir] = $fileList;
}

foreach ($results as $dir => $files) {
    echo "Directory: $dir (" . count($files) . " files)\n";
}

file_put_contents('audit_file_list.json', json_encode($results, JSON_PRETTY_PRINT));
echo "Saved file list to audit_file_list.json\n";
