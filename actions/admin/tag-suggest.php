<?php
// GET /actions/admin/tag-suggest?q= — autocomplete tag untuk token input editor.
require_once __DIR__ . '/../../bootstrap.php';
requireStaff();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') { echo json_encode([]); exit; }

try {
    $stmt = getDB()->prepare("SELECT name FROM tags WHERE name LIKE ? ORDER BY name ASC LIMIT 8");
    $stmt->execute(['%' . $q . '%']);
    echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Throwable $e) {
    echo json_encode([]);
}
