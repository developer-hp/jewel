<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesOrderReference;
use App\Models\Voucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoucherRequest extends FormRequest
{
    use ResolvesOrderReference;

    public function authorize(): bool
    {
        return $this->user()->can($this->route('voucher') ? 'voucher.edit' : 'voucher.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'voucher_date' => ['required', 'date'],
            'sales_person_id' => ['required', 'exists:sales_persons,id'],
            'payment_mode' => ['required', Rule::in(array_keys(Voucher::MODES))],

            'order_reference' => $this->orderReferenceRules(),
            'direction' => ['nullable', 'in:in,out'],
            'order_form_id' => ['nullable', 'exists:order_forms,id'],

            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeOrderReference();
    }
}
