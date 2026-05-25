<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminDocument;
use App\Models\AdminShipment;
use App\Models\AdminShipmentParcel;
use App\Services\Shipments\AdminShipmentBorderoPdfService;
use App\Services\Shipments\AdminShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $shipments = AdminShipment::query()
            ->with(['document', 'documents'])
            ->withCount('parcels')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%'.$request->string('q')->toString().'%';

                $query->where(function ($query) use ($search) {
                    $query->where('recipient_name', 'like', $search)
                        ->orWhere('recipient_email', 'like', $search)
                        ->orWhere('recipient_phone', 'like', $search)
                        ->orWhere('reference', 'like', $search)
                        ->orWhere('tracking_number', 'like', $search)
                        ->orWhereHas('documents', function ($query) use ($search) {
                            $query->where('code', 'like', $search)
                                ->orWhere('customer_name', 'like', $search);
                        });
                });
            })
            ->when($request->filled('carrier'), fn ($query) => $query->where('carrier', $request->string('carrier')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->string('link')->toString() === 'with_document', fn ($query) => $query->whereHas('documents'))
            ->when($request->string('link')->toString() === 'free', fn ($query) => $query->doesntHave('documents'))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_to')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.shipments.index', [
            'shipments' => $shipments,
            'carriers' => AdminShipment::CARRIERS,
            'statuses' => AdminShipment::STATUSES,
            'filters' => $request->only(['q', 'carrier', 'status', 'link', 'date_from', 'date_to']),
        ]);
    }

    public function create(Request $request, AdminShipmentService $service): View
    {
        $document = $request->filled('document_id')
            ? AdminDocument::find($request->integer('document_id'))
            : null;
        $selectedDocumentIds = collect(old('document_ids', $document ? [$document->id] : []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $selectedDocuments = AdminDocument::query()
            ->whereIn('id', $selectedDocumentIds)
            ->get()
            ->sortBy(fn (AdminDocument $document) => $selectedDocumentIds->search($document->id))
            ->values();

        return view('admin.shipments.create', [
            'shipment' => new AdminShipment([
                ...($document ? $service->fromDocument($document) : []),
                'carrier' => 'brt',
                'recipient_country' => 'IT',
                'parcels_count' => 1,
                'weight_kg' => 1.0,
                'volume_m3' => 0.000,
            ]),
            'document' => $document,
            'selectedDocuments' => $selectedDocuments,
            'carriers' => AdminShipment::CARRIERS,
        ]);
    }

    public function store(Request $request, AdminShipmentService $service): RedirectResponse
    {
        $data = $this->validatedData($request);
        $document = ! empty($data['document_ids']) ? AdminDocument::find((int) reset($data['document_ids'])) : null;
        $shipment = $service->create($data, $document);

        return redirect()
            ->route('admin.shipments.show', $shipment)
            ->with('status', $shipment->status === 'shipped' ? 'Spedizione creata.' : 'Spedizione salvata con errore: '.$shipment->error_message);
    }

    public function documentSearch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($data['q'] ?? '');
        $documents = AdminDocument::query()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function ($query) use ($like) {
                    $query->where('code', 'like', $like)
                        ->orWhere('number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_email', 'like', $like)
                        ->orWhere('customer_vat_number', 'like', $like);
                });
            })
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $documents->map(fn (AdminDocument $document) => [
                'id' => $document->id,
                'label' => $document->display_code.' - '.$document->customer_name,
                'meta' => trim($document->type_label.' · '.optional($document->document_date)->format('d/m/Y')),
            ])->values(),
        ]);
    }

    public function show(AdminShipment $shipment): View
    {
        $shipment->load(['document', 'documents', 'parcels']);

        return view('admin.shipments.show', [
            'shipment' => $shipment,
        ]);
    }

    public function retry(AdminShipment $shipment, AdminShipmentService $service): RedirectResponse
    {
        abort_unless($shipment->status === 'failed', 404);

        $shipment = $service->ship($shipment);

        return back()->with('status', $shipment->status === 'shipped' ? 'Spedizione creata.' : 'Errore spedizione: '.$shipment->error_message);
    }

    public function label(AdminShipment $shipment, AdminShipmentParcel $parcel, AdminShipmentService $service): RedirectResponse
    {
        $parcel = $service->sendParcelLabelToDropbox($shipment, $parcel);

        return back()->with('status', 'Etichetta inviata a Dropbox: '.$parcel->dropbox_path);
    }

    public function bordero(Request $request, AdminShipmentBorderoPdfService $pdfService)
    {
        $data = $request->validate([
            'shipment_ids' => ['required', 'array', 'min:1'],
            'shipment_ids.*' => ['integer', Rule::exists('admin_shipments', 'id')],
            'carrier' => ['required', Rule::in(array_keys(AdminShipment::CARRIERS))],
        ]);

        $shipments = AdminShipment::query()
            ->with(['documents', 'parcels'])
            ->whereIn('id', $data['shipment_ids'])
            ->where('carrier', $data['carrier'])
            ->orderBy('recipient_name')
            ->get();

        if ($shipments->isEmpty()) {
            return back()->withErrors(['shipment_ids' => 'Nessuna spedizione selezionata per questo corriere.']);
        }

        return response($pdfService->output($shipments, $data['carrier']))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$pdfService->filename($data['carrier']).'"');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['integer', Rule::exists('admin_documents', 'id')],
            'carrier' => ['required', Rule::in(array_keys(AdminShipment::CARRIERS))],
            'reference' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_email' => ['nullable', 'email:rfc', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:40'],
            'recipient_address' => ['required', 'string', 'max:255'],
            'recipient_street_number' => ['nullable', 'string', 'max:30'],
            'recipient_city' => ['required', 'string', 'max:120'],
            'recipient_province' => ['nullable', 'string', 'max:10'],
            'recipient_postal_code' => ['required', 'string', 'max:20'],
            'recipient_country' => ['required', 'string', 'size:2'],
            'parcels_count' => ['required', 'integer', 'min:1', 'max:99'],
            'weight_kg' => ['required', 'numeric', 'min:0.1', 'max:99999.99'],
            'volume_m3' => ['required', 'numeric', 'min:0.000', 'max:999.999'],
            'cash_on_delivery' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);
    }
}
