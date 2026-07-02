<?php

use Illuminate\Support\Carbon;
use MOE\Identifiers\Support\NumberFormatter;

beforeEach(function () {
    // Migrasi tabel counter untuk token {SEQ}.
    $this->loadLaravelMigrations();
    $this->artisan('migrate')->run();
    Carbon::setTestNow(Carbon::create(2026, 7, 3, 10, 0, 0));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('mengganti token tanggal dengan benar', function () {
    /** @var NumberFormatter $f */
    $f = app(NumberFormatter::class);

    expect($f->build('INV-{Y}-{m}-{d}', 'invoices'))->toBe('INV-2026-07-03')
        ->and($f->build('X-{Ymd}', 'invoices'))->toBe('X-20260703')
        ->and($f->build('Y-{y}', 'invoices'))->toBe('Y-26');
});

it('membuat segmen RAND dengan panjang benar dan alfabet non-ambigu', function () {
    /** @var NumberFormatter $f */
    $f = app(NumberFormatter::class);

    $out = $f->build('ORD-{RAND:6}', 'orders');

    expect($out)->toStartWith('ORD-')
        ->and(strlen($out))->toBe(10);

    // Cek hanya segmen acak (setelah prefix "ORD-"), bukan prefix-nya.
    $segment = substr($out, 4);

    expect(strlen($segment))->toBe(6)
        ->and($segment)->not->toContain('0')
        ->and($segment)->not->toContain('1')
        ->and($segment)->not->toContain('I')
        ->and($segment)->not->toContain('L')
        ->and($segment)->not->toContain('O')
        ->and($segment)->not->toContain('U');
});

it('token SEQ bertambah gap-safe dan di-pad', function () {
    /** @var NumberFormatter $f */
    $f = app(NumberFormatter::class);

    expect($f->build('INV-{Y}-{SEQ:5}', 'invoices'))->toBe('INV-2026-00001')
        ->and($f->build('INV-{Y}-{SEQ:5}', 'invoices'))->toBe('INV-2026-00002')
        ->and($f->build('INV-{Y}-{SEQ:5}', 'invoices'))->toBe('INV-2026-00003');
});

it('melempar error untuk token tidak dikenal', function () {
    /** @var NumberFormatter $f */
    $f = app(NumberFormatter::class);

    $f->build('BAD-{NOPE}', 'x');
})->throws(\MOE\Identifiers\Exceptions\IdentifierException::class);
