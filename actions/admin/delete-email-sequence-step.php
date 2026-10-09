<?php
require_once __DIR__ . '/../../bootstrap.php'; requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) { flash('error','Permintaan tidak valid.','error'); redirect('/admin/email-sequences'); }
$pdo=getDB();$id=(int)($_POST['id']??0);$sequenceId=(int)($_POST['sequence_id']??0);try{$pdo->prepare('DELETE FROM email_sequence_sends WHERE step_id=?')->execute([$id]);$pdo->prepare('DELETE FROM email_sequence_steps WHERE id=? AND sequence_id=?')->execute([$id,$sequenceId]);flash('success','Email sequence dihapus.','success');}catch(Throwable $e){flash('error','Email sequence belum dapat dihapus.','error');}redirect('/admin/email-sequence-steps?id='.$sequenceId);
