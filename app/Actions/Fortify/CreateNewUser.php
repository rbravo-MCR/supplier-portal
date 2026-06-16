<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id,status,active'],
            'username' => ['required', 'string', 'lowercase', 'alpha_dash:ascii', 'max:255', 'unique:users,username'],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => filled($input['email'] ?? null) ? $input['email'] : null,
            'password' => $input['password'],
            'supplier_id' => $input['supplier_id'],
            'role' => 'supplier_admin',
            'status' => 'inactive',
        ]);
    }
}
