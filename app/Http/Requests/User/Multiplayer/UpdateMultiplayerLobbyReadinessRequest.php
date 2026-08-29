<?php

declare(strict_types=1);

namespace App\Http\Requests\User\Multiplayer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateMultiplayerLobbyReadinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_ready' => ['required', 'boolean'],
        ];
    }

    /**
     * Get custom validation messages for errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_ready.required' => 'Choose whether you are ready to play.',
            'is_ready.boolean' => 'Your ready status must be valid.',
        ];
    }
}
