<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            // Self-registered accounts are buyers; never inherit the legacy 'gate_usher' column default.
            'role' => 'attendee',
        ]);

        // Self-registered accounts are buyers with no tenant scope, whatever else the request carried.
        $user->forceFill(['role' => 'attendee', 'organization_id' => null, 'subdivision_id' => null])->save();

        return $user;
    }
}
