<?php
// ════════════════════════════════════════════════════════════════════════
// Route /blog — Homepage Blog (hero + kategori + featured + grid + pagination).
// Memakai ULANG pages/public/home.php dengan mode blog DIPAKSA (tanpa duplikasi
// logic), sehingga homepage '/' bebas dipakai untuk BioLink via setting home_mode.
// ════════════════════════════════════════════════════════════════════════

$GLOBALS['scribe_blog_route'] = true;
require __DIR__ . '/home.php';
