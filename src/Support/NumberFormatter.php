<?php

namespace MOE\Identifiers\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use MOE\Identifiers\Exceptions\IdentifierException;

/**
 * Mengubah template nomor dokumen menjadi string final.
 *
 * Token yang didukung:
 *   {Y}        tahun 4 digit           -> 2026
 *   {y}        tahun 2 digit           -> 26
 *   {m}        bulan 2 digit           -> 07
 *   {d}        tanggal 2 digit         -> 03
 *   {Ymd}      gabungan tanggal        -> 20260703
 *   {RAND:n}   n karakter acak CSPRNG  -> K7M2QX
 *   {SEQ:n}    nomor urut gap-safe     -> 00042
 *
 * Token {SEQ} membutuhkan SequenceManager (di-resolve saat build).
 */
class NumberFormatter
{
    public function __construct(
        protected Config $config,
        protected SequenceManager $sequences,
    ) {
    }

    /**
     * Bangun nomor final dari template.
     *
     * @param  string       $format       Template, mis. "INV-{Y}-{SEQ:5}".
     * @param  string       $sequenceKey  Kunci counter untuk token {SEQ}
     *                                     (umumnya nama tabel/model).
     * @param  Carbon|null  $now          Waktu acuan (default: sekarang).
     */
    public function build(string $format, string $sequenceKey, ?Carbon $now = null): string
    {
        $now ??= Carbon::now();

        // Token tanggal sederhana (tanpa argumen).
        $replacements = [
            '{Y}' => $now->format('Y'),
            '{y}' => $now->format('y'),
            '{m}' => $now->format('m'),
            '{d}' => $now->format('d'),
            '{Ymd}' => $now->format('Ymd'),
        ];

        $result = strtr($format, $replacements);

        // Token {RAND:n}
        $result = preg_replace_callback(
            '/\{RAND:(\d+)\}/',
            fn (array $m): string => $this->randomSegment((int) $m[1]),
            $result,
        );

        // Token {SEQ:n}
        $result = preg_replace_callback(
            '/\{SEQ:(\d+)\}/',
            function (array $m) use ($sequenceKey, $now): string {
                $pad = (int) $m[1];
                $period = $this->sequences->periodFor($now);
                $next = $this->sequences->next($sequenceKey, $period);

                return str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
            },
            $result,
        );

        if ($result === null) {
            throw new IdentifierException("Gagal membangun nomor dokumen dari format: {$format}");
        }

        // Deteksi token yang belum tergantikan (typo pada template).
        if (preg_match('/\{[A-Za-z]+(?::\d*)?\}/', $result, $leftover)) {
            throw new IdentifierException(
                "Token tidak dikenal pada format nomor dokumen: {$leftover[0]}"
            );
        }

        return $result;
    }

    /**
     * Segmen acak sepanjang $length memakai alfabet non-ambigu + CSPRNG.
     */
    public function randomSegment(int $length): string
    {
        if ($length < 1) {
            throw new IdentifierException('Panjang {RAND} harus >= 1.');
        }

        $alphabet = (string) $this->config->get(
            'moe-identifiers.document_number.random_alphabet',
            '23456789ABCDEFGHJKMNPQRSTVWXYZ'
        );

        $max = strlen($alphabet) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }
}
