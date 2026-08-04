<?php

declare(strict_types=1);

namespace Moe\Identifiers\Facades;

use Illuminate\Support\Facades\Facade;
use Moe\Identifiers\Support\PublicIdCodec;

/**
 * @method static string encode(int $id)
 * @method static int|null decode(string $code)
 * @method static string driver()
 *
 * @see \Moe\Identifiers\Support\PublicIdCodec
 */
class MoeId extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PublicIdCodec::class;
    }
}
