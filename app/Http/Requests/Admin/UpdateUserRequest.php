<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->canManageUsers() ?? false) && $this->user()->can('update', $this->route('user'));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:80', 'alpha_dash', 'unique:users,username,'.$target->id],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$target->id],
            'role' => ['required', new Enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
