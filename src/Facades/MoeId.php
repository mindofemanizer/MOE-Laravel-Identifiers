<?php

declare(strict_types=1);

namespace MOE\Identifiers\Facades;

use Illuminate\Support\Facades\Facade;
use MOE\Identifiers\Support\PublicIdCodec;

/**
 * @method static string encode(int $id)
 * @method static int|null decode(string $code)
 * @method static string driver()
 *
 * @see \MOE\Identifiers\Support\PublicIdCodec
 */
class MoeId extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PublicIdCodec::class;
    }
}
