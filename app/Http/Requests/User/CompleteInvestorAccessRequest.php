<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompleteInvestorAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->investor() instanceof User;
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        $investor = $this->investor();

        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($investor?->id),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function investor(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User && $user->isInvestor() ? $user : null;
    }
}
