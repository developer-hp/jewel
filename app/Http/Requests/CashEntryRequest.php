<?php

namespace App\Http\Requests;

use App\Models\CashEntry;
use App\Models\ItemEstimate;
use App\Models\OgEstimate;
use App\Models\Voucher;
use App\Services\CashMath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CashEntryRequest extends FormRequest
{
    /** Resolved once, so after() and the controller do not each reload it. */
    private ItemEstimate|Voucher|null $document = null;

    private bool $documentResolved = false;

    public function authorize(): bool
    {
        return $this->user()->can($this->route('cash_entry') ? 'cash_entry.edit' : 'cash_entry.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('cash_entry')?->id;
        $hasCheque = fn () => (float) $this->input('cheque_amount') > 0;

        return [
            'entry_date' => ['required', 'date'],
            'cash_drawer_id' => ['required', Rule::exists('cash_drawers', 'id')->whereNull('deleted_at')],
            'cash_event' => ['required', Rule::in(array_keys(CashEntry::EVENTS))],

            // "estimate:12" or "voucher:7" — one control, two columns, the grammar
            // HasOrderReference already uses. after() checks it resolves.
            'document_reference' => ['required', 'string', 'max:30'],

            'og_estimate_id' => ['nullable',
                Rule::exists('og_estimates', 'id')->whereNull('deleted_at'),
                Rule::unique('cash_entries', 'og_estimate_id')->ignore($id)->whereNull('deleted_at')],

            'cash_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'cheque_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],

            // Required only once there is a cheque to describe.
            'cheque_number' => [Rule::requiredIf($hasCheque), 'nullable', 'string', 'max:50'],
            'cheque_name' => [Rule::requiredIf($hasCheque), 'nullable', 'string', 'max:150'],
            'cheque_bank' => [Rule::requiredIf($hasCheque), 'nullable', 'string', 'max:100'],
            'cheque_mobile' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'og_estimate_id.unique' => 'That OG estimate has already been settled by another entry.',
            'cheque_number.required' => 'A cheque amount needs its number.',
            'cheque_name.required' => 'A cheque amount needs the name on it.',
            'cheque_bank.required' => 'A cheque amount needs its bank.',
        ];
    }

    /**
     * The rules that need the documents themselves, so they cannot be strings.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $document = $this->document();

            if (! $document) {
                $validator->errors()->add('document_reference', 'Choose an estimate or a voucher.');

                return;
            }

            if ($this->alreadySettled($document)) {
                $validator->errors()->add('document_reference', 'That document already has a cash entry.');

                return;
            }

            $math = app(CashMath::class);

            $final = $math->finalAmount($document);
            $settled = round(
                (float) $this->input('cash_amount')
                + (float) $this->input('cheque_amount')
                + $math->goldFigures($this->ogEstimate())['amount'],
                2
            );

            // The over-payment guard. More handed over than the document is worth
            // means a typo or the wrong document — never silently clamped.
            //
            // Attached to `discount`, which is not a posted field, so the form can
            // render the message under the readonly Discount box where the clerk is
            // already looking.
            if (round($final - $settled, 2) < 0) {
                $validator->errors()->add('discount', 'Discount is too small (minimum is 0).');
            }

            if ($settled <= 0) {
                $validator->errors()->add('cash_amount', 'Enter a cash, cheque or gold amount.');
            }
        }];
    }

    /**
     * The estimate or voucher this entry settles, loaded once.
     */
    public function document(): ItemEstimate|Voucher|null
    {
        if ($this->documentResolved) {
            return $this->document;
        }

        $this->documentResolved = true;

        ['item_estimate_id' => $estimateId, 'voucher_id' => $voucherId] =
            CashEntry::splitDocumentReference($this->input('document_reference'));

        $this->document = match (true) {
            // lines.stones because summary() walks both, and this figure decides
            // how much money the entry is allowed to carry.
            $estimateId !== null => ItemEstimate::with('lines.stones')->find($estimateId),
            $voucherId !== null => Voucher::find($voucherId),
            default => null,
        };

        return $this->document;
    }

    public function ogEstimate(): ?OgEstimate
    {
        $id = $this->input('og_estimate_id');

        return $id ? OgEstimate::with('lines')->find($id) : null;
    }

    private function alreadySettled(ItemEstimate|Voucher $document): bool
    {
        $column = $document instanceof ItemEstimate ? 'item_estimate_id' : 'voucher_id';

        return CashEntry::query()
            ->where($column, $document->id)
            ->whereKeyNot($this->route('cash_entry')?->id)
            ->exists();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cash_amount' => $this->input('cash_amount') ?: 0,
            'cheque_amount' => $this->input('cheque_amount') ?: 0,
            'og_estimate_id' => $this->input('og_estimate_id') ?: null,
        ]);

        // No cheque means no cheque details. Cleared here rather than refused, so a
        // clerk who types an amount and then removes it is not shouted at.
        if ((float) $this->input('cheque_amount') <= 0) {
            $this->merge([
                'cheque_number' => null,
                'cheque_name' => null,
                'cheque_mobile' => null,
                'cheque_bank' => null,
            ]);
        }
    }
}
