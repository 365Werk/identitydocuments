<?php

namespace werk365\IdentityDocuments\Facades;

use Illuminate\Support\Facades\Facade;

class IdentityDocuments extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'identitydocuments';
    }
}
