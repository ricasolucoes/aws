<?php

namespace Aws\Facades;

use Illuminate\Support\Facades\Facade;

class Aws extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'aws';
    }
}
