<?php

declare(strict_types=1);

namespace App\Http\Requests\Discord;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

final class SyncDiscordActivityRoomRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Refuse the room sync outside the activity test flag before validating the snapshot.
     */
    #[Override]
    public function validateResolved(): void
    {
        if (! $this->boolean('test')) {
            abort(404);
        }

        parent::validateResolved();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'string', 'regex:/^[0-9]{1,32}$/'],
            'channel_id' => ['nullable', 'string', 'regex:/^[0-9]{1,32}$/'],
            'guild_id' => ['nullable', 'string', 'regex:/^[0-9]{1,32}$/'],
            'participants' => ['present', 'array', 'max:25'],
            'participants.*.id' => ['required', 'string', 'regex:/^[0-9]{1,32}$/', 'distinct'],
            'participants.*.username' => ['required', 'string', 'max:32'],
            'participants.*.global_name' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Get the validated activity snapshot.
     *
     * @return array{instance_id: string, channel_id: string|null, guild_id: string|null, participants: list<array{id: string, username: string, global_name: string|null}>}
     */
    public function snapshot(): array
    {
        $validated = $this->validated();

        return [
            'instance_id' => $validated['instance_id'],
            'channel_id' => $validated['channel_id'] ?? null,
            'guild_id' => $validated['guild_id'] ?? null,
            'participants' => array_map(
                static fn (array $participant): array => [
                    'id' => $participant['id'],
                    'username' => $participant['username'],
                    'global_name' => $participant['global_name'] ?? null,
                ],
                $validated['participants'],
            ),
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
            'instance_id.regex' => 'Enter a Discord activity instance id.',
            'participants.max' => 'A test room can list at most 25 players.',
        ];
    }
}
