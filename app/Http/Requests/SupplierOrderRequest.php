<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('supplier_order') ? 'supplier_order.edit' : 'supplier_order.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'order_type_id' => ['required', 'exists:order_types,id'],

            'order_date' => ['required', 'date'],
            'customer_delivery_date' => ['required', 'date', 'after_or_equal:order_date'],
            'followup_date' => ['required', 'date', 'after_or_equal:order_date'],

            // Free text on purpose: whatever the counter writes on the slip, in
            // capitals. No format rule, digits included.
            'order_form_ref' => ['nullable', 'string', 'max:30'],

            'description' => ['nullable', 'string', 'max:255'],
            'size_pcs' => ['nullable', 'string', 'max:50'],
            'sample_desc' => ['nullable', 'string', 'max:255'],
            'order_weight' => ['nullable', 'numeric', 'min:0', 'max:999999.999'],
            'sample_weight' => ['nullable', 'numeric', 'min:0', 'max:999999.999'],
            'special_remarks' => ['nullable', 'string', 'max:2000'],

            'photo' => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'],
            'remove_photo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_type_id.required' => 'Choose the type of work.',
            'customer_delivery_date.after_or_equal' => 'The customer cannot be promised the goods before the order was placed.',
            'followup_date.after_or_equal' => 'There is no sense chasing an order before it was placed.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'order_form_ref' => strtoupper(trim((string) $this->input('order_form_ref'))) ?: null,
            'order_weight' => $this->input('order_weight') === '' ? null : $this->input('order_weight'),
            'sample_weight' => $this->input('sample_weight') === '' ? null : $this->input('sample_weight'),
            'remove_photo' => $this->boolean('remove_photo'),
        ]);
    }
}
