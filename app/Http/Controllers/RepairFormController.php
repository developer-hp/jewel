<?php

namespace App\Http\Controllers;

use App\Http\Requests\RepairFormRequest;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\RepairForm;
use App\Models\SalesPerson;
use App\Services\ItemPhotoStore;
use App\Services\WhatsAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class RepairFormController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ItemPhotoStore $photos,
        private readonly WhatsAppNotifier $whatsapp,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:repair_form.view', only: ['index']),
            new Middleware('permission:repair_form.create', only: ['create', 'store']),
            new Middleware('permission:repair_form.edit', only: ['edit', 'update']),
            new Middleware('permission:repair_form.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('repair-forms.index');
    }

    private function data(Request $request): JsonResponse
    {
        $query = RepairForm::query()
            ->select('repair_forms.*')
            ->withReadyCounts()
            ->when($request->filled('status'), fn ($q) => $request->string('status')->toString() === 'ready'
                ? $q->ready()
                : $q->pending());

        $prefix = RepairForm::refPrefix();

        return DataTables::eloquent($query)
            ->addColumn('select', fn (RepairForm $form) => view('repair-forms.partials.select-cell', ['form' => $form])->render())
            ->addColumn('ref', fn (RepairForm $form) => '<strong>'.e($prefix.' '.$form->ref_no).'</strong>')
            ->addColumn('customer', fn (RepairForm $form) => e($form->customer_name))
            ->addColumn('contact', fn (RepairForm $form) => e($form->contact_no))
            ->editColumn('form_date', fn (RepairForm $form) => $form->form_date->format('d-m-Y'))
            ->editColumn('delivery_date', fn (RepairForm $form) => $form->delivery_date->format('d-m-Y'))
            ->addColumn('progress', fn (RepairForm $form) => $form->ready_lines_count.' / '.$form->lines_count)
            ->editColumn('remarks', fn (RepairForm $form) => e($form->remarks ?: '—'))
            ->addColumn('action', fn (RepairForm $form) => view('repair-forms.partials.actions', ['form' => $form])->render())
            // Green when every piece is back, red while any is still out — the same
            // read the paper listing gives at a glance.
            ->setRowClass(fn (RepairForm $form) => $this->isRowReady($form) ? 'row-ready' : 'row-pending')
            ->filterColumn('ref', fn ($q, $keyword) => $q->where('ref_no', 'like', '%'.ltrim($keyword, $prefix.' ').'%'))
            ->filterColumn('customer', fn ($q, $keyword) => $q->where('customer_name', 'like', "%{$keyword}%"))
            ->filterColumn('contact', function ($q, $keyword) {
                $q->where(fn ($sub) => $sub->where('contact_no', 'like', "%{$keyword}%")
                    ->orWhere('contact_no_alt', 'like', "%{$keyword}%"));
            })
            ->orderColumn('ref', 'ref_no $1')
            ->orderColumn('customer', 'customer_name $1')
            ->orderColumn('contact', 'contact_no $1')
            ->rawColumns(['select', 'ref', 'action'])
            ->toJson();
    }

    /**
     * Reads the counts loaded by withReadyCounts() rather than the relation, so the
     * listing does not query per row.
     */
    private function isRowReady(RepairForm $form): bool
    {
        return $form->lines_count > 0 && $form->ready_lines_count === $form->lines_count;
    }

    public function create(): View
    {
        return view('repair-forms.create', $this->formData() + [
            'form' => new RepairForm(['form_date' => today(), 'delivery_date' => today()->addWeeks(2)]),
            'lines' => collect(),
            'chosenSalesPersons' => [],
            'nextRef' => RepairForm::refPrefix().' '.AppSetting::current()->repair_next_ref_no,
        ]);
    }

    public function store(RepairFormRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $form = DB::transaction(function () use ($data, $request) {
            $form = new RepairForm($data);
            // Reserved inside the transaction so the settings row lock holds.
            $form->ref_no = RepairForm::nextRefNo();
            $form->created_by = $request->user()->id;
            $form->save();

            $form->lines()->createMany($data['lines']);
            $this->syncSalesPersons($form, $data['sales_person_ids']);
            $this->linkCustomer($form);

            return $form;
        });

        // After the commit, like the photo below: the customer is only told about a
        // repair that is definitely on disk. Never throws — see WhatsAppNotifier.
        $this->whatsapp->repairCreated($form);

        if ($request->hasFile('photo')) {
            $this->photos->put($form, $request->file('photo'));
        }

        if ($request->boolean('print_after_save')) {
            return redirect()->route('repair-forms.index')
                ->with('printAfterSave', $form->id)
                ->with('success', "Repair {$form->reference()} saved — printing.");
        }

        return redirect()->route('repair-forms.index')
            ->with('success', "Repair {$form->reference()} has been created.");
    }

    public function edit(RepairForm $repairForm): View
    {
        return view('repair-forms.edit', $this->formData() + [
            'form' => $repairForm,
            'lines' => $repairForm->lines()->with('item')->get(),
            'chosenSalesPersons' => $repairForm->salesPersons->pluck('sales_person_id')->filter()->all(),
            'nextRef' => $repairForm->reference(),
        ]);
    }

    public function update(RepairFormRequest $request, RepairForm $repairForm): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($repairForm, $data) {
            $repairForm->update($data);

            $this->syncLines($repairForm, $data['lines']);
            $this->syncSalesPersons($repairForm, $data['sales_person_ids']);
            // Re-run on update too: correcting a mistyped number should attach the
            // form to the right person, or add them if they are new.
            $this->linkCustomer($repairForm);
        });

        if ($request->hasFile('photo')) {
            $this->photos->put($repairForm, $request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            $this->photos->remove($repairForm);
        }

        return redirect()->route('repair-forms.index')
            ->with('success', "Repair {$repairForm->reference()} has been updated.");
    }

    public function destroy(RepairForm $repairForm): RedirectResponse
    {
        if ($repairForm->lines()->whereHas('item')->exists()) {
            return back()->with('error', "{$repairForm->reference()} has pieces already booked into stock and cannot be deleted.");
        }

        $reference = $repairForm->reference();

        if ($repairForm->hasPhoto()) {
            $this->photos->remove($repairForm);
        }

        $repairForm->lines()->delete();
        $repairForm->salesPersons()->delete();
        $repairForm->delete();

        return redirect()->route('repair-forms.index')
            ->with('success', "Repair {$reference} has been deleted.");
    }

    /**
     * Update the lines in place rather than replacing them wholesale.
     *
     * A line whose piece is already back in stock is pointed at by an item, so
     * deleting and recreating it would quietly break that link and un-ready the
     * form. Only lines nothing points at are ever removed.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncLines(RepairForm $form, array $rows): void
    {
        $kept = [];

        foreach ($rows as $row) {
            $attributes = [
                'description' => $row['description'],
                'net_weight' => $row['net_weight'],
                'sort_order' => $row['sort_order'],
            ];

            $line = $row['id'] ? $form->lines()->whereKey($row['id'])->first() : null;

            if ($line) {
                $line->update($attributes);
            } else {
                $line = $form->lines()->create($attributes);
            }

            $kept[] = $line->id;
        }

        $form->lines()->whereNotIn('id', $kept)->whereDoesntHave('item')->delete();
    }

    /**
     * Tie the form to the customer register, adding them on first contact.
     *
     * The form keeps its own copy of the name, number and address either way — this
     * only records who it belongs to, so the next repair for the same number can
     * prefill itself.
     */
    private function linkCustomer(RepairForm $form): void
    {
        $customer = Customer::rememberByPhone($form->contact_no, $form->customer_name, $form->address);

        if ($customer && $form->customer_id !== $customer->id) {
            $form->forceFill(['customer_id' => $customer->id])->save();
        }
    }

    /**
     * Names are snapshotted, so a later rename in the master cannot rewrite a form
     * that has already printed.
     *
     * @param  array<int, int>  $ids
     */
    private function syncSalesPersons(RepairForm $form, array $ids): void
    {
        $people = SalesPerson::whereIn('id', $ids)->get()->keyBy('id');

        $form->salesPersons()->delete();

        foreach (array_values($ids) as $order => $id) {
            $person = $people->get($id);

            if (! $person) {
                continue;
            }

            $form->salesPersons()->create([
                'sales_person_id' => $person->id,
                'name' => $person->name,
                'sort_order' => $order,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return ['salesPersons' => SalesPerson::active()->ordered()->get(['id', 'name', 'city'])];
    }
}
