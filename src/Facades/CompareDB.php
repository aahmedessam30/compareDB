<?php

namespace Essam\CompareDB\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static compare()
 */
class CompareDB extends Facade
{
    public static function getFacadeAccessor(): string
    {
        return 'compareDB';
    }
}
