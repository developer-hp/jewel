<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('purity') ? 'purity.edit' : 'purity.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'metal_type_id' => ['required', 'exists:metal_types,id'],
            'name' => [
                'required', 'string', 'max:50',
                // Purity names repeat across metals (22K gold and 22K antique), so
                // uniqueness is scoped to the metal type.
                Rule::unique('purities', 'name')
                    ->where('metal_type_id', $this->input('metal_type_id'))
                    ->ignore($this->route('purity')?->id)
                    ->withoutTrashed(),
            ],
            'touch' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_per_grams' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This purity already exists for the selected metal type.',
            'default_per_grams.min' => 'The rate basis must be greater than zero.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->input('sort_order') ?: 0,
        ]);
    }
}
