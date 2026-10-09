<?php
// Halaman enforcement lisensi (suspended / expired). BATAS REBRAND: halaman ini
// SELALU identitas Averion — komunikasi vendor→customer, bukan bagian produk
// customer→pembaca. TIDAK membaca setting rebrand apa pun (logo/nama blog).
$ls        = resolveCurrentLicenseState();
$suspended = $ls['suspended'];
$supportEmail = getSetting('vendor_support_email', defined('VENDOR_EMAIL') ? VENDOR_EMAIL : 'support@averion.id');
$supportWa    = getSetting('vendor_support_whatsapp', defined('VENDOR_WHATSAPP') ? VENDOR_WHATSAPP : '');
$renewUrl     = getSetting('vendor_renew_url', '');
$source       = getSetting('license_source', 'direct');

if ($suspended) {
    $title = 'Lisensi Disuspend';
    $heading = 'Lisensi Ditangguhkan';
    $body = 'Lisensi Averion SEO Engine untuk situs ini sedang ditangguhkan (suspended). Situs dikunci sementara hingga masalah diselesaikan bersama vendor.';
} else {
    $title = 'Lisensi Perlu Divalidasi';
    $heading = 'Lisensi Kedaluwarsa';
    $body = 'Lisensi Averion SEO Engine untuk situs ini belum tervalidasi ulang atau telah kedaluwarsa. Perpanjang atau hubungi dukungan untuk mengaktifkannya kembali.';
}
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — Averion SEO Engine</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;background:#0f172a;color:#e2e8f0;padding:1rem}
.box{max-width:460px;width:100%;background:#1e293b;border:1px solid #334155;border-radius:8px;padding:2.5rem 2rem;text-align:center}
.badge{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:8px;background:<?= $suspended ? '#7f1d1d' : '#78350f' ?>;margin-bottom:1.25rem}
.badge svg{width:28px;height:28px;stroke:<?= $suspended ? '#fecaca' : '#fde68a' ?>}
h1{font-size:1.35rem;margin:0 0 .5rem;color:#fff}
p{color:#94a3b8;font-size:.9rem;line-height:1.65;margin:0 0 1.5rem}
.brand{font-size:.7rem;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin-bottom:1rem}
.actions a{display:inline-block;margin:.25rem;text-decoration:none;font-weight:600;font-size:.85rem;padding:.55rem 1.1rem;border-radius:6px}
.primary{background:#6366f1;color:#fff}.secondary{background:#334155;color:#e2e8f0}
.support{margin-top:1.5rem;font-size:.8rem;color:#64748b;border-top:1px solid #334155;padding-top:1.25rem}
.support a{color:#a5b4fc;text-decoration:none}
</style></head>
<body><div class="box">
  <div class="brand">Averion SEO Engine</div>
  <div class="badge"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php
    echo $suspended
      ? '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'
      : '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>';
  ?></svg></div>
  <h1><?= e($heading) ?></h1>
  <p><?= e($body) ?></p>
  <div class="actions">
    <?php if ($renewUrl !== ''): ?><a class="primary" href="<?= e($renewUrl) ?>">Perpanjang Lisensi</a><?php endif; ?>
    <a class="secondary" href="<?= e(url('/admin/license')) ?>">Cek Status Lisensi</a>
  </div>
  <div class="support">
    Butuh bantuan? Hubungi dukungan <?= $source === 'agency' ? 'agency' : 'Averion' ?>:<br>
    <?php if ($supportEmail): ?><a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a><?php endif; ?>
    <?php if ($supportWa): ?> &middot; <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $supportWa)) ?>">WhatsApp</a><?php endif; ?>
  </div>
</div></body></html>
