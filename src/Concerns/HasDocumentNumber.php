<?php

declare(strict_types=1);

namespace Moe\Identifiers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Moe\Identifiers\Exceptions\IdentifierException;
use Moe\Identifiers\Support\NumberFormatter;

/**
 * Kebutuhan 2 — Nomor dokumen yang enak dibaca manusia untuk
 * Order / Invoice / Ticket, mendukung token acak maupun sekuensial.
 *
 * Konfigurasi per-model:
 *  - protected string $numberColumn = 'order_number';
 *  - protected string $numberFormat = 'ORD-{Ymd}{RAND:6}';
 *  - protected ?string $numberSequenceKey = null; // default: nama tabel
 *
 * Nomor dibuat otomatis pada event "creating" bila kolomnya masih kosong.
 * Untuk token {RAND}, keunikan dijamin dengan retry cek database.
 */
trait HasDocumentNumber
{
    public static function bootHasDocumentNumber(): void
    {
        static::creating(function (Model $model): void {
            /** @var static $model */
            $column = $model->documentNumberColumn();

            if (! empty($model->getAttribute($column))) {

                return; // sudah di-set manual, hormati.
            }

            $model->setAttribute($column, $model->generateDocumentNumber());
        });
    }

    /**
     * Bangun nilai nomor dokumen berdasarkan format model.
     * Untuk format yang mengandung {RAND}, dilakukan retry hingga unik.
     * Untuk {SEQ}, keunikan sudah dijamin oleh counter (tidak perlu retry).
     *
     * @return string
     * @throws \Moe\Identifiers\Exceptions\IdentifierException
     */
    public function generateDocumentNumber(): string
    {
        $format = $this->documentNumberFormat();
        $column = $this->documentNumberColumn();
        $key = $this->documentNumberSequenceKey();

        /** @var NumberFormatter $formatter */
        $formatter = app(NumberFormatter::class);

        $hasRandom = str_contains($format, '{RAND:');
        $maxAttempts = $hasRandom ? 10 : 1;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $candidate = $formatter->build($format, $key);

            // {SEQ} sudah dijamin unik oleh counter -> langsung pakai.
            if (! $hasRandom) {

                return $candidate;
            }

            // Pakai newQuery() dari instance model, bukan static::query()
            // untuk menghindari circular boot dependency.
            $exists = $this->newQuery()
                ->where($column, $candidate)
                ->exists();

            if (! $exists) {

                return $candidate;
            }
        }

        throw new IdentifierException(
            "Gagal membuat nomor dokumen unik untuk [{$format}] setelah {$maxAttempts} percobaan."
        );
    }

    /**
     * @return string
     */
    public function documentNumberColumn(): string
    {
        return property_exists($this, 'numberColumn')
            ? $this->numberColumn
            : 'number';
    }

    /**
     * @return string
     * @throws \Moe\Identifiers\Exceptions\IdentifierException
     */
    public function documentNumberFormat(): string
    {
        if (! property_exists($this, 'numberFormat') || empty($this->numberFormat)) {
            throw new IdentifierException(
                'Model ' . static::class . ' memakai HasDocumentNumber tetapi belum mendefinisikan $numberFormat.'
            );
        }

        return $this->numberFormat;
    }

    /**
     * @return string
     */
    public function documentNumberSequenceKey(): string
    {
        if (property_exists($this, 'numberSequenceKey') && ! empty($this->numberSequenceKey)) {

            return $this->numberSequenceKey;
        }

        return $this->getTable();
    }
}
