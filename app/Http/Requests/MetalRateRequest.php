<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetalRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('rate') ? 'metal_rate.edit' : 'metal_rate.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purity_id' => ['required', 'exists:purities,id'],
            'effective_date' => [
                'required', 'date',
                // One rate per purity per day; editing the day's rate replaces it.
                Rule::unique('metal_rates', 'effective_date')
                    ->where('purity_id', $this->input('purity_id'))
                    ->ignore($this->route('rate')?->id),
            ],
            'rate' => ['required', 'numeric', 'min:0'],
            'per_grams' => ['required', 'numeric', 'min:0.001', 'max:99999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'effective_date.unique' => 'A rate for this purity on this date already exists — edit that one instead.',
        ];
    }
}
