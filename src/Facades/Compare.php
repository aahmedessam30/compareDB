<?php

namespace Essam\CompareDB\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static getInformation()
 */
class Compare extends Facade
{
    public static function getFacadeAccessor(): string
    {
        return 'compare';
    }
}
