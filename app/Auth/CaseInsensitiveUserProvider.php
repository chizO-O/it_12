<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;

class CaseInsensitiveUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        if (is_string($credentials['email'] ?? null)) {
            $email = strtolower(trim($credentials['email']));
            $credentials['email'] = fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]);
        }

        return parent::retrieveByCredentials($credentials);
    }
}
