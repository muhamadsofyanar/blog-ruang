<?php
// Route /sitemap.xml — sajikan file cache/sitemap.xml (generate on-demand bila
// belum ada). Jalankan lazy publish dulu agar scheduled yang lewat ikut masuk.
require_once __DIR__ . '/../../helpers/schedule.php';
require_once __DIR__ . '/../../helpers/sitemap.php';

scribeLazyPublish();
scribeServeSitemap();
