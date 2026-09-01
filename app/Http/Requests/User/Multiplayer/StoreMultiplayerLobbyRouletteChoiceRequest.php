<?php

declare(strict_types=1);

namespace App\Http\Requests\User\Multiplayer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMultiplayerLobbyRouletteChoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'choice_index' => ['nullable', 'integer', 'between:0,2', 'required_without:proposal', 'prohibited_with:proposal'],
            'proposal' => ['nullable', 'string', 'min:3', 'max:2000', 'required_without:choice_index', 'prohibited_with:choice_index'],
        ];
    }
}
