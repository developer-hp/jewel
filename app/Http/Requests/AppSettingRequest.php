<?php

namespace App\Http\Requests;

use App\Models\AppSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppSettingRequest extends FormRequest
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
        $image = ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml', 'max:1024'];

        return [
            'app_name' => ['required', 'string', 'max:60'],
            'media_disk' => ['required', Rule::in(array_keys(AppSetting::MEDIA_DISKS))],

            'firm_name' => ['nullable', 'string', 'max:100'],
            'firm_city' => ['nullable', 'string', 'max:100'],
            'firm_phone' => ['nullable', 'string', 'max:30'],

            'angadiya_columns' => ['required', 'integer', 'min:1', 'max:6'],
            'angadiya_slip_height_mm' => ['required', 'numeric', 'min:20', 'max:200'],

            'logo' => $image,
            'logo_dark' => $image,
            'logo_small' => $image,

            'remove_logo' => ['boolean'],
            'remove_logo_dark' => ['boolean'],
            'remove_logo_small' => ['boolean'],

            'sidebar_user_bg_from' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_user_bg_to' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_user_text_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sidebar_user_bg_from.regex' => 'Pick a colour in #rrggbb form.',
            'sidebar_user_bg_to.regex' => 'Pick a colour in #rrggbb form.',
            'sidebar_user_text_color.regex' => 'Pick a colour in #rrggbb form.',
            'logo.max' => 'The logo may not be larger than 1 MB.',
            'logo_dark.max' => 'The logo may not be larger than 1 MB.',
            'logo_small.max' => 'The logo may not be larger than 1 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'logo' => 'logo',
            'logo_dark' => 'dark-background logo',
            'logo_small' => 'small logo',
            'sidebar_user_bg_from' => 'gradient start colour',
            'sidebar_user_bg_to' => 'gradient end colour',
            'sidebar_user_text_color' => 'text colour',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['remove_logo', 'remove_logo_dark', 'remove_logo_small'] as $flag) {
            $this->merge([$flag => $this->boolean($flag)]);
        }
    }
}
