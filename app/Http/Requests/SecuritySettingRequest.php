<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SecuritySettingRequest extends FormRequest
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
            'single_device_login' => ['boolean'],
            // 0 disables idle logout; the cap keeps it inside a working day.
            'idle_timeout_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'idle_warning_seconds' => ['required', 'integer', 'min:10', 'max:600'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $timeout = (int) $this->input('idle_timeout_minutes');
            $warning = (int) $this->input('idle_warning_seconds');

            // A warning at least as long as the timeout would be on screen from the
            // moment the user stopped typing.
            if ($timeout > 0 && $warning >= $timeout * 60) {
                $validator->errors()->add(
                    'idle_warning_seconds',
                    "The warning must be shorter than the timeout ({$timeout} min)."
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'single_device_login' => $this->boolean('single_device_login'),
            'idle_timeout_minutes' => $this->input('idle_timeout_minutes') ?: 0,
            'idle_warning_seconds' => $this->input('idle_warning_seconds') ?: 60,
        ]);
    }
}
