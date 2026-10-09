<?php
// POST /actions/admin/email-preview — render HTML email step untuk PRATINJAU.
// Reuse scribeEmailSequenceBody() (sumber kebenaran pembungkus) + isi placeholder
// dengan contoh. Output HTML mentah; ditampilkan klien di IFRAME ber-SANDBOX
// (skrip tak dieksekusi), jadi aman tanpa sanitasi yang merusak tampilan email.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/email-sequence.php';
requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p style="font-family:sans-serif;color:#b91c1c;padding:16px">Token keamanan tidak valid.</p>';
    exit;
}

$body    = (string) ($_POST['body'] ?? '');
$format  = in_array($_POST['body_format'] ?? 'html', ['html', 'text'], true) ? (string) $_POST['body_format'] : 'html';
$subject = (string) ($_POST['subject'] ?? '');

$html = scribeEmailSequenceBody($body, $format, $subject);

// Placeholder → nilai contoh agar pratinjau realistis.
$html = strtr($html, [
    '{{name}}'              => 'Ady',
    '{{email}}'             => 'email@contoh.com',
    '{{blog_name}}'         => blogName(),
    '{{article_title}}'     => 'Judul Artikel Contoh',
    '{{article_url}}'       => url('/artikel/contoh'),
    '{{lead_magnet_title}}' => 'Lead Magnet Contoh',
    '{{lead_magnet_url}}'   => url('/'),
    '{{unsubscribe_url}}'   => '#',
]);

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo $html;
