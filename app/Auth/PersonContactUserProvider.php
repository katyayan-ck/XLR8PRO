<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class PersonContactUserProvider extends EloquentUserProvider
{
    
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        
        if (isset($credentials['email']) && !isset($credentials['password'])) {
            return $this->createModel()
                ->whereEmail($credentials['email']) 
                ->first();
        }
        return parent::retrieveByCredentials($credentials);
    }
}
