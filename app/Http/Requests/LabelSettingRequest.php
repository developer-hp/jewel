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
        return $this->user()->can($this->template() ? 'label_setting.edit' : 'label_setting.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60',
                Rule::unique('label_settings', 'name')->ignore($this->template()?->id)],
            'layout' => ['required', Rule::in(array_keys(LabelSetting::LAYOUTS))],

            'shop_name' => ['nullable', 'string', 'max:60'],

            'tag_width_mm' => ['required', 'numeric', 'min:10', 'max:300'],
            'tag_height_mm' => ['required', 'numeric', 'min:5', 'max:300'],
            'margin_mm' => ['required', 'numeric', 'min:0', 'max:20'],
            'font_size_pt' => ['required', 'numeric', 'min:3', 'max:24'],
            'max_stone_rows' => ['required', 'integer', 'min:1', 'max:20'],

            'show_gross' => ['boolean'],
            'show_net' => ['boolean'],
            'show_purity' => ['boolean'],
            'show_huid' => ['boolean'],
            'show_stone' => ['boolean'],
            'show_diamond' => ['boolean'],
            'show_stone_rate' => ['boolean'],
            'show_extra_charges' => ['boolean'],
            'show_oc' => ['boolean'],
            'show_making_charge' => ['boolean'],
            'show_item_name' => ['boolean'],
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

            // The detail layouts stack a row per stone. On the 18 mm stock the
            // standard tag uses they run onto a second page, which wastes a label
            // on every print — so this is refused rather than left to be discovered
            // at the printer.
            $detail = in_array($this->input('layout'), [
                LabelSetting::LAYOUT_STONE_DETAIL,
                LabelSetting::LAYOUT_DIAMOND_DETAIL,
            ], true);

            if ($detail && $height < LabelSetting::DETAIL_MIN_HEIGHT_MM) {
                $validator->errors()->add('tag_height_mm', sprintf(
                    'A %s tag needs at least %d mm of height, or it prints onto a second page.',
                    LabelSetting::LAYOUTS[$this->input('layout')],
                    LabelSetting::DETAIL_MIN_HEIGHT_MM,
                ));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'show_gross', 'show_net', 'show_purity', 'show_huid', 'show_stone', 'show_diamond',
            'show_stone_rate', 'show_extra_charges', 'show_oc', 'show_making_charge',
            'show_item_name', 'show_shop_name', 'qr_enabled',
        ] as $flag) {
            $this->merge([$flag => $this->boolean($flag)]);
        }
    }

    /**
     * The template being edited, or null when one is being created.
     *
     * is_default is deliberately absent from this request: it moves only through
     * the dedicated label-settings.default route, so two defaults cannot be
     * submitted from the form at all.
     */
    private function template(): ?LabelSetting
    {
        $template = $this->route('label_setting');

        return $template instanceof LabelSetting ? $template : null;
    }
}
