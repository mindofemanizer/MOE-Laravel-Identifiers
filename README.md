# MOE Laravel Identifiers

Package Laravel untuk **dua kebutuhan identifier** yang sering berulang di setiap project:

1. **Public ID obfuscation** — sembunyikan integer primary key (`1, 2, 3, ...`) di URL/API publik untuk mencegah IDOR & enumerasi. Trait: `HasPublicId`.
2. **Document numbering** — nomor dokumen yang mudah dibaca manusia untuk Order, Invoice, Ticket, dll. Trait: `HasDocumentNumber`.

> **Catatan keamanan:** Public ID obfuscation adalah *defense-in-depth*, **bukan** pengganti otorisasi. Tetap wajib melakukan authorization check (policy/gate) di setiap endpoint.

## Requirements

- PHP `^8.2`
- Laravel `^11 | ^12 | ^13`

## Instalasi

> Package ini **privat** (tidak dipublikasikan di Packagist). Pilih salah satu cara di bawah sesuai kebutuhan.

### A. Konsumsi via GitHub (untuk project lain / deploy)

Tambahkan repository VCS ke `composer.json` project yang memakai package:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/mindofemanizer/MOE-Laravel-Identifiers" }
]
```

Lalu require versi rilis:

```bash
composer require moe/laravel-identifiers:^1.0
```

Karena repo privat, Composer butuh GitHub Personal Access Token (scope `repo`).
Set sekali per mesin/server (jangan commit token ke git):

```bash
composer config --global github-oauth.github.com <TOKEN>
```

### B. Pengembangan lokal (mengedit package langsung)

Kloning package sejajar dengan project, lalu pakai path repository:

```json
"repositories": [
    { "type": "path", "url": "../MOE-Laravel-Identifiers" }
]
```

```bash
composer require moe/laravel-identifiers:@dev
```

Composer akan membuat junction/symlink ke folder lokal, sehingga setiap
perubahan pada package langsung terpakai tanpa `composer update`.

### Publish config & migration

Publikasikan config (opsional) dan migration:

```bash
php artisan vendor:publish --tag=moe-identifiers-config
php artisan vendor:publish --tag=moe-identifiers-migrations
php artisan migrate
```

Migration hanya diperlukan bila kamu memakai token `{SEQ}` (nomor urut gap-safe). Migration akan otomatis di-load oleh package, jadi cukup jalankan `php artisan migrate` tanpa publish jika kamu tidak perlu mengubahnya.

## Kebutuhan 1 — Public ID (`HasPublicId`)

### Driver `sqids` (default)

Encode reversible dari integer PK. **Tidak** butuh kolom tambahan.

```php
use Illuminate\Database\Eloquent\Model;
use Moe\Identifiers\Concerns\HasPublicId;

class User extends Model
{
    use HasPublicId;

    // Opsional, default dari config:
    // protected string $publicIdDriver = 'sqids';
}
```

```php
$user->public_id;              // "bF3aX9kQ" — bukan "1"
User::findByPublicId('bF3aX9kQ'); // => User model
route('users.show', $user);    // otomatis pakai public_id di URL
```

Route model binding otomatis bekerja karena `getRouteKeyName()` mengembalikan `public_id`.

### Driver `uuid` / `ulid`

Menyimpan nilai acak di kolom (`public_id` secara default). Butuh kolom di tabel:

```php
// migration
$table->uuid('public_id')->unique(); // atau ->ulid(...)
```

```php
class License extends Model
{
    use HasPublicId;

    protected string $publicIdDriver = 'uuid'; // atau 'ulid'
    protected string $publicIdColumn = 'uuid'; // opsional, override nama kolom
}
```

Nilai akan otomatis diisi saat `creating` bila kosong.

### Override per-model

| Property | Fungsi |
|---|---|
| `$publicIdDriver` | `sqids` \| `uuid` \| `ulid` |
| `$publicIdColumn` | Nama kolom penyimpanan (untuk uuid/ulid) |

## Kebutuhan 2 — Document Number (`HasDocumentNumber`)

Nomor dokumen berbasis format token. Wajib set `$numberFormat`.

```php
use Illuminate\Database\Eloquent\Model;
use Moe\Identifiers\Concerns\HasDocumentNumber;

class Invoice extends Model
{
    use HasDocumentNumber;

    protected string $numberColumn = 'number';       // default: 'number'
    protected string $numberFormat = 'INV-{Y}-{SEQ:5}';
    protected string $numberSequenceKey = 'invoices'; // default: nama tabel
}
```

```php
$invoice = Invoice::create([...]);
$invoice->number; // "INV-2026-00001", lalu "INV-2026-00002", dst.
```

### Token yang tersedia

| Token | Hasil | Contoh |
|---|---|---|
| `{Y}` | Tahun 4 digit | `2026` |
| `{y}` | Tahun 2 digit | `26` |
| `{m}` | Bulan 2 digit | `07` |
| `{d}` | Tanggal 2 digit | `03` |
| `{Ymd}` | Tanggal ringkas | `20260703` |
| `{RAND:n}` | `n` karakter acak CSPRNG (alfabet non-ambigu) | `{RAND:6}` → `XWK5HB` |
| `{SEQ:n}` | Nomor urut gap-safe, di-pad `n` digit | `{SEQ:5}` → `00001` |

**Alfabet `{RAND}`** default: `23456789ABCDEFGHJKMNPQRSTVWXYZ` (tanpa `0/O`, `1/I/L`, `U`) untuk menghindari salah baca.

**Perbedaan `{RAND}` vs `{SEQ}`:**

- `{RAND}` — acak, tidak berurutan (tidak bisa ditebak/enumerasi), unik dicek ke DB (retry sampai 10x).
- `{SEQ}` — berurutan & rapi, gap-safe via transaksi + `lockForUpdate`. Reset periodik (default tahunan) diatur di config.

### Contoh format umum

```php
'ORD-{Ymd}-{RAND:6}'   // ORD-20260703-XWK5HB
'INV-{Y}-{SEQ:5}'      // INV-2026-00001
'TIC-{y}{m}-{SEQ:4}'   // TIC-2607-0001
```

## Facade `MoeId`

Untuk encode/decode manual (driver sqids):

```php
use Moe\Identifiers\Facades\MoeId;

MoeId::encode(42);        // "bF3aX9kQ"
MoeId::decode('bF3aX9kQ'); // 42 (atau null jika kode tidak valid)
MoeId::driver();          // "sqids"
```

## Konfigurasi

File `config/moe-identifiers.php`:

```php
return [
    'public_id' => [
        'driver' => env('MOE_ID_DRIVER', 'sqids'),
        'sqids' => [
            'alphabet'   => env('MOE_ID_SQIDS_ALPHABET', '...'),
            'min_length' => env('MOE_ID_SQIDS_MIN_LENGTH', 8),
            'blocklist'  => [],
        ],
        'column' => 'public_id',
    ],
    'document_number' => [
        'random_alphabet' => '23456789ABCDEFGHJKMNPQRSTVWXYZ',
        'sequence' => [
            'table' => 'moe_document_sequences',
            'reset' => 'yearly', // yearly | monthly | daily | never
        ],
    ],
];
```

> **Penting:** ganti `MOE_ID_SQIDS_ALPHABET` per-project (alfabet berbeda ⇒ output berbeda). Jangan ubah alfabet setelah produksi berjalan karena akan memutus URL lama.

## Testing

```bash
composer test
```

## Lisensi

MIT © MOE (MindOfEmanizer)
