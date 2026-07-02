<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public ID (Obfuscation) — Kebutuhan 1
    |--------------------------------------------------------------------------
    |
    | Menyembunyikan primary key integer (1, 2, 3) menjadi kode tak-berurutan
    | pada URL / API publik. Ini adalah defense-in-depth (anti-IDOR &
    | anti-enumerasi), BUKAN pengganti authorization. Tetap wajib ada cek
    | kepemilikan (Policy / where user_id) di sisi aplikasi.
    |
    */
    'public_id' => [

        // Driver default: "sqids" | "uuid" | "ulid".
        // - sqids : reversible, tanpa kolom tambahan (encode dari id).
        // - uuid  : kolom acak permanen tersimpan (butuh kolom di tabel).
        // - ulid  : kolom acak time-sortable tersimpan (butuh kolom di tabel).
        'driver' => env('MOE_ID_DRIVER', 'sqids'),

        // Konfigurasi khusus driver "sqids".
        'sqids' => [
            // Salt/alfabet rahasia. WAJIB di-set per-project via .env supaya
            // kode publik tidak bisa direproduksi lintas project.
            // Kosong = pakai alfabet default Sqids (tetap aman, tapi tidak
            // unik per-project).
            'alphabet' => env('MOE_ID_SQIDS_ALPHABET', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),

            // Panjang minimum kode hasil encode.
            'min_length' => (int) env('MOE_ID_SQIDS_MIN_LENGTH', 8),

            // Daftar kata terlarang yang tidak boleh muncul di hasil encode.
            // Biarkan default Sqids (kosong = pakai blocklist bawaan).
            'blocklist' => [],
        ],

        // Nama kolom yang menyimpan uuid/ulid saat memakai driver tsb.
        'column' => 'public_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Number — Kebutuhan 2
    |--------------------------------------------------------------------------
    |
    | Nomor dokumen yang enak dibaca manusia untuk Order / Invoice / Ticket.
    | Mendukung token acak (anti-tebak) maupun sekuensial gap-safe.
    |
    | Token yang didukung pada "format":
    |   {Y}        -> tahun 4 digit (2026)
    |   {y}        -> tahun 2 digit (26)
    |   {m}        -> bulan 2 digit (07)
    |   {d}        -> tanggal 2 digit (03)
    |   {Ymd}      -> gabungan tanggal (20260703)
    |   {RAND:n}   -> n karakter acak CSPRNG (alfabet non-ambigu)
    |   {SEQ:n}    -> nomor urut gap-safe, di-pad ke n digit (per periode reset)
    |
    | Contoh:
    |   'ORD-{Ymd}{RAND:6}'  -> ORD-20260703K7M2QX
    |   'INV-{Y}-{SEQ:5}'    -> INV-2026-00042
    |   'TKT-{RAND:8}'       -> TKT-A3F9B2C1
    |
    */
    'document_number' => [

        // Alfabet non-ambigu (Crockford-style) untuk token {RAND}.
        // Tanpa I, L, O, U, 0, 1 supaya tidak salah baca/ketik.
        'random_alphabet' => '23456789ABCDEFGHJKMNPQRSTVWXYZ',

        // Konfigurasi counter untuk token {SEQ}.
        'sequence' => [
            // Tabel penyimpan counter.
            'table' => 'moe_document_sequences',

            // Periode reset counter: "yearly" | "monthly" | "daily" | "never".
            // Menentukan kapan nomor urut kembali ke 1.
            'reset' => 'yearly',
        ],
    ],

];
