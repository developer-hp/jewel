<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('sales_person') ? 'sales_person.edit' : 'sales_person.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('sales_person')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('sales_persons', 'name')->ignore($id)->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
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
