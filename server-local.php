<?php

$publicDirectory = __DIR__ . '/public';
$requestPath = rawurldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
);
$publicRoot = realpath($publicDirectory);
$requestedFile = realpath(
    $publicDirectory . DIRECTORY_SEPARATOR . ltrim($requestPath, '/\\')
);

// The Laravel public storage link resolves outside public/. Allow only files
// whose real path remains inside storage/app/public, never arbitrary symlinks.
$storagePublicRoot = realpath(__DIR__ . '/storage/app/public');
$isPublicStorageFile = false;
if ($storagePublicRoot && str_starts_with(ltrim($requestPath, '/\\'), 'storage/')) {
    $storageRelativePath = substr(ltrim($requestPath, '/\\'), strlen('storage/'));
    $storageRequestedFile = realpath(
        $storagePublicRoot . DIRECTORY_SEPARATOR . $storageRelativePath
    );
    $isPublicStorageFile = $storageRequestedFile
        && str_starts_with($storageRequestedFile, $storagePublicRoot . DIRECTORY_SEPARATOR)
        && is_file($storageRequestedFile);
}

$isRegularPublicFile = $requestedFile
    && $publicRoot
    && str_starts_with($requestedFile, $publicRoot . DIRECTORY_SEPARATOR)
    && is_file($requestedFile);

if ($requestPath !== '/' && ($isRegularPublicFile || $isPublicStorageFile)) {
    return false;
}

require $publicDirectory . DIRECTORY_SEPARATOR . 'index.php';
