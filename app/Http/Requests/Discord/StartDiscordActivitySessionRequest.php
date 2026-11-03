<?php

declare(strict_types=1);

namespace App\Http\Requests\Discord;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StartDiscordActivitySessionRequest extends FormRequest
{
    public const string INSTANCE_ID_PATTERN = '/^[A-Za-z0-9_-]{1,128}$/';

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
            'code' => ['required', 'string', 'max:255'],
            'instance_id' => ['required', 'string', 'regex:'.self::INSTANCE_ID_PATTERN],
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
            'code.required' => 'Discord did not send a sign-in code.',
            'instance_id.required' => 'Open this activity from a voice channel.',
            'instance_id.regex' => 'Discord sent an activity id we do not recognise.',
        ];
    }
}
