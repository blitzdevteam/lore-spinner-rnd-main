<?php

declare(strict_types=1);

namespace App\Http\Requests\Writer\Authentication;

use App\Support\PasswordRequirements;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('writers', 'email')],
            'password' => ['required', 'confirmed', ...PasswordRequirements::rules()],
        ];
    }
}
