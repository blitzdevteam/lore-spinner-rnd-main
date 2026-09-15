<?php

declare(strict_types=1);

namespace App\Http\Requests\User\Game;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreGameRequest extends FormRequest
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
            'story_id' => ['required', 'exists:stories,id'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
