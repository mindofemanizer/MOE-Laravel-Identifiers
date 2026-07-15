<?php

declare(strict_types=1);

namespace MOE\Identifiers\Support;

use Illuminate\Contracts\Config\Repository as Config;
use MOE\Identifiers\Exceptions\IdentifierException;
use Sqids\Sqids;

/**
 * Wrapper enkode/dekode untuk Public ID.
 *
 * Driver "sqids" bersifat reversible (id <-> kode) tanpa kolom tambahan.
 * Driver "uuid"/"ulid" hanya membuat nilai acak; enkode/dekode tidak berlaku
 * karena nilainya disimpan langsung di kolom model.
 */
class PublicIdCodec
{
    protected ?Sqids $sqids = null;

    public function __construct(protected Config $config)
    {
    }

    /**
     * Driver aktif: "sqids" | "uuid" | "ulid".
     */
    public function driver(): string
    {
        return (string) $this->config->get('moe-identifiers.public_id.driver', 'sqids');
    }

    /**
     * Enkode satu integer id menjadi kode publik (khusus driver sqids).
     */
    public function encode(int $id): string
    {
        $this->assertSqids('encode');

        return $this->sqids()->encode([$id]);
    }

    /**
     * Dekode kode publik menjadi integer id (khusus driver sqids).
     * Mengembalikan null bila kode tidak valid / tidak dapat didekode
     * ke bentuk kanonik yang sama (mencegah kode palsu diterima).
     */
    public function decode(string $code): ?int
    {
        $this->assertSqids('decode');

        $numbers = $this->sqids()->decode($code);

        if (count($numbers) !== 1) {
            return null;
        }

        $id = $numbers[0];

        // Verifikasi kanonik: hasil encode(id) harus identik dengan input.
        // Ini menolak kode yang "kebetulan" bisa didekode tapi bukan bentuk
        // resmi dari id tersebut.
        if ($this->sqids()->encode([$id]) !== $code) {
            return null;
        }

        return $id;
    }

    /**
     * Instance Sqids yang dibangun dari konfigurasi (lazy + cached).
     */
    protected function sqids(): Sqids
    {
        if ($this->sqids instanceof Sqids) {
            return $this->sqids;
        }

        $alphabet = (string) $this->config->get('moe-identifiers.public_id.sqids.alphabet', '');
        $minLength = max(0, (int) $this->config->get('moe-identifiers.public_id.sqids.min_length', 8));
        $blocklist = (array) $this->config->get('moe-identifiers.public_id.sqids.blocklist', []);

        // Konstruktor Sqids: alphabet (string), minLength (int), blocklist (array).
        // Bila alphabet kosong, gunakan default bawaan Sqids agar tetap aman.
        if ($alphabet !== '' && $blocklist !== []) {
            $this->sqids = new Sqids($alphabet, $minLength, $blocklist);
        } elseif ($alphabet !== '') {
            $this->sqids = new Sqids($alphabet, $minLength);
        } elseif ($blocklist !== []) {
            $this->sqids = new Sqids(minLength: $minLength, blocklist: $blocklist);
        } else {
            $this->sqids = new Sqids(minLength: $minLength);
        }

        return $this->sqids;
    }

    protected function assertSqids(string $op): void
    {
        if ($this->driver() !== 'sqids') {
            throw new IdentifierException(
                "Operasi [{$op}] hanya tersedia untuk driver 'sqids'. Driver aktif: '{$this->driver()}'."
            );
        }
    }
}
