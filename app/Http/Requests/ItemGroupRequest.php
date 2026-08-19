<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('item_group') ? 'item_group.edit' : 'item_group.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = $this->route('item_group');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('item_groups', 'name')->ignore($group?->id)->withoutTrashed()],
            'prefix' => ['required', 'string', 'alpha_num', 'max:10', Rule::unique('item_groups', 'prefix')->ignore($group?->id)->withoutTrashed()],
            'code_padding' => ['required', 'integer', 'min:1', 'max:10'],
            'metal_type_id' => ['nullable', 'exists:metal_types,id'],
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
            'prefix.unique' => 'Another group already uses this prefix — item codes would collide.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'prefix' => strtoupper((string) $this->input('prefix')),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->input('sort_order') ?: 0,
            'code_padding' => $this->input('code_padding') ?: 4,
        ]);
    }
}
