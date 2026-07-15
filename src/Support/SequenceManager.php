<?php

declare(strict_types=1);

namespace MOE\Identifiers\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * Pengelola counter untuk token {SEQ} pada nomor dokumen.
 *
 * Menjamin nomor urut gap-safe & anti-duplikat menggunakan transaksi
 * + lockForUpdate(), aman saat banyak request bersamaan.
 */
class SequenceManager
{
    public function __construct(
        protected ConnectionInterface $db,
        protected Config $config,
    ) {
    }

    /**
     * Ambil nomor urut berikutnya untuk sebuah key + periode.
     * Baris counter dibuat otomatis bila belum ada.
     */
    public function next(string $key, string $period): int
    {
        $table = $this->table();

        return $this->db->transaction(function () use ($table, $key, $period): int {
            $row = $this->db->table($table)
                ->where('key', $key)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                // Coba insert baris awal. Bila balapan (duplikat), ambil ulang.
                try {
                    $this->db->table($table)->insert([
                        'key' => $key,
                        'period' => $period,
                        'value' => 1,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    return 1;
                } catch (\Throwable $e) {
                    $row = $this->db->table($table)
                        ->where('key', $key)
                        ->where('period', $period)
                        ->lockForUpdate()
                        ->first();

                    // Sangat jarang: insert gagal bukan karena duplikat, dan
                    // baris tetap tidak ada. Lempar error yang jelas daripada
                    // memicu TypeError saat mengakses $row->value pada null.
                    if ($row === null) {
                        throw $e;
                    }
                }
            }

            $next = ((int) $row->value) + 1;

            $this->db->table($table)
                ->where('key', $key)
                ->where('period', $period)
                ->update([
                    'value' => $next,
                    'updated_at' => Carbon::now(),
                ]);

            return $next;
        });
    }

    /**
     * Tentukan label periode berdasarkan konfigurasi reset.
     * yearly -> "2026" | monthly -> "2026-07" | daily -> "2026-07-03"
     * never  -> "all"
     */
    public function periodFor(?Carbon $now = null): string
    {
        $now ??= Carbon::now();

        return match ($this->resetMode()) {
            'monthly' => $now->format('Y-m'),
            'daily' => $now->format('Y-m-d'),
            'never' => 'all',
            default => $now->format('Y'), // yearly
        };
    }

    protected function resetMode(): string
    {
        return (string) $this->config->get(
            'moe-identifiers.document_number.sequence.reset',
            'yearly'
        );
    }

    protected function table(): string
    {
        return (string) $this->config->get(
            'moe-identifiers.document_number.sequence.table',
            'moe_document_sequences'
        );
    }
}
