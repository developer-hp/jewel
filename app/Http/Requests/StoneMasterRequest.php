<?php

namespace App\Http\Requests;

use App\Models\StoneMaster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoneMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('stone') ? 'stone.edit' : 'stone.create');
    }

    /**
     * The kind comes from the route (`stones.*` vs `diamonds.*`), never from the
     * request body — otherwise a diamond could be posted into the stone screen.
     */
    public function kind(): string
    {
        return $this->routeIs('diamonds.*')
            ? StoneMaster::KIND_DIAMOND
            : StoneMaster::KIND_STONE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('stone')?->id;

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('stone_masters', 'name')
                    ->where('kind', $this->kind())
                    ->ignore($id)
                    ->withoutTrashed(),
            ],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('stone_masters', 'code')->ignore($id)->withoutTrashed()],
            'shape' => ['nullable', 'string', 'max:50'],
            'quality' => ['nullable', 'string', 'max:50'],
            'colour' => ['nullable', 'string', 'max:50'],
            'size' => ['nullable', 'string', 'max:50'],
            'rate_unit' => ['required', Rule::in(array_keys(StoneMaster::RATE_UNITS))],
            'default_rate' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'code' => $this->input('code') ?: null,
        ]);
    }
}
