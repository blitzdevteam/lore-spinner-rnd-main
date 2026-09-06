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
            'settings' => ['required', 'array:custom_input_enabled,voice_over_enabled,decision_window_seconds'],
            'settings.custom_input_enabled' => [Rule::requiredIf($isRoulette), 'nullable', 'boolean'],
            'settings.voice_over_enabled' => ['required', 'boolean'],
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
            'settings.decision_window_seconds.in' => 'Choose a voting time of 10, 20, or 30 seconds.',
        ];
    }
}
