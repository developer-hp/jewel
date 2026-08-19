<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('supplier') ? 'supplier.edit' : 'supplier.create');
    }

    /**
     * Only the name is required — the rest are captured as they become known.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('supplier')?->id;

        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('suppliers', 'name')->ignore($id)->withoutTrashed(),
            ],
            'short_name' => [
                'nullable', 'string', 'max:50',
                Rule::unique('suppliers', 'short_name')->ignore($id)->withoutTrashed(),
            ],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A supplier with this name already exists.',
            'short_name.unique' => 'Another supplier already uses this short name.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            // Blank optional fields are stored as null, not empty strings, so the
            // unique rule on short_name does not collide across blank entries.
            'short_name' => $this->filled('short_name') ? trim($this->input('short_name')) : null,
            'city' => $this->filled('city') ? trim($this->input('city')) : null,
            'address' => $this->filled('address') ? trim($this->input('address')) : null,
            'phone' => $this->filled('phone') ? trim($this->input('phone')) : null,
        ]);
    }
}
