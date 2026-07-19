<?php

declare(strict_types=1);

namespace App\Http\Requests\User\Game\Prompt;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StorePromptRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'min:3', 'max:255'],
            'prompt_id' => ['required', 'ulid', 'exists:prompts,id'],
            'suggestion_index' => ['nullable', 'integer', 'between:0,2'],
            'canonical_choice_id' => ['nullable', 'required_with:canonical_path_label', 'string', 'max:128'],
            'canonical_path_label' => ['nullable', 'required_with:canonical_choice_id', 'string', 'in:A,B,C'],
        ];
    }
}
