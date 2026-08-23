<?php

declare(strict_types=1);

namespace App\Http\Requests\User\Multiplayer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class JoinMultiplayerLobbyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $roomCode = $this->input('room_code');
        $email = $this->input('email');
        $firstName = $this->input('first_name');
        $lastName = $this->input('last_name');

        $payload = [];

        if (is_string($roomCode)) {
            $payload['room_code'] = Str::upper(trim($roomCode));
        }

        if (is_string($email)) {
            $payload['email'] = Str::lower(trim($email));
        }

        if (is_string($firstName)) {
            $payload['first_name'] = trim($firstName);
        }

        if (is_string($lastName)) {
            $payload['last_name'] = trim($lastName);
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        $isGuest = $this->user('user') === null;

        return [
            'room_code' => ['required', 'string', 'regex:/^TRS-[A-Z0-9]{6}$/'],
            'email' => [$isGuest ? 'required' : 'nullable', 'email', 'max:255'],
            'first_name' => [$isGuest ? 'required' : 'nullable', 'string', 'min:2', 'max:64'],
            'last_name' => [$isGuest ? 'required' : 'nullable', 'string', 'min:2', 'max:64'],
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
            'room_code.required' => 'Enter a room code to join a lobby.',
            'room_code.regex' => 'Enter a valid room code.',
            'email.required' => 'Enter your email to play as a guest.',
            'first_name.required' => 'Enter your first name to play as a guest.',
            'last_name.required' => 'Enter your last name to play as a guest.',
        ];
    }
}
