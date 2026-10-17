<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;

class AccountUserProvider extends EloquentUserProvider
{
    public function validateCredentials(\Illuminate\Contracts\Auth\Authenticatable $user,array $credentials)
    {
        if($user->type==='employee' && (int)$user->is_active!==1)return false;
        return parent::validateCredentials($user,$credentials);
    }

    public function rehashPasswordIfRequired(\Illuminate\Contracts\Auth\Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false)
    {
        $oldHash = $user->getAuthPassword();
        if (!$force && !$this->hasher->needsRehash($oldHash)) return;

        $column = $user->getAuthPasswordName();
        $newHash = $this->hasher->make($credentials['password']);
        // Automatic rehashing must never overwrite a simultaneous password reset
        // or save unrelated attributes from the login's stale user instance.
        $changed = $this->newModelQuery($user)->whereKey($user->getAuthIdentifier())
            ->where($column, $oldHash)->where('is_active', 1)->update([$column => $newHash]);
        if ($changed) {
            $user->forceFill([$column => $newHash])->syncOriginalAttribute($column);
        }
    }
    // Customer rows preserve historical vehicle/invoice IDs, but are never login accounts.
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->where('type', '<>', 'client');
    }
}
