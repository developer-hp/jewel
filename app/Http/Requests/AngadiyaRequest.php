<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AngadiyaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('angadiya') ? 'angadiya.edit' : 'angadiya.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Optional: a one-off party can be typed straight in.
            'supplier_id' => ['nullable', 'exists:suppliers,id'],

            'name' => ['required', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:100'],
            'mobile' => ['required', 'string', 'max:30'],
            'insurance_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'supplier_id' => $this->input('supplier_id') ?: null,
            'name' => trim((string) $this->input('name')),
            'city' => trim((string) $this->input('city')),
            'mobile' => trim((string) $this->input('mobile')),
            'remark' => $this->filled('remark') ? trim($this->input('remark')) : null,
        ]);
    }
}
