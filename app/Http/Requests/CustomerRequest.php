<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('customer') ? 'customer.edit' : 'customer.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/\d/', $this->uniqueNumber()],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * The number is the customer's identity, and it is matched on digits only — so
     * uniqueness has to be checked against the derived key rather than the typed
     * text, or "9712 406367" would slip in alongside "9712406367".
     *
     * Written as a closure so the failure lands on the `phone` field the form shows,
     * instead of on a hidden key the user never typed.
     */
    private function uniqueNumber(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $key = Customer::phoneKey(is_string($value) ? $value : null);

            if ($key === '') {
                return;
            }

            $clash = Customer::query()
                ->where('phone_key', $key)
                ->whereKeyNot($this->route('customer')?->id)
                ->first();

            if ($clash) {
                $fail("This number already belongs to {$clash->name}.");
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => 'Enter a phone number containing at least one digit.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
