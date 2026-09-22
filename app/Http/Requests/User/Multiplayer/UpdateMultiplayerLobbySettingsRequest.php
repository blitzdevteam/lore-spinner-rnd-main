<?php

declare(strict_types=1);

namespace App\Http\Requests\User\Multiplayer;

use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Models\MultiplayerLobby;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMultiplayerLobbySettingsRequest extends FormRequest
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
        $lobby = $this->route('lobby');
        $isRoulette = $lobby instanceof MultiplayerLobby
            && $lobby->mode === MultiplayerLobbyModeEnum::Roulette;

        return [
            'settings' => ['required', 'array:choices_enabled,custom_input_enabled,voice_over_enabled,decision_window_seconds'],
            'settings.choices_enabled' => ['sometimes', 'boolean'],
            'settings.custom_input_enabled' => [Rule::requiredIf($isRoulette), 'nullable', 'boolean'],
            'settings.voice_over_enabled' => ['required', 'boolean'],
            'settings.decision_window_seconds' => ['required', 'integer', Rule::in(MultiplayerLobby::DECISION_WINDOW_SECONDS)],
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
            'settings.decision_window_seconds.in' => 'Choose a countdown of 3, 4, 5, or 6 minutes, or turn it off.',
        ];
    }
}
