<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetalTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('metal_type') ? 'metal_type.edit' : 'metal_type.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('metal_type')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('metal_types', 'name')->ignore($id)->withoutTrashed()],
            'code' => ['required', 'string', 'alpha_num', 'max:20', Rule::unique('metal_types', 'code')->ignore($id)->withoutTrashed()],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            // Null means "use the default template".
            'label_setting_id' => ['nullable', 'integer', 'exists:label_settings,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper((string) $this->input('code')),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->input('sort_order') ?: 0,
        ]);
    }
}
