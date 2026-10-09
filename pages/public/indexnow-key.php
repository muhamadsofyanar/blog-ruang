<?php
// File verifikasi IndexNow: {APP_URL}/{key}.txt yang isinya key itu sendiri.
// Mesin pencari mengambilnya untuk membuktikan kepemilikan domain. Hanya cocok
// bila {key} di URL == key tersimpan; selain itu 404 (tidak membocorkan apa pun).
require_once __DIR__ . '/../../helpers/indexnow.php';

$req = (string) ($_GET['key'] ?? '');
$key = indexnowKey();
if ($key === '' || !hash_equals($key, $req)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo $key;
