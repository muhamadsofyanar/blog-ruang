<?php
// Management API email sequence untuk Hermes/automation.
// POST mengganti metadata + seluruh step secara atomik; body HTML diteruskan
// sebagai HTML email dan tidak pernah menjadi konten artikel publik.
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Gunakan GET atau POST.']);
if ($method === 'POST' && !str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) ingestApiSend(415, ['ok' => false, 'error' => 'Content-Type harus application/json.']);
ingestApiAuthorize($method === 'POST');
$pdo = getDB();

function scribeApiSequenceOutput(PDO $pdo, array $row): array
{
    $st = $pdo->prepare('SELECT id, step_number, delay_days, subject, body, body_format, status FROM email_sequence_steps WHERE sequence_id = ? ORDER BY step_number ASC');
    $st->execute([(int) $row['id']]);
    $row['id'] = (int) $row['id'];
    $row['lead_magnet_id'] = $row['lead_magnet_id'] !== null ? (int) $row['lead_magnet_id'] : null;
    $row['steps'] = array_map(static function (array $s): array { $s['id'] = (int) $s['id']; $s['step_number'] = (int) $s['step_number']; $s['delay_days'] = (int) $s['delay_days']; return $s; }, $st->fetchAll());
    return $row;
}

if ($method === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) { $st = $pdo->prepare('SELECT id,name,trigger_event,lead_magnet_id,provider,status,created_at,updated_at FROM email_sequences WHERE id=? LIMIT 1'); $st->execute([$id]); $row = $st->fetch(); if (!$row) ingestApiSend(404, ['ok' => false, 'error' => 'Email sequence tidak ditemukan.']); ingestApiSend(200, ['ok' => true, 'sequence' => scribeApiSequenceOutput($pdo, $row)]); }
    $rows = $pdo->query('SELECT id,name,trigger_event,lead_magnet_id,provider,status,created_at,updated_at FROM email_sequences ORDER BY updated_at DESC, id DESC')->fetchAll();
    ingestApiSend(200, ['ok' => true, 'sequences' => array_map(fn($row) => scribeApiSequenceOutput($pdo, $row), $rows)]);
}

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) ingestApiSend(400, ['ok' => false, 'error' => 'Body JSON tidak valid.']);
$allowed = ['id', 'name', 'lead_magnet_id', 'status', 'steps', 'dry_run'];
$unknown = array_values(array_diff(array_keys($in), $allowed));
if ($unknown) ingestApiSend(400, ['ok' => false, 'error' => 'Field tidak diizinkan: ' . implode(', ', $unknown) . '.']);
$name = trim((string) ($in['name'] ?? ''));
if ($name === '' || mb_strlen($name) > 160) ingestApiSend(422, ['ok' => false, 'error' => 'name wajib diisi dan maksimal 160 karakter.']);
$id = (int) ($in['id'] ?? 0);
$status = ($in['status'] ?? 'inactive') === 'active' ? 'active' : 'inactive';
$magnetId = max(0, (int) ($in['lead_magnet_id'] ?? 0));
if ($magnetId > 0) {
    $magnetCheck = $pdo->prepare('SELECT id FROM lead_magnets WHERE id = ? LIMIT 1');
    $magnetCheck->execute([$magnetId]);
    if (!$magnetCheck->fetchColumn()) ingestApiSend(422, ['ok' => false, 'error' => 'lead_magnet_id tidak ditemukan.']);
}
if (!isset($in['steps']) || !is_array($in['steps']) || count($in['steps']) > 50) ingestApiSend(422, ['ok' => false, 'error' => 'steps wajib berupa array maksimal 50 item.']);
$steps = []; $numbers = [];
foreach ($in['steps'] as $i => $step) {
    if (!is_array($step)) ingestApiSend(422, ['ok' => false, 'error' => 'Step ke-' . ($i + 1) . ' wajib berupa object.']);
    $number = (int) ($step['step_number'] ?? ($i + 1)); $delay = (int) ($step['delay_days'] ?? 0); $subject = trim(str_replace(["\r", "\n"], '', (string) ($step['subject'] ?? ''))); $body = (string) ($step['body'] ?? ''); $format = ($step['body_format'] ?? 'html') === 'text' ? 'text' : 'html'; $stepStatus = ($step['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    if ($number < 1 || $number > 50 || in_array($number, $numbers, true)) ingestApiSend(422, ['ok' => false, 'error' => 'step_number harus unik dan berada di antara 1-50.']);
    if ($delay < 0 || $delay > 365) ingestApiSend(422, ['ok' => false, 'error' => 'delay_days harus berada di antara 0-365.']);
    if ($subject === '' || mb_strlen($subject) > 255) ingestApiSend(422, ['ok' => false, 'error' => 'Subject step ke-' . ($i + 1) . ' wajib dan maksimal 255 karakter.']);
    if ($body === '' || mb_strlen($body) > 500000) ingestApiSend(422, ['ok' => false, 'error' => 'Body step ke-' . ($i + 1) . ' wajib dan maksimal 500 KB.']);
    $numbers[] = $number; $steps[] = ['step_number' => $number, 'delay_days' => $delay, 'subject' => $subject, 'body' => $body, 'body_format' => $format, 'status' => $stepStatus];
}
usort($steps, fn($a, $b) => $a['step_number'] <=> $b['step_number']);
$dry = !empty($in['dry_run']);
if ($dry) ingestApiSend(200, ['ok' => true, 'dry_run' => true, 'name' => $name, 'lead_magnet_id' => $magnetId ?: null, 'status' => $status, 'steps' => $steps]);

try {
    $pdo->beginTransaction();
    if ($id > 0) {
        $chk = $pdo->prepare('SELECT id FROM email_sequences WHERE id=? LIMIT 1'); $chk->execute([$id]); if (!$chk->fetchColumn()) { $pdo->rollBack(); ingestApiSend(404, ['ok' => false, 'error' => 'Email sequence tidak ditemukan.']); }
        $pdo->prepare('UPDATE email_sequences SET name=?, lead_magnet_id=NULLIF(?,0), status=?, updated_at=NOW() WHERE id=?')->execute([$name, $magnetId, $status, $id]);
    } else {
        $pdo->prepare("INSERT INTO email_sequences (name, trigger_event, lead_magnet_id, provider, status) VALUES (?, 'subscriber_created', NULLIF(?,0), 'mailketing', ?)")->execute([$name, $magnetId, $status]); $id = (int) $pdo->lastInsertId();
    }
    $pdo->prepare('DELETE FROM email_sequence_sends WHERE step_id IN (SELECT id FROM email_sequence_steps WHERE sequence_id=?)')->execute([$id]);
    $pdo->prepare('DELETE FROM email_sequence_steps WHERE sequence_id=?')->execute([$id]);
    $ins = $pdo->prepare('INSERT INTO email_sequence_steps (sequence_id, step_number, delay_days, subject, body, body_format, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($steps as $step) $ins->execute([$id, $step['step_number'], $step['delay_days'], $step['subject'], $step['body'], $step['body_format'], $step['status']]);
    $pdo->commit();
    ingestLog('email-sequence-diubah id=' . $id . ' steps=' . count($steps));
    $st = $pdo->prepare('SELECT id,name,trigger_event,lead_magnet_id,provider,status,created_at,updated_at FROM email_sequences WHERE id=?'); $st->execute([$id]);
    ingestApiSend(200, ['ok' => true, 'sequence' => scribeApiSequenceOutput($pdo, $st->fetch())]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ingestLog('500 email-sequence-gagal');
    ingestApiSend(500, ['ok' => false, 'error' => 'Gagal menyimpan email sequence.']);
}
