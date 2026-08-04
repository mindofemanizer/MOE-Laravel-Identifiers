<?php

declare(strict_types=1);

namespace Moe\Identifiers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Moe\Identifiers\Support\PublicIdCodec;

/**
 * Kebutuhan 1 — Sembunyikan primary key integer pada URL/API publik.
 *
 * PENTING: Ini defense-in-depth (anti-IDOR & anti-enumerasi), BUKAN pengganti
 * authorization. Tetap wajib ada cek kepemilikan (Policy / where user_id) di
 * sisi aplikasi.
 *
 * Driver (dari config moe-identifiers.public_id.driver):
 *  - "sqids" : reversible, tanpa kolom tambahan. Route key = encode(id).
 *  - "uuid"  : kolom acak permanen tersimpan (default kolom: public_id).
 *  - "ulid"  : kolom acak time-sortable tersimpan.
 *
 * Override per-model bila perlu:
 *  - protected string $publicIdDriver = 'uuid';
 *  - protected string $publicIdColumn = 'uuid';
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (Model $model): void {
            /** @var static $model */
            $driver = $model->publicIdDriver();

            if ($driver === 'sqids') {

                return; // sqids tidak butuh kolom; dihitung dari id.
            }

            $column = $model->publicIdColumn();

            if (empty($model->getAttribute($column))) {
                $model->setAttribute(
                    $column,
                    $driver === 'ulid' ? (string) Str::ulid() : (string) Str::uuid()
                );
            }
        });
    }

    /**
     * Nilai Public ID untuk ditampilkan / dipakai di URL.
     *
     * @return string|null
     */
    public function getPublicIdAttribute(): ?string
    {
        $driver = $this->publicIdDriver();

        if ($driver === 'sqids') {
            $key = $this->getKey();

            return $key === null ? null : $this->publicIdCodec()->encode((int) $key);
        }

        // uuid/ulid: baca nilai MENTAH dari kolom. Wajib lewat $this->attributes
        // (bukan getAttribute) agar tidak memicu accessor ini lagi secara
        // rekursif ketika publicIdColumn() kebetulan bernama 'public_id'.
        $column = $this->publicIdColumn();

        if ($column === 'public_id') {

            return $this->getAttributeFromArray($column);
        }

        return $this->getAttribute($column);
    }

    /**
     * Route model binding memakai Public ID, bukan id mentah.
     *
     * @return mixed
     */
    public function getRouteKey(): mixed
    {
        return $this->getPublicIdAttribute();
    }

    /**
     * @return string
     */
    public function getRouteKeyName(): string
    {
        // Nilai sentinel; resolusi sebenarnya ditangani resolveRouteBinding().
        return $this->publicIdColumn();
    }

    /**
     * Resolusi binding dari Public ID kembali ke model.
     *
     * @param mixed $value
     * @param mixed $field
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        // Bila field eksplisit diminta, hormati perilaku default.
        if ($field !== null) {

            return $this->where($field, $value)->first();
        }

        $driver = $this->publicIdDriver();

        if ($driver === 'sqids') {
            $id = $this->publicIdCodec()->decode((string) $value);

            if ($id === null) {

                return null;
            }

            return $this->newPublicIdQuery()
                ->where($this->getKeyName(), $id)
                ->first();
        }

        return $this->newPublicIdQuery()
            ->where($this->publicIdColumn(), $value)
            ->first();
    }

    /**
     * Cari model berdasarkan Public ID (helper eksplisit).
     *
     * @param string $publicId
     * @return static|null
     */
    public static function findByPublicId(string $publicId): ?static
    {
        return (new static())->resolveRouteBinding($publicId);
    }

    /**
     * Query dasar untuk resolusi Public ID.
     *
     * Sengaja TIDAK memakai nama resolveRouteBindingQuery() karena nama itu
     * sudah dipakai Illuminate\Database\Eloquent\Model dengan signature
     * ($query, $value, $field = null) — meng-override-nya dengan signature
     * berbeda akan memicu fatal error "Declaration must be compatible".
     *
     * @return Builder
     */
    protected function newPublicIdQuery(): Builder
    {
        return $this->newQuery();
    }

    /**
     * @return string
     */
    public function publicIdDriver(): string
    {
        return property_exists($this, 'publicIdDriver')
            ? $this->publicIdDriver
            : (string) config('moe-identifiers.public_id.driver', 'sqids');
    }

    /**
     * @return string
     */
    public function publicIdColumn(): string
    {
        return property_exists($this, 'publicIdColumn')
            ? $this->publicIdColumn
            : (string) config('moe-identifiers.public_id.column', 'public_id');
    }

    /**
     * @return PublicIdCodec
     */
    protected function publicIdCodec(): PublicIdCodec
    {
        return app(PublicIdCodec::class);
    }
}
