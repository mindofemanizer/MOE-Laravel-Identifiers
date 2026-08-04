<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Moe\Identifiers\Concerns\HasPublicId;

/*
|--------------------------------------------------------------------------
| Model uji (didefinisikan inline agar tidak mengotori src)
|--------------------------------------------------------------------------
*/

class SqidsArticle extends Model
{
    use HasPublicId;

    protected $table = 'sqids_articles';
    public $timestamps = false;
    protected $guarded = [];
    // driver default = sqids (dari config)
}

class UuidWidget extends Model
{
    use HasPublicId;

    protected $table = 'uuid_widgets';
    public $timestamps = false;
    protected $guarded = [];

    protected string $publicIdDriver = 'uuid';
    // kolom default 'public_id' -> uji regression rekursi accessor
}

class UlidGadget extends Model
{
    use HasPublicId;

    protected $table = 'ulid_gadgets';
    public $timestamps = false;
    protected $guarded = [];

    protected string $publicIdDriver = 'ulid';
    protected string $publicIdColumn = 'ulid';
}

beforeEach(function () {
    foreach (['sqids_articles', 'uuid_widgets', 'ulid_gadgets'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('sqids_articles', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
    });

    Schema::create('uuid_widgets', function (Blueprint $table) {
        $table->id();
        $table->uuid('public_id')->nullable()->unique();
        $table->string('name')->nullable();
    });

    Schema::create('ulid_gadgets', function (Blueprint $table) {
        $table->id();
        $table->ulid('ulid')->nullable()->unique();
        $table->string('name')->nullable();
    });
});

/*
|--------------------------------------------------------------------------
| Regression: trait harus bisa dipasang tanpa fatal error
|--------------------------------------------------------------------------
| Bila resolveRouteBindingQuery() bentrok signature dengan Model, atau
| accessor uuid rekursif, test di bawah akan gagal/crash.
*/

it('driver sqids: public_id adalah encode dari id, bukan id mentah', function () {
    $a = SqidsArticle::create(['title' => 'Halo']);

    expect($a->public_id)->toBeString()
        ->and($a->public_id)->not->toBe((string) $a->id)
        ->and($a->getRouteKey())->toBe($a->public_id);
});

it('driver sqids: resolveRouteBinding menemukan model dari public_id', function () {
    $a = SqidsArticle::create(['title' => 'Halo']);

    $found = (new SqidsArticle())->resolveRouteBinding($a->public_id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($a->id);
});

it('driver sqids: kode sampah pada binding menghasilkan null', function () {
    SqidsArticle::create(['title' => 'Halo']);

    $found = (new SqidsArticle())->resolveRouteBinding('!!!invalid!!!');

    expect($found)->toBeNull();
});

it('driver sqids: findByPublicId bekerja', function () {
    $a = SqidsArticle::create(['title' => 'X']);

    expect(SqidsArticle::findByPublicId($a->public_id)?->id)->toBe($a->id);
});

it('driver uuid: kolom public_id terisi otomatis (tanpa rekursi accessor)', function () {
    $w = UuidWidget::create(['name' => 'Roda']);

    // Regression BUG rekursi: mengakses accessor pada kolom bernama sama.
    expect($w->public_id)->toBeString()
        ->and(strlen($w->public_id))->toBe(36) // panjang uuid
        ->and($w->getRouteKey())->toBe($w->public_id);

    $found = (new UuidWidget())->resolveRouteBinding($w->public_id);
    expect($found?->id)->toBe($w->id);
});

it('driver ulid: kolom kustom terisi otomatis', function () {
    $g = UlidGadget::create(['name' => 'Gawai']);

    expect($g->public_id)->toBeString()
        ->and($g->getAttribute('ulid'))->toBe($g->public_id)
        ->and($g->getRouteKey())->toBe($g->public_id);

    $found = (new UlidGadget())->resolveRouteBinding($g->public_id);
    expect($found?->id)->toBe($g->id);
});

it('resolveRouteBinding dengan field eksplisit menghormati field', function () {
    $a = SqidsArticle::create(['title' => 'Judul Unik']);

    $found = (new SqidsArticle())->resolveRouteBinding('Judul Unik', 'title');

    expect($found?->id)->toBe($a->id);
});
