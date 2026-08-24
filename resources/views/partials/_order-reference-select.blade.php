{{--
    The Order Number control: IN, OUT, or a particular order form.

    One select posting one value — "in", "out" or "order:12" — which the request folds
    back into the two columns behind it. Shared by the OG estimate and the voucher.

    $record     the model, carrying orderReferenceValue()
    $orderForms the orders to offer
--}}
<label for="order_reference" class="form-label">Order Number <span class="text-danger">*</span></label>

<select id="order_reference" name="order_reference"
    class="form-select @error('order_reference') is-invalid @enderror" required>
    <option value="">Select</option>

    @foreach (\App\Models\OgEstimate::DIRECTIONS as $value => $label)
        <option value="{{ $value }}" @selected(old('order_reference', $record->orderReferenceValue()) === $value)>
            {{ $label }}
        </option>
    @endforeach

    <optgroup label="Against an order">
        @foreach ($orderForms as $form)
            @php($value = 'order:'.$form->id)
            <option value="{{ $value }}" data-customer="{{ $form->customer_name }}"
                data-contact="{{ $form->contact_no }}"
                data-reference="{{ trim(\App\Models\OrderForm::refPrefix().' '.$form->ref_no) }}"
                @selected(old('order_reference', $record->orderReferenceValue()) === $value)>
                {{ trim(\App\Models\OrderForm::refPrefix().' '.$form->ref_no) }} — {{ $form->customer_name }}
            </option>
        @endforeach
    </optgroup>
</select>

<small class="text-muted d-none" id="order-customer-hint"></small>

@error('order_reference')
    <div class="invalid-feedback d-block">{{ $message }}</div>
@enderror
