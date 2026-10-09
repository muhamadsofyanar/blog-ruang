<?php
// POST /actions/admin/save-appearance — simpan preset UI theme + sidebar/topbar.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/theme-config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/appearance');
}

$theme = $_POST['ui_theme'] ?? 'default';
if (!isset(scribeThemePresets()[$theme])) $theme = 'default';

$sidebar = $_POST['ui_sidebar_style'] ?? 'classic';
if (!isset(scribeSidebarStyles()[$sidebar])) $sidebar = 'classic';

$topbar = $_POST['ui_topbar_style'] ?? 'default';
if (!isset(scribeTopbarStyles()[$topbar])) $topbar = 'default';

setSetting('ui_theme', $theme, 'appearance');
setSetting('ui_sidebar_style', $sidebar, 'appearance');
setSetting('ui_topbar_style', $topbar, 'appearance');

flash('success', 'Appearance disimpan.', 'success');
redirect('/admin/appearance');
