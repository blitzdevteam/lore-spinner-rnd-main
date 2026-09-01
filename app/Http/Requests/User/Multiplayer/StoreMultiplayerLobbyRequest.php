<?php

declare(strict_types=1);

namespace App\Http\Requests\User\Multiplayer;

use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMultiplayerLobbyRequest extends FormRequest
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
            'mode' => ['required', Rule::enum(MultiplayerLobbyModeEnum::class)],
            'max_players' => ['required', 'integer', 'between:3,4'],
            'settings' => ['required', 'array:custom_input_enabled,voice_over_enabled,power_ups_per_user,decision_window_seconds'],
            'settings.custom_input_enabled' => ['required', 'boolean'],
            'settings.voice_over_enabled' => ['required', 'boolean'],
            'settings.power_ups_per_user' => ['required', 'integer', 'in:5,10,15'],
            'settings.decision_window_seconds' => ['required', 'integer', 'in:10,20,30'],
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
            'mode.required' => 'Choose a multiplayer mode.',
            'max_players.between' => 'A multiplayer room can have between 3 and 4 players.',
            'settings.required' => 'Choose the lobby settings before creating the room.',
        ];
    }
}
