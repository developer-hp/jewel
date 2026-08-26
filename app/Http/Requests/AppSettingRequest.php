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

            'order_ref_prefix' => ['nullable', 'string', 'alpha_num', 'max:10'],
            'order_next_ref_no' => ['nullable', 'integer', 'min:1', 'max:99999999'],
            'order_contact_no' => ['nullable', 'string', 'max:30'],
            'order_terms' => ['nullable', 'string', 'max:2000'],

            'og_estimate_ref_prefix' => ['nullable', 'string', 'alpha_num', 'max:10'],
            'og_estimate_next_ref_no' => ['nullable', 'integer', 'min:1', 'max:99999999'],

            'voucher_ref_prefix' => ['nullable', 'string', 'alpha_num', 'max:10'],
            'voucher_next_ref_no' => ['nullable', 'integer', 'min:1', 'max:99999999'],

            'item_estimate_ref_prefix' => ['nullable', 'string', 'alpha_num', 'max:10'],
            'item_estimate_next_ref_no' => ['nullable', 'integer', 'min:1', 'max:99999999'],
            // Snapshotted onto each estimate when it is saved, so changing it here
            // never rewrites a quote already given.
            'gst_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'cash_entry_ref_prefix' => ['nullable', 'string', 'alpha_num', 'max:10'],
            'cash_entry_next_ref_no' => ['nullable', 'integer', 'min:1', 'max:99999999'],

            'supplier_order_next_form_no' => ['nullable', 'integer', 'min:1', 'max:99999999'],
            'supplier_order_header' => ['nullable', 'string', 'max:150'],

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

            // The sections to SHOW; what gets stored is the complement. Keys are
            // checked against the registry, so a stale or invented one is refused.
            'settings_cache_enabled' => ['boolean'],
            'auto_opening_enabled' => ['boolean'],
            'dashboard_sections' => ['sometimes', 'array'],
            'dashboard_sections.*' => [Rule::in(array_column(config('dashboard', []), 'key'))],
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
        // Only touched when the form actually sent it. Defaulting it to an empty
        // array here would mean any save that omits the field hides every section.
        //
        // The form posts one empty value so "none ticked" still arrives as an array;
        // drop it before the key rule sees it.
        $this->merge([
            'settings_cache_enabled' => $this->boolean('settings_cache_enabled'),
            'auto_opening_enabled' => $this->boolean('auto_opening_enabled'),
        ]);

        if ($this->has('dashboard_sections')) {
            $this->merge([
                'dashboard_sections' => array_values(array_filter(
                    (array) $this->input('dashboard_sections'),
                    fn ($key) => $key !== '' && $key !== null,
                )),
            ]);
        }

        foreach (['remove_logo', 'remove_logo_dark', 'remove_logo_small'] as $flag) {
            $this->merge([$flag => $this->boolean($flag)]);
        }

        // Ticking "use the theme default" clears the colour rather than storing one.
        foreach (['light', 'dark'] as $mode) {
            if ($this->boolean("table_header_default_{$mode}")) {
                $this->merge(["table_header_bg_{$mode}" => null]);
            }
        }

        $this->normaliseNumbering();
    }

    /**
     * Every prefix, counter and rate column is NOT NULL, but ConvertEmptyStringsToNull
     * turns a cleared box into null on the way in — which reached the database as an
     * error rather than as an answer.
     *
     * A blank prefix is a real choice: no prefix, so the reference prints as a bare
     * number. A blank counter or GST rate is not a choice at all, so the key is dropped
     * and whatever is saved stands, rather than a stray tab wiping a counter.
     */
    private function normaliseNumbering(): void
    {
        $prefixes = ['repair_ref_prefix', 'order_ref_prefix', 'og_estimate_ref_prefix',
            'voucher_ref_prefix', 'item_estimate_ref_prefix', 'cash_entry_ref_prefix'];

        foreach ($prefixes as $field) {
            if ($this->has($field) && blank($this->input($field))) {
                $this->merge([$field => '']);
            }
        }

        $numbers = ['repair_next_ref_no', 'order_next_ref_no', 'og_estimate_next_ref_no',
            'voucher_next_ref_no', 'item_estimate_next_ref_no', 'cash_entry_next_ref_no', 'supplier_order_next_form_no',
            'gst_percent'];

        foreach ($numbers as $field) {
            if ($this->has($field) && blank($this->input($field))) {
                $this->request->remove($field);
            }
        }
    }
}
