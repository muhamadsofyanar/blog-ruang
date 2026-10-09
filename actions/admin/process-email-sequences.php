<?php
require_once __DIR__ . '/../../bootstrap.php'; require_once __DIR__ . '/../../helpers/mailketing.php'; require_once __DIR__ . '/../../helpers/email-sequence.php'; requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) { flash('error','Permintaan tidak valid.','error'); redirect('/admin/email-sequences'); }
$mk=scribeProcessMailketingQueue(50);$seq=scribeProcessDueEmailSequenceSends(50);flash('success','Antrean diproses: '.$mk['sent'].' subscriber masuk list, '.$seq['sent'].' email terkirim.','success');redirect('/admin/email-sequences');
