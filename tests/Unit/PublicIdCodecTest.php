<?php

use MOE\Identifiers\Support\PublicIdCodec;

it('encode lalu decode kembali ke id yang sama (roundtrip)', function () {
    /** @var PublicIdCodec $codec */
    $codec = app(PublicIdCodec::class);

    foreach ([1, 2, 3, 42, 1000, 999999] as $id) {
        $code = $codec->encode($id);

        expect($code)->toBeString()
            ->and(strlen($code))->toBeGreaterThanOrEqual(8)
            ->and($codec->decode($code))->toBe($id);
    }
});

it('menghasilkan kode tak-berurutan untuk id berurutan', function () {
    /** @var PublicIdCodec $codec */
    $codec = app(PublicIdCodec::class);

    $a = $codec->encode(1);
    $b = $codec->encode(2);
    $c = $codec->encode(3);

    expect($a)->not->toBe($b)
        ->and($b)->not->toBe($c);
});

it('menolak kode sampah dengan mengembalikan null', function () {
    /** @var PublicIdCodec $codec */
    $codec = app(PublicIdCodec::class);

    expect($codec->decode('!!!not-valid!!!'))->toBeNull();
});
