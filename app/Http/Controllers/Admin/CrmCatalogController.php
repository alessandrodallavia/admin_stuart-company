<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use App\Models\LeadCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CrmCatalogController extends Controller
{
    public function index(): View { return view('admin.crm-catalog.index',['categories'=>LeadCategory::orderBy('sort_order')->orderBy('name')->get(),'products'=>CrmProduct::with('priceTiers')->orderBy('name')->get(),'printTypes'=>CrmPrintType::with('priceTiers')->orderBy('name')->get()]); }
    public function storeCategory(Request $request): RedirectResponse { $data=$request->validate(['name'=>['required','string','max:255','unique:lead_categories,name'],'sort_order'=>['nullable','integer','min:0']]); LeadCategory::create($data+['is_active'=>true]); return back()->with('status','Categoria salvata.'); }
    public function toggleCategory(LeadCategory $category): RedirectResponse { $category->update(['is_active'=>! $category->is_active]); return back()->with('status', $category->is_active ? 'Categoria riattivata.' : 'Categoria disattivata.'); }
    public function destroyCategory(LeadCategory $category): RedirectResponse
    {
        if ($category->leads()->exists()) {
            return back()->withErrors(['category' => 'La categoria è già associata a uno o più lead: puoi disattivarla, ma non eliminarla.']);
        }

        $category->delete();
        return back()->with('status', 'Categoria eliminata definitivamente.');
    }
    public function storeProduct(Request $request): RedirectResponse { $data=$request->validate(['code'=>['required','string','max:100','unique:crm_products,code'],'name'=>['required','string','max:255'],'unit_cost'=>['required','numeric','min:0']]); CrmProduct::create($data+['is_active'=>true]); return back()->with('status','Prodotto salvato.'); }
    public function storeProductTier(Request $request, CrmProduct $product): RedirectResponse { $data=$request->validate(['min_quantity'=>['required','numeric','min:0.01'],'max_quantity'=>['nullable','numeric','gte:min_quantity'],'unit_price'=>['required','numeric','min:0']]); $product->priceTiers()->create($data); return back()->with('status','Fascia prodotto salvata.'); }
    public function storePrintType(Request $request): RedirectResponse { $data=$request->validate(['code'=>['required','string','max:100','unique:crm_print_types,code'],'name'=>['required','string','max:255']]); CrmPrintType::create($data+['is_active'=>true]); return back()->with('status','Stampa salvata.'); }
    public function storePrintTier(Request $request, CrmPrintType $printType): RedirectResponse { $data=$request->validate(['min_quantity'=>['required','numeric','min:0.01'],'max_quantity'=>['nullable','numeric','gte:min_quantity'],'unit_cost'=>['required','numeric','min:0'],'unit_price'=>['required','numeric','min:0']]); $printType->priceTiers()->create($data); return back()->with('status','Fascia stampa salvata.'); }
}
