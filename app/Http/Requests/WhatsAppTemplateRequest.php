<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WhatsAppTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('app_setting.edit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Meta's own naming rule: lower case, digits and underscores.
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            // "en" and "en_US" are different templates as far as Meta is concerned,
            // so this has to match the approved one exactly.
            'language' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2}(_[A-Z]{2})?$/'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'A template name may only contain lower-case letters, digits and underscores.',
            'language.regex' => 'Use a code like en or en_US, exactly as the template is registered with Meta.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // An unticked switch arrives absent, not false, so the hidden 0 alongside it
        // is what actually turns the template off.
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
