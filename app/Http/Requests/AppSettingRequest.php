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
            'firm_website' => ['nullable', 'string', 'max:150'],
            'firm_office_phone' => ['nullable', 'string', 'max:30'],

            'angadiya_columns' => ['required', 'integer', 'min:1', 'max:6'],
            'angadiya_slip_height_mm' => ['required', 'numeric', 'min:20', 'max:200'],

            'hallmark_next_lot_no' => ['required', 'integer', 'min:1', 'max:99999999'],

            // All optional: the Appearance form is submitted whole, and a new
            // required field here silently breaks every caller that posts it.
            'repair_ref_prefix' => ['nullable', 'string', 'alpha_num', 'max:10'],
            'repair_next_ref_no' => ['nullable', 'integer', 'min:1', 'max:99999999'],
            'repair_contact_no' => ['nullable', 'string', 'max:30'],
            'repair_metal_type_id' => ['nullable', 'exists:metal_types,id'],
            'repair_purity_id' => ['nullable', 'exists:purities,id'],
            'repair_terms' => ['nullable', 'string', 'max:2000'],

            'logo' => $image,
            'logo_dark' => $image,
            'logo_small' => $image,

            'remove_logo' => ['boolean'],
            'remove_logo_dark' => ['boolean'],
            'remove_logo_small' => ['boolean'],

            'sidebar_user_bg_from' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_user_bg_to' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_user_text_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],

            // Nullable: blank means "leave the table header to the theme".
            'table_header_bg_light' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'table_header_bg_dark' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
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
            'table_header_bg_light.regex' => 'Pick a colour in #rrggbb form.',
            'table_header_bg_dark.regex' => 'Pick a colour in #rrggbb form.',
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

        // Ticking "use the theme default" clears the colour rather than storing one.
        foreach (['light', 'dark'] as $mode) {
            if ($this->boolean("table_header_default_{$mode}")) {
                $this->merge(["table_header_bg_{$mode}" => null]);
            }
        }
    }
}
