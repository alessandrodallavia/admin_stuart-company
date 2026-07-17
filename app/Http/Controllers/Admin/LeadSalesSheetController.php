<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use App\Models\Lead;
use App\Models\LeadSalesItem;
use App\Models\LeadSalesItemPrint;
use App\Services\LeadSalesSheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadSalesSheetController extends Controller
{
    public function storeItem(Request $request, Lead $lead, LeadSalesSheetService $calculator): RedirectResponse
    {
        $data=$request->validate(['product_id'=>['required','exists:crm_products,id'],'configuration_name'=>['nullable','string','max:255'],'quantity'=>['required','numeric','min:0.01']]);
        $product=CrmProduct::with('priceTiers')->findOrFail($data['product_id']); $tier=$calculator->tier($product->priceTiers,(float)$data['quantity']);
        if(!$tier) return back()->withErrors(['quantity'=>'Nessuna fascia prezzo configurata per questa quantità.']);
        $sheet=$lead->salesSheet()->firstOrCreate([],['revenue_total'=>0,'cost_total'=>0,'margin_total'=>0,'margin_percentage'=>0]);
        $sheet->items()->create(['crm_product_id'=>$product->id,'product_code'=>$product->code,'product_name'=>$product->name,'configuration_name'=>filled($data['configuration_name'] ?? null) ? trim($data['configuration_name']) : null,'quantity'=>$data['quantity'],'product_unit_cost'=>$product->unit_cost,'product_unit_price'=>$tier->unit_price]);
        $calculator->recalculate($sheet); return back()->with('status','Prodotto aggiunto alla scheda vendita.');
    }
    public function destroyItem(Lead $lead, LeadSalesItem $item, LeadSalesSheetService $calculator): RedirectResponse { abort_unless($item->lead_sales_sheet_id===$lead->salesSheet?->id,404); $sheet=$lead->salesSheet; $item->delete(); $calculator->recalculate($sheet); return back()->with('status','Prodotto rimosso.'); }
    public function storePrint(Request $request, Lead $lead, LeadSalesItem $item, LeadSalesSheetService $calculator): RedirectResponse
    {
        abort_unless($item->lead_sales_sheet_id===$lead->salesSheet?->id,404); $data=$request->validate(['print_type_id'=>['required','exists:crm_print_types,id']]); $type=CrmPrintType::with('priceTiers')->findOrFail($data['print_type_id']); $tier=$calculator->tier($type->priceTiers,(float)$item->quantity);
        if(!$tier) return back()->withErrors(['print_type_id'=>'Nessuna fascia prezzo stampa per questa quantità.']);
        $item->prints()->create(['crm_print_type_id'=>$type->id,'print_code'=>$type->code,'print_name'=>$type->name,'unit_cost'=>$tier->unit_cost,'unit_price'=>$tier->unit_price]); $calculator->recalculate($lead->salesSheet); return back()->with('status','Stampa aggiunta.');
    }
    public function destroyPrint(Lead $lead, LeadSalesItem $item, LeadSalesItemPrint $print, LeadSalesSheetService $calculator): RedirectResponse { abort_unless($item->lead_sales_sheet_id===$lead->salesSheet?->id && $print->lead_sales_item_id===$item->id,404); $print->delete(); $calculator->recalculate($lead->salesSheet); return back()->with('status','Stampa rimossa.'); }
}
