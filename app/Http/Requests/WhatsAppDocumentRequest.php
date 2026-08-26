<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WhatsAppDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('app_setting.edit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Either a customer off the list, or a number and name typed in.
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'contact_no' => ['nullable', 'string', 'max:30'],
            'customer_name' => ['nullable', 'string', 'max:150'],

            // What goes in "Your ___ is ready".
            'described_as' => ['required', 'string', 'max:60'],

            // Meta accepts up to 100 MB for a document, but a tag or a report is
            // small and a huge upload here is a mistake, not a requirement.
            'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document.mimes' => 'The template attaches a PDF, so the file has to be one.',
            'described_as.required' => 'Say what the document is — it is printed in the message.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // One of the two ways of naming a recipient has to be filled in. Neither
            // is required on its own, so this is the only place that can catch it.
            if (blank($this->input('customer_id')) && blank($this->input('contact_no'))) {
                $validator->errors()->add('contact_no', 'Choose a customer, or type a number to send to.');
            }
        });
    }
}
