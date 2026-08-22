@csrf

<div class="card">
    <div class="card-header py-2">
        <h5 class="mb-0">Create Order</h5>
    </div>
    <div class="card-body">
        {{-- Labels above their control, as on the repair and order forms: with two
             columns of label/control pairs, help text on one side pushes it out of
             step with the other. --}}
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Form No</label>
                {{-- Issued by the system under a lock, so it is never typed. --}}
                <input type="text" class="form-control bg-light" value="{{ $nextFormNo }}" readonly>
                @unless ($order->exists)
                    <small class="text-muted">Assigned on save.</small>
                @endunless
            </div>

            <div class="col-md-3">
                <label for="order_date" class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" id="order_date" name="order_date"
                    class="form-control @error('order_date') is-invalid @enderror"
                    value="{{ old('order_date', optional($order->order_date)->toDateString() ?? today()->toDateString()) }}"
                    required>
                @error('order_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                <select id="supplier_id" name="supplier_id"
                    class="form-select @error('supplier_id') is-invalid @enderror" required>
                    <option value="">Select Supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id', $order->supplier_id) == $supplier->id)>
                            {{ $supplier->short_name ?: $supplier->name }}
                        </option>
                    @endforeach
                </select>
                @error('supplier_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="order_type_id" class="form-label">Type <span class="text-danger">*</span></label>
                <select id="order_type_id" name="order_type_id"
                    class="form-select @error('order_type_id') is-invalid @enderror" required>
                    <option value="">Select</option>
                    @foreach ($orderTypes as $id => $name)
                        <option value="{{ $id }}" @selected(old('order_type_id', $order->order_type_id) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <small class="text-muted">Prints as ITEM CODE.</small>
                @error('order_type_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="customer_delivery_date" class="form-label">
                    Customer Delivery Date <span class="text-danger">*</span>
                </label>
                <input type="date" id="customer_delivery_date" name="customer_delivery_date"
                    class="form-control @error('customer_delivery_date') is-invalid @enderror"
                    value="{{ old('customer_delivery_date', optional($order->customer_delivery_date)->toDateString()) }}"
                    required>
                @error('customer_delivery_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="followup_date" class="form-label">Followup Date <span class="text-danger">*</span></label>
                <input type="date" id="followup_date" name="followup_date"
                    class="form-control @error('followup_date') is-invalid @enderror"
                    value="{{ old('followup_date', optional($order->followup_date)->toDateString()) }}" required>
                <small class="text-muted">Once past, the order shows as overdue.</small>
                @error('followup_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="order_form_ref" class="form-label">Order Form No</label>
                <input type="text" id="order_form_ref" name="order_form_ref"
                    class="form-control text-uppercase @error('order_form_ref') is-invalid @enderror"
                    value="{{ old('order_form_ref', $order->order_form_ref) }}" maxlength="30"
                    autocomplete="off">
                <small class="text-muted">Free text, saved in capitals.</small>
                @error('order_form_ref')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="size_pcs" class="form-label">Size / pcs</label>
                <input type="text" id="size_pcs" name="size_pcs" class="form-control"
                    value="{{ old('size_pcs', $order->size_pcs) }}" maxlength="50">
            </div>

            <div class="col-md-6">
                <label for="description" class="form-label">Description</label>
                <input type="text" id="description" name="description" class="form-control"
                    value="{{ old('description', $order->description) }}" maxlength="255">
            </div>

            <div class="col-md-3">
                <label for="order_weight" class="form-label">Order Weight</label>
                <input type="number" step="0.001" min="0" id="order_weight" name="order_weight"
                    class="form-control @error('order_weight') is-invalid @enderror"
                    value="{{ old('order_weight', $order->order_weight) }}">
                @error('order_weight')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="sample_weight" class="form-label">Sample Weight</label>
                <input type="number" step="0.001" min="0" id="sample_weight" name="sample_weight"
                    class="form-control @error('sample_weight') is-invalid @enderror"
                    value="{{ old('sample_weight', $order->sample_weight) }}">
                <small class="text-muted">Prints on the receipt.</small>
                @error('sample_weight')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="sample_desc" class="form-label">Sample Desc</label>
                <input type="text" id="sample_desc" name="sample_desc" class="form-control"
                    value="{{ old('sample_desc', $order->sample_desc) }}" maxlength="255">
            </div>

            <div class="col-md-8">
                <label for="special_remarks" class="form-label">Special Remarks</label>
                <textarea id="special_remarks" name="special_remarks" rows="4" class="form-control"
                    maxlength="2000">{{ old('special_remarks', $order->special_remarks) }}</textarea>
            </div>

            <div class="col-md-4">
                <label class="form-label">Photo</label>
                <div class="border rounded p-2 text-center">
                    @if ($order->photoUrl())
                        <img src="{{ $order->photoUrl() }}" alt="Order {{ $order->form_no }}"
                            class="img-fluid rounded mb-2" style="max-height: 120px;">
                    @else
                        <div class="bg-light rounded d-flex align-items-center justify-content-center mb-2"
                            style="height: 80px;">
                            <span class="text-muted"><i class="ri-image-line fs-24 d-block mb-1"></i>No photo</span>
                        </div>
                    @endif

                    <input type="file" name="photo" class="form-control form-control-sm"
                        accept="image/png,image/jpeg,image/webp">
                    @error('photo')
                        <div class="text-danger fs-13 mt-1">{{ $message }}</div>
                    @enderror

                    @if ($order->hasPhoto())
                        <div class="form-check mt-2 text-start">
                            <input type="hidden" name="remove_photo" value="0">
                            <input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo" value="1">
                            <label class="form-check-label text-danger fs-13" for="remove_photo">Remove photo</label>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mb-4 d-flex gap-2 justify-content-center">
    <a href="{{ route('supplier-orders.index') }}" class="btn btn-warning">Cancel</a>

    <button type="submit" class="btn btn-dark">
        <i class="ri-save-line"></i> {{ $order->exists ? 'Update' : 'Save' }}
    </button>

    @can('supplier_order.print')
        @unless ($order->exists)
            <button type="submit" name="print_after_save" value="1" class="btn btn-secondary">
                <i class="ri-printer-line"></i> Save &amp; Print
            </button>
        @endunless
    @endcan
</div>

@push('js')
    <script>
        $(function () {
            window.appSelect2('#supplier_id', { allowClear: false });
        });
    </script>
@endpush
