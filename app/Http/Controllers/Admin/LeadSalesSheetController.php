<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use App\Models\Lead;
use App\Models\LeadSalesItem;
use App\Models\LeadSalesItemPrint;
use App\Models\LeadSalesSheet;
use App\Models\LeadOrderCheckout;
use App\Services\LeadConversionTrackingService;
use App\Services\LeadOrderCheckoutService;
use App\Services\LeadProjectPdfService;
use App\Services\LeadSalesSheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LeadSalesSheetController extends Controller
{
    public function storeCheckout(
        Request $request,
        Lead $lead,
        LeadSalesSheet $sheet,
        LeadOrderCheckoutService $checkoutService,
    ): RedirectResponse {
        abort_unless($sheet->lead_id === $lead->id, 404);

        $data = $request->validate([
            'payment_type' => ['required', Rule::in(['full', 'deposit', 'balance'])],
            'deposit_amount' => ['required_if:payment_type,deposit', 'nullable', 'numeric', 'min:0.01'],
            'expires_at' => ['required', 'date', 'after:today', 'before_or_equal:'.now()->addDays(90)->toDateString()],
        ]);

        $checkout = $checkoutService->createForOrder(
            $sheet,
            $data['payment_type'],
            isset($data['deposit_amount']) ? (float) $data['deposit_amount'] : null,
            $data['expires_at'],
        );

        return redirect()->to(route('admin.leads.index', [
            'lead' => $lead,
            'sales_sheet' => $sheet->id,
        ]).'#product')->with('status', 'Checkout pronto: '.$checkoutService->publicUrl($checkout));
    }

    public function revokeCheckout(Lead $lead, LeadSalesSheet $sheet, LeadOrderCheckout $checkout): RedirectResponse
    {
        abort_unless($sheet->lead_id === $lead->id && $checkout->lead_sales_sheet_id === $sheet->id, 404);
        abort_if($checkout->status === 'paid', 422, 'Un checkout pagato non può essere revocato.');

        $checkout->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();

        return redirect()->to(route('admin.leads.index', [
            'lead' => $lead,
            'sales_sheet' => $sheet->id,
        ]).'#product')->with('status', 'Checkout revocato. Il vecchio link non è più valido.');
    }

    public function storeProposal(
        Request $request,
        Lead $lead,
        LeadSalesSheet $sheet,
        LeadProjectPdfService $projectPdf,
        LeadConversionTrackingService $tracking,
    ): RedirectResponse {
        abort_unless($sheet->lead_id === $lead->id, 404);

        $data = $request->validate([
            'proposal_number' => ['required', 'string', 'max:100', Rule::unique('lead_quote_pdfs', 'proposal_number')],
            'project_notes' => ['nullable', 'string', 'max:1500'],
            'send_google_event' => ['nullable', 'boolean'],
        ]);

        $sheet->load(['items.prints', 'items.attachments']);
        if ($sheet->items->isEmpty()) {
            return back()->withErrors(['proposal_number' => 'Aggiungi almeno un prodotto prima di generare la proposta.']);
        }

        $proposal = $lead->quotePdfs()->create([
            'lead_sales_sheet_id' => $sheet->id,
            'proposal_number' => $data['proposal_number'],
            'amount' => $sheet->vatTotals()['gross'],
            'project_notes' => $data['project_notes'] ?? null,
            'uploaded_at' => now(),
        ]);
        $proposal->forceFill([
            ...$projectPdf->generate($lead, $proposal),
            'uploaded_at' => now(),
        ])->save();

        $lead->forceFill([
            'quote_number' => $proposal->proposal_number,
            'quote_amount' => $proposal->amount,
        ])->save();

        if ($request->boolean('send_google_event')) {
            $lead->forceFill(['status' => 'quote_sent'])->save();
            $tracking->trackQuoteSent($lead->fresh());
        }

        return redirect()->to(route('admin.leads.index', ['lead' => $lead, 'sales_sheet' => $sheet->id]).'#proposal')
            ->with('status', 'Proposta generata dai dati dell’ordine '.$sheet->order_number.'.');
    }

    public function storeOrder(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $sheet = $lead->salesSheets()->create([
            'name' => trim($data['name']),
            'status' => 'draft',
            'prices_include_vat' => false,
            'minimum_order_amount' => config('orders.minimum_amount_ex_vat'),
            'shipping_fee' => 8.12,
            'free_shipping_threshold' => 250,
            'revenue_total' => 0,
            'cost_total' => 0,
            'margin_total' => 0,
            'margin_percentage' => 0,
        ]);
        $sheet->update(['order_number' => 'ORD-'.str_pad((string) $sheet->id, 6, '0', STR_PAD_LEFT)]);

        return redirect()->to(route('admin.leads.index', ['lead' => $lead, 'sales_sheet' => $sheet->id]).'#product')
            ->with('status', 'Nuovo ordine creato senza generare un nuovo lead.');
    }

    public function destroyOrder(Lead $lead, LeadSalesSheet $sheet, LeadSalesSheetService $calculator): RedirectResponse
    {
        abort_unless($sheet->lead_id === $lead->id, 404);

        $sheet->load(['items.attachments', 'checkouts.proforma']);
        foreach ($sheet->items->flatMap->attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
        foreach ($sheet->items->pluck('size_chart_path')->filter() as $sizeChartPath) {
            Storage::disk('local')->delete($sizeChartPath);
        }

        $sheet->checkouts
            ->pluck('proforma')
            ->filter()
            ->unique('id')
            ->each->delete();
        $sheet->checkouts()->delete();

        $sheet->delete();
        $remainingSheet = $lead->salesSheets()->first();

        if ($remainingSheet) {
            $calculator->recalculate($remainingSheet);
        } else {
            $lead->update(['margin_amount' => 0, 'quantity' => null, 'product' => null]);
        }

        return redirect()->to(route('admin.leads.index', ['lead' => $lead, 'sales_sheet' => $remainingSheet?->id]).'#product')
            ->with('status', 'Ordine eliminato definitivamente.');
    }

    public function storeItem(Request $request, Lead $lead, LeadSalesSheetService $calculator): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'exists:crm_products,id'], 'configuration_name' => ['nullable', 'string', 'max:255'], 'quantity' => ['required', 'numeric', 'min:0.01']]);
        $product = CrmProduct::with('priceTiers')->findOrFail($data['product_id']);
        $tier = $calculator->tier($product->priceTiers, (float) $data['quantity']);
        if (! $tier) {
            return back()->withErrors(['quantity' => 'Nessuna fascia prezzo configurata per questa quantità.']);
        }
        $sheet = $lead->salesSheet()->firstOrCreate([], [
            'prices_include_vat' => false,
            'minimum_order_amount' => config('orders.minimum_amount_ex_vat'),
            'shipping_fee' => 8.12,
            'free_shipping_threshold' => 250,
            'revenue_total' => 0,
            'cost_total' => 0,
            'margin_total' => 0,
            'margin_percentage' => 0,
        ]);
        $sheet->items()->create(['crm_product_id' => $product->id, 'product_code' => $product->code, 'product_name' => $product->name, 'configuration_name' => filled($data['configuration_name'] ?? null) ? trim($data['configuration_name']) : null, 'quantity' => $data['quantity'], 'product_unit_cost' => $product->unit_cost, 'product_unit_price' => $tier->unit_price]);
        $calculator->recalculate($sheet);

        return back()->with('status', 'Prodotto aggiunto alla scheda vendita.');
    }

    public function destroyItem(Lead $lead, LeadSalesItem $item, LeadSalesSheetService $calculator): RedirectResponse
    {
        abort_unless($item->lead_sales_sheet_id === $lead->salesSheet?->id, 404);
        $sheet = $lead->salesSheet;
        if ($item->size_chart_path) {
            Storage::disk('local')->delete($item->size_chart_path);
        }
        $item->delete();
        $calculator->recalculate($sheet);

        return back()->with('status', 'Prodotto rimosso.');
    }

    public function storePrint(Request $request, Lead $lead, LeadSalesItem $item, LeadSalesSheetService $calculator): RedirectResponse
    {
        abort_unless($item->lead_sales_sheet_id === $lead->salesSheet?->id, 404);
        $data = $request->validate(['print_type_id' => ['required', 'exists:crm_print_types,id']]);
        $type = CrmPrintType::with('priceTiers')->findOrFail($data['print_type_id']);
        $calculator->attachPrint($lead->salesSheet, $item, $type);
        $calculator->recalculate($lead->salesSheet);

        return back()->with('status', 'Stampa aggiunta.');
    }

    public function destroyPrint(Lead $lead, LeadSalesItem $item, LeadSalesItemPrint $print, LeadSalesSheetService $calculator): RedirectResponse
    {
        abort_unless($item->lead_sales_sheet_id === $lead->salesSheet?->id && $print->lead_sales_item_id === $item->id, 404);
        $print->delete();
        $calculator->recalculate($lead->salesSheet);

        return back()->with('status', 'Stampa rimossa.');
    }
}
