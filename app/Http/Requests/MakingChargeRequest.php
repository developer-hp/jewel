<?php

namespace App\Http\Requests;

use App\Models\MakingCharge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MakingChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('making_charge') ? 'making_charge.edit' : 'making_charge.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('making_charges', 'code')->ignore($this->route('making_charge')?->id)->withoutTrashed(),
            ],
            'name' => ['required', 'string', 'max:100'],
            'charge_type' => ['required', Rule::in(array_keys(MakingCharge::TYPES))],
            // A percentage over 100 is almost certainly a typo for a per-gram rate.
            'rate' => [
                'required', 'numeric', 'min:0',
                Rule::when($this->input('charge_type') === MakingCharge::TYPE_PERCENTAGE, ['max:100']),
            ],
            'weight_basis' => [
                Rule::requiredIf($this->input('charge_type') === MakingCharge::TYPE_PER_GRAM),
                'nullable',
                Rule::in(array_keys(MakingCharge::WEIGHT_BASES)),
            ],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate.max' => 'A percentage making charge cannot exceed 100%.',
            'weight_basis.required' => 'Choose whether the per-gram rate applies to net or gross weight.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper((string) $this->input('code')),
            'is_active' => $this->boolean('is_active'),
            // Only per-gram charges carry a weight basis.
            'weight_basis' => $this->input('charge_type') === MakingCharge::TYPE_PER_GRAM
                ? $this->input('weight_basis')
                : null,
        ]);
    }
}
