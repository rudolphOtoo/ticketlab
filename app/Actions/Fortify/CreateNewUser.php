<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    /**
     * Validate and create a newly registered organization and admin user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'organization_name' => ['nullable', 'string', 'max:255'],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $orgName = trim((string) ($input['organization_name'] ?? ''));
            if ($orgName === '') {
                $orgName = $input['name'] . "'s Organization";
            }

            $baseSlug = Str::slug($orgName) ?: 'org';
            $slug = $baseSlug;
            $counter = 1;
            while (Organization::query()->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $organization = Organization::create([
                'name' => $orgName,
                'slug' => $slug,
                'subdivision_label' => 'Subdivision',
            ]);

            $user = new User([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->assignMembership('admin', $organization->id);

            return $user;
        });
    }
}
