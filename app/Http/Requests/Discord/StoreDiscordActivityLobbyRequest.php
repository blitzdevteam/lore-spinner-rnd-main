<?php

declare(strict_types=1);

namespace App\Http\Requests\Discord;

use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Models\Story;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDiscordActivityLobbyRequest extends FormRequest
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
            'story' => ['required', 'string', Rule::exists(Story::class, 'slug')],
            'mode' => ['required', Rule::enum(MultiplayerLobbyModeEnum::class)],
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
            'story.required' => 'Choose a story for the voice channel.',
            'story.exists' => 'That story could not be found.',
            'mode.required' => 'Choose a multiplayer mode.',
        ];
    }
}
