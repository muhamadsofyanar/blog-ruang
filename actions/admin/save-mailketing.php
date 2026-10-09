<?php
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) { flash('error', 'Permintaan tidak valid.', 'error'); redirect('/admin/integrations'); }

$token = trim((string) ($_POST['mailketing_api_token'] ?? ''));
$senderEmail = trim((string) ($_POST['mailketing_sender_email'] ?? ''));
$senderName = mb_substr(trim((string) ($_POST['mailketing_sender_name'] ?? '')), 0, 120);
$listId = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string) ($_POST['mailketing_subscriber_list_id'] ?? '')));
$endpoint = trim((string) ($_POST['mailketing_endpoint'] ?? 'https://api.mailketing.co.id/api/v1/send'));

if ($senderEmail !== '' && !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) { flash('error', 'Email pengirim Mailketing tidak valid.', 'error'); redirect('/admin/integrations'); }
if ($endpoint !== '' && !preg_match('#^https://#i', $endpoint)) { flash('error', 'Endpoint Mailketing harus HTTPS.', 'error'); redirect('/admin/integrations'); }
if ($token !== '') setSetting('mailketing_api_token', mb_substr($token, 0, 500), 'integrations');
setSetting('mailketing_sender_email', mb_substr($senderEmail, 0, 190), 'integrations');
setSetting('mailketing_sender_name', $senderName, 'integrations');
setSetting('mailketing_subscriber_list_id', mb_substr($listId, 0, 40), 'integrations');
setSetting('mailketing_endpoint', $endpoint !== '' ? $endpoint : 'https://api.mailketing.co.id/api/v1/send', 'integrations');
flash('success', 'Integrasi Mailketing disimpan.', 'success');
redirect('/admin/integrations');
