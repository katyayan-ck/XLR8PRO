<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class PersonContactUserProvider extends EloquentUserProvider
{
    /**
     * Called by Password Broker: find user where email = $identifier.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        // Handle the password reset "email" lookup specially
        if (isset($credentials['email']) && !isset($credentials['password'])) {
            return $this->createModel()
                ->whereEmail($credentials['email'])  // uses our scope
                ->first();
        }

        // Normal login — delegate to parent (username + password)
        return parent::retrieveByCredentials($credentials);
    }
}
