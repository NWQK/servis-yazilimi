<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;

class AccountUserProvider extends EloquentUserProvider
{
    // Customer rows preserve historical vehicle/invoice IDs, but are never login accounts.
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->where('type', '<>', 'client');
    }
}
