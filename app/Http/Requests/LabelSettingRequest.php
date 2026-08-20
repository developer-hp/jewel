<?php

namespace App\Http\Requests;

use App\Models\LabelSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LabelSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('label_setting.edit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop_name' => ['nullable', 'string', 'max:60'],

            'tag_width_mm' => ['required', 'numeric', 'min:10', 'max:300'],
            'tag_height_mm' => ['required', 'numeric', 'min:5', 'max:300'],
            'margin_mm' => ['required', 'numeric', 'min:0', 'max:20'],
            'font_size_pt' => ['required', 'numeric', 'min:3', 'max:24'],

            'show_gross' => ['boolean'],
            'show_net' => ['boolean'],
            'show_purity' => ['boolean'],
            'show_huid' => ['boolean'],
            'show_stone' => ['boolean'],
            'show_diamond' => ['boolean'],
            'show_extra_charges' => ['boolean'],
            'show_shop_name' => ['boolean'],

            'qr_enabled' => ['boolean'],
            'qr_content' => ['required', Rule::in(array_keys(LabelSetting::QR_CONTENTS))],
            'qr_size_mm' => ['required', 'numeric', 'min:5', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // A QR taller than the tag silently overflows the page box in dompdf,
            // producing a clipped, unscannable code.
            $height = (float) $this->input('tag_height_mm');
            $margin = (float) $this->input('margin_mm');
            $qr = (float) $this->input('qr_size_mm');

            $usable = max(0, $height - (2 * $margin));

            if ($this->boolean('qr_enabled') && $qr > $usable) {
                $validator->errors()->add(
                    'qr_size_mm',
                    sprintf('The QR must fit inside the tag height less margins (max %.2f mm).', $usable)
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'show_gross', 'show_net', 'show_purity', 'show_huid', 'show_stone', 'show_diamond',
            'show_extra_charges', 'show_shop_name', 'qr_enabled',
        ] as $flag) {
            $this->merge([$flag => $this->boolean($flag)]);
        }
    }
}
