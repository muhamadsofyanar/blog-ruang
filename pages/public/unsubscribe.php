<?php
require_once __DIR__ . '/../../views/theme-default/layout.php';
$token = trim((string) ($_GET['token'] ?? ''));
$ok = false;
if (preg_match('/^[a-f0-9]{64}$/i', $token)) {
    try {
        $pdo = getDB();
        $st = $pdo->prepare('SELECT id FROM subscribers WHERE unsubscribe_token = ? LIMIT 1'); $st->execute([$token]);
        $id = (int) ($st->fetchColumn() ?: 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE subscribers SET unsubscribed_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$id]);
            $pdo->prepare("UPDATE email_sequence_enrollments SET status = 'unsubscribed' WHERE subscriber_id = ? AND status = 'active'")->execute([$id]);
            $ok = true;
        }
    } catch (Throwable $e) { error_log('unsubscribe: ' . $e->getMessage()); }
}
$brand = feBrand();
theme_head(['title' => 'Preferensi Email — ' . $brand['name'], 'description' => 'Preferensi email subscriber']);
?>
<main class="max-w-xl mx-auto px-4 sm:px-6 py-16">
  <div class="fe-card rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-7 text-center">
    <div class="mx-auto w-11 h-11 rounded-md flex items-center justify-center <?= $ok ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>"><?= icon($ok ? 'check-circle' : 'mail', 'w-6 h-6') ?></div>
    <h1 class="font-display font-bold text-2xl mt-4"><?= $ok ? 'Anda sudah berhenti berlangganan' : 'Tautan tidak valid' ?></h1>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><?= $ok ? 'Kami tidak akan mengirim email sequence berikutnya ke alamat ini.' : 'Tautan preferensi email tidak ditemukan atau sudah tidak berlaku.' ?></p>
    <a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-2 mt-5 text-sm font-semibold text-accent">Kembali ke beranda <?= icon('arrow-right', 'w-4 h-4') ?></a>
  </div>
</main>
<?php theme_footer(); ?>
