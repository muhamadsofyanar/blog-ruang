<?php
// Halaman 403 (akses ditolak). Dipanggil oleh denyAccess(). Identitas netral —
// bukan halaman produk customer. $title disediakan pemanggil.
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? 'Akses Ditolak') ?></title>
<style>
body{font-family:system-ui,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;background:#f9fafb;color:#111827;padding:1rem}
.box{max-width:420px;text-align:center;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:2.5rem 2rem}
h1{font-size:1.25rem;margin:0 0 .5rem}p{color:#6b7280;font-size:.9rem;line-height:1.6;margin:0 0 1.25rem}
a{display:inline-block;background:#6366f1;color:#fff;text-decoration:none;font-weight:600;font-size:.875rem;padding:.5rem 1rem;border-radius:6px}
</style></head>
<body><div class="box">
  <h1>Akses Ditolak</h1>
  <p>Akun Anda (<?= e(currentRole()) ?>) tidak memiliki izin untuk membuka halaman ini. Halaman ini khusus administrator.</p>
  <a href="<?= e(url('/admin')) ?>">Kembali ke Dashboard</a>
</div></body></html>
