<?php
// ════════════════════════════════════════════════════════════════════════
// Public key vendor — MEMVERIFIKASI signature token lisensi di sisi client.
// ────────────────────────────────────────────────────────────────────────
// PENTING:
//  - Ini BUKAN rahasia. Public key boleh berada di kode client karena fungsinya
//    hanya VERIFIKASI, bukan menandatangani. Yang rahasia (private key) HANYA ada
//    di server vendor (averion-license, VENDOR_PRIVATE_KEY_PATH).
//  - JANGAN PERNAH menaruh private key di file ini atau paket client mana pun.
//  - 'v1' = public key Ed25519 ASLI vendor (base64 standar), pasangan private key
//    produksi di server averion-license. Sama dengan yang dipakai produk Averion.
//  - Format: base64 STANDAR (bukan base64url) dari 32-byte raw Ed25519 public key.
//
// GATE DEV-KEY (A-08): jalur pubkey dev (kid staging) HANYA aktif pada instalasi
// pengembangan — WAJIB memenuhi TIGA syarat sekaligus: (1) APP_ENV==='dev',
// (2) file marker `.dev-mode` ada di root, (3) config mendefinisikan konstanta
// override dev. Artefak release TIDAK memuat `.dev-mode`, APP_ENV bukan 'dev',
// DAN langkah build MELUCUTI blok override di bawah (menggantinya dengan versi
// bersih hanya kid produksi). Jadi di produksi jalur override tidak mungkin
// aktif — dibuktikan grep artefak: nama konstanta override tidak ditemukan.
// ════════════════════════════════════════════════════════════════════════

$__scribe_pubkeys = [
    'v1' => 'P6OQpcTUUw5YJ8i9pUwFVc0OWmM6ScP6+OuE1Hxx+Z0=',
];

// (jalur dev-key dilucuti di artefak release)

define('LICENSE_PUBKEYS', $__scribe_pubkeys);
unset($__scribe_pubkeys);

// (Opsional) Fallback RSA — tidak aktif selama kosong (verifikasi via sodium).
const LICENSE_PUBKEYS_RSA = [];
