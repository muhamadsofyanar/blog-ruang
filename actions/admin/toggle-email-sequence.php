<?php
require_once __DIR__ . '/../../bootstrap.php'; requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) { flash('error','Permintaan tidak valid.','error'); redirect('/admin/email-sequences'); }
$id=(int)($_POST['id']??0); $pdo=getDB(); $st=$pdo->prepare('SELECT status FROM email_sequences WHERE id=?');$st->execute([$id]);$cur=$st->fetchColumn();if($cur===false){flash('error','Sequence tidak ditemukan.','error');redirect('/admin/email-sequences');}$pdo->prepare('UPDATE email_sequences SET status=?,updated_at=NOW() WHERE id=?')->execute([$cur==='active'?'inactive':'active',$id]);flash('success','Status sequence diperbarui.','success');redirect('/admin/email-sequences');
