<?php
// ════════════════════════════════════════════════════════════════════════
// Profil penulis (E-E-A-T) — halaman penulis publik + Person schema.
// Data profil (bio/avatar/jabatan/sosial) disimpan sebagai BLOB SETTINGS per
// user (`author_profile_{id}`, grup 'author') — tanpa tabel/kolom baru. Nama &
// peran tetap dari tabel users. Slug penulis = slugify(nama).
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/frontend.php';   // url(), UPLOAD_URL
require_once __DIR__ . '/seo-head.php';   // seoJsonLdScript()

/** Data profil mentah (array) dari settings. */
function authorProfileRaw(int $id): array
{
    $raw = (string) getSetting('author_profile_' . $id, '');
    $d = $raw !== '' ? json_decode($raw, true) : [];
    return is_array($d) ? $d : [];
}

/** Simpan profil penulis (validasi + batasi). $social = array URL. */
function authorProfileSave(int $id, array $data): void
{
    $social = [];
    foreach ((array) ($data['social'] ?? []) as $s) {
        $s = trim((string) $s);
        if ($s !== '' && preg_match('#^https?://#i', $s)) $social[$s] = true;
    }
    $payload = [
        'bio'       => mb_substr(trim((string) ($data['bio'] ?? '')), 0, 1000),
        'job_title' => mb_substr(trim((string) ($data['job_title'] ?? '')), 0, 120),
        'avatar'    => trim((string) ($data['avatar'] ?? '')),
        'social'    => array_slice(array_keys($social), 0, 8),
    ];
    setSetting('author_profile_' . $id, (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'author');
}

/** Profil publik lengkap untuk sebuah user aktif, atau null. */
function authorPublic(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u) return null;

    $p = authorProfileRaw($id);
    $avatar = trim((string) ($p['avatar'] ?? ''));
    $social = [];
    foreach ((array) ($p['social'] ?? []) as $s) {
        $s = trim((string) $s);
        if ($s !== '' && preg_match('#^https?://#i', $s)) $social[] = $s;
    }
    $slug = slugify((string) $u['name']);
    return [
        'id'         => (int) $u['id'],
        'name'       => (string) $u['name'],
        'role'       => (string) $u['role'],
        'bio'        => trim((string) ($p['bio'] ?? '')),
        'job_title'  => trim((string) ($p['job_title'] ?? '')),
        'avatar_url' => $avatar !== '' ? rtrim(UPLOAD_URL, '/') . '/' . ltrim($avatar, '/') : '',
        'social'     => $social,
        'slug'       => $slug,
        'url'        => url('/penulis/' . $slug),
    ];
}

/** Resolve slug penulis → user id aktif (cocokkan slugify nama). 0 bila tak ada. */
function authorIdBySlug(PDO $pdo, string $slug): int
{
    $slug = strtolower(trim($slug));
    if ($slug === '') return 0;
    foreach ($pdo->query("SELECT id, name FROM users WHERE is_active = 1")->fetchAll() as $u) {
        if (slugify((string) $u['name']) === $slug) return (int) $u['id'];
    }
    return 0;
}

/** URL halaman penulis dari nama (untuk byline). */
function authorUrlFromName(string $name): string
{
    return url('/penulis/' . slugify($name));
}

/** JSON-LD Person untuk halaman penulis. */
function seoPersonJsonLd(array $author): string
{
    $node = [
        '@type' => 'Person',
        '@id'   => $author['url'] . '#person',
        'name'  => $author['name'],
        'url'   => $author['url'],
    ];
    if ($author['avatar_url'] !== '') $node['image'] = $author['avatar_url'];
    if ($author['bio'] !== '')        $node['description'] = $author['bio'];
    if ($author['job_title'] !== '')  $node['jobTitle'] = $author['job_title'];
    if (!empty($author['social']))    $node['sameAs'] = $author['social'];
    return seoJsonLdScript([$node]);
}
