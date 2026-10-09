<?php
require_once __DIR__ . '/_shell.php'; requireAdmin();
$pdo=getDB();$sequenceId=(int)($_GET['id']??0);$st=$pdo->prepare('SELECT * FROM email_sequences WHERE id=? LIMIT 1');$st->execute([$sequenceId]);$sequence=$st->fetch();if(!$sequence){flash('error','Sequence tidak ditemukan.','error');redirect('/admin/email-sequences');}
$editId=(int)($_GET['step']??0);$edit=null;if($editId>0){$q=$pdo->prepare('SELECT * FROM email_sequence_steps WHERE id=? AND sequence_id=?');$q->execute([$editId,$sequenceId]);$edit=$q->fetch()?:null;}$q=$pdo->prepare('SELECT * FROM email_sequence_steps WHERE sequence_id=? ORDER BY step_number ASC');$q->execute([$sequenceId]);$steps=$q->fetchAll();
admin_shell_top('Email Sequence Steps','/admin/email-sequences');
?>
<div class="max-w-6xl space-y-4"><div class="flex flex-wrap items-end justify-between gap-3"><div><a href="<?= e(url('/admin/email-sequences')) ?>" class="text-xs text-accent">← Email Sequence</a><h1 class="font-display font-semibold text-xl mt-1"><?= e($sequence['name']) ?></h1><p class="text-sm text-gray-500 mt-1">Step dikirim sesuai jeda hari setelah subscriber masuk.</p></div></div><div class="grid lg:grid-cols-[1fr_430px] gap-4"><div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden"><div class="px-5 py-4 border-b border-gray-200 dark:border-gray-800"><h2 class="font-display font-semibold">Daftar email</h2></div><div class="divide-y divide-gray-200 dark:divide-gray-800"><?php foreach($steps as $s):?><div class="px-5 py-4 flex items-start justify-between gap-3"><div><p class="text-xs text-gray-500">D+<?= (int)$s['delay_days'] ?> · <?= e(strtoupper($s['body_format'])) ?></p><a href="<?= e(url('/admin/email-sequence-steps?id='.$sequenceId.'&step='.(int)$s['id'])) ?>" class="font-semibold text-sm hover:text-accent">#<?= (int)$s['step_number'] ?> <?= e($s['subject']) ?></a></div><span class="text-xs <?= $s['status']==='active'?'text-emerald-600':'text-gray-500' ?>"><?= $s['status']==='active'?'Aktif':'Nonaktif' ?></span></div><?php endforeach;?><?php if(!$steps):?><div class="p-8 text-center text-sm text-gray-500">Belum ada step. Tambahkan email pertama.</div><?php endif;?></div></div><form method="post" action="<?= e(url('/actions/admin/save-email-sequence-step')) ?>" class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-3"><input type="hidden" name="sequence_id" value="<?= $sequenceId ?>"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>"><?= csrfField() ?><h2 class="font-display font-semibold"><?= $edit?'Edit email':'Tambah email' ?></h2><div class="grid grid-cols-2 gap-3"><div><label class="block text-xs font-medium mb-1">Nomor step</label><input type="number" name="step_number" min="1" max="50" required value="<?= (int)($edit['step_number']??(count($steps)+1)) ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div><div><label class="block text-xs font-medium mb-1">Jeda (hari)</label><input type="number" name="delay_days" min="0" max="365" required value="<?= (int)($edit['delay_days']??0) ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div></div><div><label class="block text-xs font-medium mb-1">Subject</label><input name="subject" required maxlength="255" value="<?= e($edit['subject']??'') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div><div><label class="block text-xs font-medium mb-1">Format email</label><select name="body_format" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm"><option value="html" <?= ($edit['body_format']??'html')==='html'?'selected':'' ?>>HTML lengkap</option><option value="text" <?= ($edit['body_format']??'')==='text'?'selected':'' ?>>Teks biasa</option></select></div><div><label class="block text-xs font-medium mb-1">Isi email</label><textarea name="body" required rows="12" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono"><?= e($edit['body']??'') ?></textarea><p class="text-[11px] text-gray-400 mt-1">Placeholder: {{name}}, {{email}}, {{blog_name}}, {{article_title}}, {{article_url}}, {{lead_magnet_title}}, {{lead_magnet_url}}, {{unsubscribe_url}}</p></div><div><label class="block text-xs font-medium mb-1">Status</label><select name="status" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm"><option value="active" <?= ($edit['status']??'active')==='active'?'selected':'' ?>>Aktif</option><option value="inactive" <?= ($edit['status']??'')==='inactive'?'selected':'' ?>>Nonaktif</option></select></div><div class="flex gap-2"><button type="button" onclick="emailPreview()" class="inline-flex items-center gap-1.5 rounded-md px-4 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"><?= icon('eye','w-4 h-4') ?> Pratinjau</button><button class="rounded-md px-4 py-2 text-sm font-semibold text-white" style="background:var(--accent)">Simpan Email</button><?php if($edit):?><button type="submit" formaction="<?= e(url('/actions/admin/delete-email-sequence-step')) ?>" name="id" value="<?= (int)$edit['id'] ?>" data-confirm="Hapus email sequence ini?" data-confirm-danger class="rounded-md px-4 py-2 text-sm font-medium border border-red-200 text-red-600">Hapus</button><?php endif;?></div></form></div></div>

<!-- Modal Pratinjau Email -->
<div id="emPreview" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
  <div class="w-full max-w-[680px] max-h-[90vh] flex flex-col rounded-lg overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-800">
      <h3 class="font-display font-semibold text-sm">Pratinjau Email <span class="text-xs font-normal text-gray-400">(placeholder diisi contoh)</span></h3>
      <button type="button" onclick="emailPreviewClose()" class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><?= icon('x','w-4 h-4') ?></button>
    </div>
    <div id="emPreviewWrap" class="bg-gray-100 dark:bg-gray-950 p-3 overflow-auto" style="min-height:70vh"></div>
  </div>
</div>
<script>
function emailPreviewClose(){ document.getElementById('emPreview').classList.add('hidden'); document.getElementById('emPreview').classList.remove('flex'); }
function emailPreview(){
  var form = document.querySelector('form[action*="save-email-sequence-step"]');
  var body = form.querySelector('[name=body]').value;
  if(!body.trim()){ (window.scribeAlert?scribeAlert('Isi email masih kosong.'):alert('Isi email masih kosong.')); return; }
  var fd = new FormData();
  fd.append('csrf_token', <?= json_encode(generateCSRF()) ?>);
  fd.append('body', body);
  fd.append('subject', form.querySelector('[name=subject]').value);
  fd.append('body_format', form.querySelector('[name=body_format]').value);
  var modal = document.getElementById('emPreview'), wrap = document.getElementById('emPreviewWrap');
  // Loading di luar iframe (bukan srcdoc) agar tak balapan.
  wrap.innerHTML = '<p style="font-family:sans-serif;color:#64748b;padding:16px">Memuat…</p>';
  modal.classList.remove('hidden'); modal.classList.add('flex');
  fetch(<?= json_encode(url('/actions/admin/email-preview')) ?>, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(function(r){ return r.text(); })
    .then(function(html){
      // Iframe BARU tiap kali + srcdoc SEKALI → render andal (tanpa race srcdoc).
      var f = document.createElement('iframe');
      f.setAttribute('sandbox', '');
      f.className = 'w-full bg-white rounded';
      f.style.cssText = 'height:70vh;border:0';
      f.srcdoc = html;
      wrap.innerHTML = '';
      wrap.appendChild(f);
    })
    .catch(function(){ wrap.innerHTML = '<p style="font-family:sans-serif;color:#b91c1c;padding:16px">Gagal memuat pratinjau.</p>'; });
}
document.getElementById('emPreview').addEventListener('click', function(e){ if(e.target===this) emailPreviewClose(); });
document.addEventListener('keydown', function(e){ if(e.key==='Escape') emailPreviewClose(); });
</script>
<?php admin_shell_bottom(); ?>
