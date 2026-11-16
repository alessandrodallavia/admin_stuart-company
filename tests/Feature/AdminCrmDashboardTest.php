<?php

namespace Tests\Feature;

use App\Jobs\SendLeadOrder;
use App\Livewire\Admin\LeadSalesSheet as LeadSalesSheetComponent;
use App\Models\AdminUser;
use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\LeadOrderCheckout;
use App\Models\LeadCategory;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\GoogleAdsReportingService;
use App\Services\CheckoutOrderCustomerNotificationService;
use App\Services\LeadOrderPackageService;
use App\Services\LeadSalesItemVariantService;
use App\Services\LeadSalesSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCrmDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_color_and_size_distribution_updates_completed_checkout_without_changing_totals(): void
    {
        $lead = $this->lead(['name' => 'Cliente varianti']);
        $product = CrmProduct::create(['code' => 'TS-VAR', 'name' => 'T-shirt varianti', 'unit_cost' => 2, 'is_active' => true]);
        $black = $product->colors()->create(['code' => 'BLACK', 'name' => 'Nero', 'sort_order' => 1]);
        $small = $product->sizes()->create(['name' => 'S', 'sort_order' => 1]);
        $medium = $product->sizes()->create(['name' => 'M', 'sort_order' => 2]);
        $sheet = $lead->salesSheets()->create(['order_number' => 'ORD-00999', 'name' => 'Ordine varianti', 'status' => 'draft']);
        $item = $sheet->items()->create([
            'crm_product_id' => $product->id, 'product_code' => $product->code, 'product_name' => $product->name,
            'quantity' => 10, 'product_unit_cost' => 2, 'product_unit_price' => 5, 'final_unit_price' => 5,
            'revenue_total' => 50, 'cost_total' => 20, 'margin_total' => 30,
        ]);
        $checkout = LeadOrderCheckout::create([
            'lead_id' => $lead->id, 'lead_sales_sheet_id' => $sheet->id, 'token' => str_repeat('v', 64),
            'proposal_number' => 'ORD-00999', 'order_number' => 'ORD-00999', 'status' => 'bank_transfer_pending',
            'order_status' => 'proforma_pending', 'payment_type' => 'full', 'payment_label' => 'Pagamento completo',
            'currency' => 'EUR', 'items' => [[
                'sales_item_id' => $item->id, 'code' => 'TS-VAR', 'name' => 'T-shirt varianti', 'details' => '',
                'quantity' => 10, 'unit_price' => 5, 'line_total' => 50, 'variants' => [],
            ]], 'product_subtotal' => 50, 'order_taxable_amount' => 50, 'order_total_amount' => 61,
            'taxable_amount' => 50, 'total_amount' => 61,
        ]);

        app(LeadSalesItemVariantService::class)->save($item, [
            $black->id => [$small->id => 4, $medium->id => 6],
        ]);

        $this->assertDatabaseHas('lead_sales_item_variants', ['lead_sales_item_id' => $item->id, 'color_name' => 'Nero', 'size_name' => 'S', 'quantity' => 4]);
        $this->assertSame('bank_transfer_pending', $checkout->fresh()->status);
        $this->assertSame('61.00', $checkout->fresh()->total_amount);
        $this->assertNotNull($checkout->fresh()->order_snapshot_hash);
        $this->assertSame('Nero — S/4, M/6', $checkout->fresh()->items[0]['details']);
        $this->assertSame("col. Nero tg. S/4, M/6", \App\Support\OrderItemVariantFormatter::proformaDetails($checkout->fresh()->items[0]));
        $this->assertSame("col. Black tg. S/10, M/10\ncol. White tg. L/5", \App\Support\OrderItemVariantFormatter::proformaDetails([
            'variants' => [
                ['color' => 'Black', 'size' => 'S', 'quantity' => 10],
                ['color' => 'Black', 'size' => 'M', 'quantity' => 10],
                ['color' => 'White', 'size' => 'L', 'quantity' => 5],
            ],
        ]));
        $this->assertCount(2, $checkout->fresh()->items[0]['variants']);
    }

    public function test_dashboard_is_the_admin_home_and_shows_crm_metrics(): void
    {
        $admin = $this->owner();
        $quoted = $this->lead([
            'name' => 'Cliente Pagato',
            'status' => 'order_completed',
            'quote_amount' => 240,
            'payment_amount' => 240,
            'margin_amount' => 120,
            'category' => 'Calcio',
            'product' => 'Kit calcio',
            'quantity' => 12,
            'lead_quality' => 'Alta',
        ]);
        $quoted->quotePdfs()->create([
            'proposal_number' => 'P-001',
            'amount' => 240,
            'uploaded_at' => now(),
        ]);
        $this->lead(['name' => 'Cliente Nuovo']);

        $this->actingAs($admin, 'admin')
            ->get('/')
            ->assertOk()
            ->assertSee('CRM Dashboard')
            ->assertSee('Cliente Pagato')
            ->assertSee('Kit calcio')
            ->assertSee('€ 240,00')
            ->assertSee('100,0%');
    }

    public function test_dashboard_excludes_pre_leads_from_commercial_metrics_and_counts_real_chats(): void
    {
        $admin = $this->owner();
        $preLead = $this->lead([
            'name' => 'Pre lead con dati da ignorare',
            'status' => 'pre',
            'quantity' => 500,
            'quote_amount' => 900,
        ]);
        $preLead->quotePdfs()->create([
            'proposal_number' => 'PRE-001',
            'amount' => 900,
            'uploaded_at' => now(),
        ]);
        $worked = $this->lead(['status' => 'confirmed', 'quantity' => 10]);
        $paid = $this->lead([
            'status' => 'order_completed',
            'quantity' => 30,
            'quote_amount' => 300,
            'payment_amount' => 300,
            'margin_amount' => null,
        ]);
        $paid->quotePdfs()->create([
            'proposal_number' => 'PAG-001',
            'amount' => 300,
            'uploaded_at' => now(),
        ]);

        $conversation = WhatsappConversation::create([
            'lead_id' => $preLead->id,
            'contact_phone' => '390000000099',
            'business_phone' => '390000000000',
            'mode' => 'auto',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        WhatsappMessage::create([
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => 'inbound-dashboard-test',
            'direction' => 'inbound',
            'type' => 'text',
            'from_phone' => '390000000099',
            'to_phone' => '390000000000',
            'body' => 'Vorrei informazioni',
            'received_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/')
            ->assertOk()
            ->assertViewHas('stats', function (array $stats) {
                return $stats['records'] === 3
                    && $stats['pre_leads'] === 1
                    && $stats['worked_leads'] === 2
                    && $stats['chat_started'] === 1
                    && $stats['quotes'] === 1
                    && $stats['payments'] === 1
                    && $stats['average_quantity'] === 20.0
                    && $stats['quantity_coverage'] === 2
                    && $stats['margin'] === null
                    && $stats['margin_coverage'] === 0;
            })
            ->assertSee('Pre-lead fermi')
            ->assertSee('Chat avviate')
            ->assertSee('Lavorati → Proposta')
            ->assertSee('N.D.');

        $this->assertSame('confirmed', $worked->fresh()->status);
    }

    public function test_dashboard_splits_open_won_and_lost_proposal_pipeline(): void
    {
        $admin = $this->owner();

        foreach ([
            ['status' => 'quote_sent', 'number' => 'OPEN-1', 'amount' => 100],
            ['status' => 'order_completed', 'number' => 'WON-1', 'amount' => 200],
            ['status' => 'lost', 'number' => 'LOST-1', 'amount' => 300],
        ] as $proposal) {
            $lead = $this->lead([
                'status' => $proposal['status'],
                'quote_amount' => $proposal['amount'],
                'payment_amount' => $proposal['status'] === 'order_completed' ? $proposal['amount'] : null,
            ]);
            $lead->quotePdfs()->create([
                'proposal_number' => $proposal['number'],
                'amount' => $proposal['amount'],
                'uploaded_at' => now(),
            ]);
        }

        $this->actingAs($admin, 'admin')
            ->get('/')
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['pipeline_open_count'] === 1
                && $stats['pipeline_open_value'] === 100.0
                && $stats['pipeline_won_count'] === 1
                && $stats['pipeline_won_value'] === 200.0
                && $stats['pipeline_lost_count'] === 1
                && $stats['pipeline_lost_value'] === 300.0)
            ->assertSee('Pipeline proposte')
            ->assertSeeInOrder(['Aperta', 'Vinta', 'Persa']);
    }

    public function test_dashboard_table_excludes_pre_leads_by_default_and_accepts_multiple_statuses(): void
    {
        $admin = $this->owner();
        $this->lead(['name' => 'Pre lead dashboard nascosto', 'status' => 'pre']);
        $this->lead(['name' => 'Lead confermato dashboard', 'status' => 'confirmed']);
        $this->lead(['name' => 'Lead perso dashboard', 'status' => 'lost']);

        $this->actingAs($admin, 'admin')
            ->get('/')
            ->assertOk()
            ->assertDontSee('Pre lead dashboard nascosto')
            ->assertSee('Lead confermato dashboard')
            ->assertSee('Lead perso dashboard')
            ->assertSee('Pre-lead esclusi')
            ->assertSee('name="statuses[]"', false);

        $this->actingAs($admin, 'admin')
            ->get('/?statuses[]=pre&statuses[]=confirmed')
            ->assertOk()
            ->assertSee('2 selezionati')
            ->assertSee('Pre lead dashboard nascosto')
            ->assertSee('Lead confermato dashboard')
            ->assertDontSee('Lead perso dashboard')
            ->assertDontSee('Pre-lead esclusi');
    }

    public function test_whatsapp_has_its_own_route(): void
    {
        $this->actingAs($this->owner(), 'admin')
            ->get('/whatsapp')
            ->assertOk()
            ->assertSee('Inbox');
    }

    public function test_stale_handoff_chat_does_not_stay_above_recent_activity(): void
    {
        $admin = $this->owner();
        WhatsappConversation::create([
            'contact_phone' => '390000000001',
            'business_phone' => '390000000000',
            'mode' => 'manual',
            'status' => 'open',
            'needs_human' => true,
            'last_message_at' => now()->subDays(2),
        ]);
        WhatsappConversation::create([
            'contact_phone' => '390000000002',
            'business_phone' => '390000000000',
            'mode' => 'auto',
            'status' => 'open',
            'needs_human' => false,
            'last_message_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/whatsapp')
            ->assertOk()
            ->assertSeeInOrder(['390000000002', '390000000001'])
            ->assertDontSee('scrollIntoView', false);
    }

    public function test_whatsapp_inbox_has_search_collapsible_follow_ups_and_lead_link(): void
    {
        $admin = $this->owner();
        $lead = $this->lead(['name' => 'Cliente Collegato']);
        $conversation = WhatsappConversation::create([
            'lead_id' => $lead->id,
            'contact_phone' => '390000000003',
            'business_phone' => '390000000000',
            'mode' => 'manual',
            'status' => 'open',
            'needs_human' => false,
            'last_message_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/whatsapp')
            ->assertOk()
            ->assertSee('Cerca nome, telefono, email')
            ->assertSee('conversation-search', false);

        $this->actingAs($admin, 'admin')
            ->get("/conversations/{$conversation->id}")
            ->assertOk()
            ->assertSee('Apri lead')
            ->assertSee(route('admin.leads.index', ['lead' => $lead]), false)
            ->assertSee('admin-follow-up-summary', false)
            ->assertDontSee('admin-follow-up-summary flex items-center justify-between gap-12 px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-gray md:hidden', false);
    }

    public function test_initial_lead_list_excludes_pre_leads_but_status_filter_can_show_them(): void
    {
        $admin = $this->owner();
        $this->lead(['name' => 'Pre Lead Nascosto', 'status' => 'pre']);
        $this->lead(['name' => 'Lead Confermato Visibile', 'status' => 'confirmed']);

        $this->actingAs($admin, 'admin')
            ->get('/leads')
            ->assertOk()
            ->assertDontSee('Pre Lead Nascosto')
            ->assertSee('Lead Confermato Visibile')
            ->assertSee('Pre-lead esclusi');

        $this->actingAs($admin, 'admin')
            ->get('/leads?status=pre')
            ->assertOk()
            ->assertSee('Pre Lead Nascosto');
    }

    public function test_admin_can_create_a_manual_phone_lead_with_attribution(): void
    {
        $admin = $this->owner();
        $category = LeadCategory::create(['name' => 'Aziendale', 'sort_order' => 1, 'is_active' => true]);

        $response = $this->actingAs($admin, 'admin')
            ->post('/leads', [
                'name' => 'Mario da chiamata',
                'club' => 'Mario SRL',
                'phone' => '+39 333 1234567',
                'email' => 'mario@example.test',
                'city' => 'Vicenza',
                'lead_category_id' => $category->id,
                'product' => 'Polo aziendale',
                'quantity' => 25,
                'crm_notes' => 'Richiamare nel pomeriggio',
                'acquisition_channel' => 'telefono',
                'attribution_confidence' => 'confirmed',
                'attribution_note' => 'Chiamata ricevuta in ufficio',
            ]);

        $lead = Lead::where('email', 'mario@example.test')->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.leads.index', ['lead' => $lead]));

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'confirmed',
            'category' => 'Aziendale',
            'acquisition_channel' => 'telefono',
            'attribution_confidence' => 'confirmed',
            'created_by_admin_user_id' => $admin->id,
            'utm_source' => 'manuale',
            'utm_medium' => 'telefono',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.leads.index', ['lead' => $lead]))
            ->assertOk()
            ->assertSee('Chiamata telefonica')
            ->assertSee('Confermata')
            ->assertSee($admin->name)
            ->assertSee('Chiamata ricevuta in ufficio');
    }

    public function test_manual_lead_requires_confirmation_when_phone_or_email_already_exists(): void
    {
        $admin = $this->owner();
        $this->lead([
            'name' => 'Lead esistente',
            'phone' => '+39 333 7654321',
            'email' => 'duplicato@example.test',
        ]);

        $payload = [
            'name' => 'Possibile duplicato',
            'phone' => '393337654321',
            'email' => 'nuova@example.test',
            'acquisition_channel' => 'telefono',
            'attribution_confidence' => 'unknown',
        ];

        $this->actingAs($admin, 'admin')
            ->post('/leads', $payload)
            ->assertSessionHasErrors('duplicate');

        $this->assertDatabaseMissing('leads', ['name' => 'Possibile duplicato']);

        $this->actingAs($admin, 'admin')
            ->post('/leads', $payload + ['confirm_duplicate' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('leads', ['name' => 'Possibile duplicato']);
    }

    public function test_crm_fields_can_be_updated_from_the_lead_page(): void
    {
        $lead = $this->lead();
        $category = LeadCategory::create(['name' => 'Basket', 'sort_order' => 1, 'is_active' => true]);

        $this->actingAs($this->owner(), 'admin')
            ->patch("/leads/{$lead->id}", [
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'status' => 'confirmed',
                'lead_category_id' => $category->id,
                'lead_quality' => 'Alta',
                'loss_reason' => null,
                'crm_notes' => 'Consegna urgente',
                'utm_campaign' => 'Kit Estate',
                'ad_group' => 'Calcio Veneto',
                'utm_term' => 'kit calcio',
                'search_term' => 'divise calcio personalizzate',
                'acquisition_country' => 'IT',
                'acquisition_region' => 'Veneto',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'category' => 'Basket',
            'lead_category_id' => $category->id,
            'lead_quality' => 'Alta',
            'crm_notes' => 'Consegna urgente',
        ]);
        $this->assertNull($lead->fresh()->ad_group);
        $this->assertNull($lead->fresh()->search_term);
        $this->assertNull($lead->fresh()->acquisition_country);
        $this->assertNull($lead->fresh()->acquisition_region);
    }

    public function test_lead_page_does_not_duplicate_checkout_billing_data(): void
    {
        $lead = $this->lead([
            'billing_customer_type' => 'company',
            'billing_name' => 'Stuart Cliente Srl',
            'billing_email' => 'amministrazione@cliente.test',
            'billing_phone' => '+39 049 1234567',
            'billing_tax_code' => '01234567890',
            'billing_vat_number' => 'IT01234567890',
            'billing_recipient_code' => 'ABC1234',
            'billing_pec' => 'cliente@pec.test',
            'billing_address_line1' => 'Via Roma 10',
            'billing_address_line2' => 'Scala B',
            'billing_postal_code' => '35100',
            'billing_city' => 'Padova',
            'billing_province' => 'PD',
            'billing_country' => 'IT',
            'billing_completed_at' => now(),
        ]);

        $this->actingAs($this->owner(), 'admin')
            ->get(route('admin.leads.index', ['lead' => $lead]))
            ->assertOk()
            ->assertDontSee('Dati di fatturazione')
            ->assertDontSee('IT01234567890')
            ->assertDontSee('cliente@pec.test');
    }

    public function test_admin_generates_a_proposal_from_the_selected_order_with_product_mockups(): void
    {
        Storage::fake('local');
        $lead = $this->lead();
        $sheet = $lead->salesSheets()->create([
            'order_number' => 'ORD-000321',
            'name' => 'Divise evento',
            'status' => 'draft',
            'revenue_total' => 512.40,
            'cost_total' => 250,
            'margin_total' => 262.40,
        ]);

        foreach ([['T-shirt staff', 40], ['Felpa coordinatori', 8]] as $index => [$name, $quantity]) {
            $item = $sheet->items()->create([
                'product_code' => 'DEMO-'.($index + 1),
                'product_name' => $name,
                'configuration_name' => $index === 0 ? 'Maglia BS052' : null,
                'quantity' => $quantity,
                'product_unit_cost' => 5,
                'product_unit_price' => $index === 0 ? 9.50 : 16.55,
                'final_unit_price' => $index === 0 ? 9.50 : 16.55,
                'revenue_total' => $index === 0 ? 380 : 132.40,
                'colors' => [$index === 0 ? 'Nero' : 'Blu'],
            ]);
            if ($index === 0) {
                $item->prints()->create([
                    'print_code' => 'CUORE DTF',
                    'print_name' => 'Lato Cuore DTF',
                    'unit_cost' => 0,
                    'unit_price' => 0,
                ]);
            }
            foreach (['front', 'back'] as $side) {
                $file = UploadedFile::fake()->image("{$index}-{$side}.png", 800, 600);
                $path = $file->store("lead-orders/{$lead->id}/items/{$item->id}/mockups", 'local');
                $item->attachments()->create([
                    'role' => 'mockup_'.$side,
                    'disk' => 'local',
                    'path' => $path,
                    'filename' => $file->getClientOriginalName(),
                    'mime_type' => 'image/png',
                    'size' => $file->getSize(),
                ]);
            }
        }

        $this->actingAs($this->owner(), 'admin')
            ->post(route('admin.leads.orders.proposal.store', [$lead, $sheet]), [
                'proposal_number' => 'PROP-ORD-000321',
                'project_notes' => 'Confermare colori e quantità prima della produzione.',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $proposal = $lead->quotePdfs()->firstOrFail();
        $this->assertSame($sheet->id, $proposal->lead_sales_sheet_id);
        $this->assertSame('625.13', $proposal->amount);
        $this->assertSame('625.13', $lead->fresh()->quote_amount);
        Storage::disk('local')->assertExists($proposal->path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($proposal->path));

        $proposal->delete();

        $this->post(route('admin.leads.orders.checkout.store', [$lead, $sheet]), [
            'payment_type' => 'deposit',
            'deposit_amount' => 100,
            'expires_at' => now()->addDays(7)->toDateString(),
        ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $deposit = $sheet->fresh()->checkouts()->firstOrFail();
        $this->assertSame(64, strlen($deposit->token));
        $this->assertNull($deposit->lead_quote_pdf_id);
        $this->assertSame('deposit', $deposit->payment_type);
        $this->assertSame('81.97', $deposit->taxable_amount);
        $this->assertSame('18.03', $deposit->vat_amount);
        $this->assertSame('100.00', $deposit->total_amount);
        $this->assertSame('625.13', $deposit->order_total_amount);
        $this->assertCount(2, $deposit->items);
        $this->assertSame('Maglia BS052', $deposit->items[0]['name']);
        $this->assertStringNotContainsString('Maglia BS052', $deposit->items[0]['details']);
        $this->assertStringNotContainsString('Lato Cuore DTF', $deposit->items[0]['details']);
        $this->assertSame('Nero', $deposit->items[0]['details']);

        $deposit->update([
            'status' => 'bank_transfer_pending',
            'order_status' => 'proforma_pending',
            'details_completed_at' => now(),
            'contact_first_name' => 'Mario',
            'contact_last_name' => 'Rossi',
            'contact_email' => 'mario.rossi@example.test',
            'contact_phone' => '3331234567',
            'shipping_company' => 'MV Group Italia srls',
            'shipping_first_name' => 'Mario',
            'shipping_last_name' => 'Rossi',
            'shipping_address' => 'Via Roma 10',
            'shipping_postal_code' => '35100',
            'shipping_city' => 'Padova',
            'shipping_province' => 'PD',
            'billing_customer_type' => 'private',
            'billing_first_name' => 'Mario',
            'billing_last_name' => 'Rossi',
            'billing_tax_code' => 'RSSMRA80A01G224A',
            'billing_address' => 'Via Roma 10',
            'billing_postal_code' => '35100',
            'billing_city' => 'Padova',
            'billing_province' => 'PD',
            'payment_method' => 'bank',
        ]);

        $this->get(route('admin.checkout-orders.index'))
            ->assertOk()
            ->assertSee('Ordini checkout')
            ->assertSee('ORD-000321')
            ->assertSee('Mario Rossi')
            ->assertSee('Proforma da generare')
            ->assertSee('€ 100,00')
            ->assertSee(route('admin.checkout-orders.show', $deposit), false);

        $this->get(route('admin.checkout-orders.show', $deposit))
            ->assertOk()
            ->assertSee('ORD-000321')
            ->assertSee('Prodotti ordinati')
            ->assertSee('Maglia BS052')
            ->assertSee('Contatti e spedizione')
            ->assertSee('Riepilogo economico')
            ->assertSee('Bonifico bancario')
            ->assertSee('Genera proforma acconto')
            ->assertSee('Apri lead');

        $deposit->update(['status' => 'paid']);
        $this->get(route('admin.checkout-orders.show', $deposit))
            ->assertOk()
            ->assertDontSee('Genera proforma acconto');
        $this->post(route('admin.checkout-orders.balance.store', $deposit))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('checkout_url');
        $balance = $sheet->fresh()->checkouts()->where('payment_type', 'balance')->firstOrFail();
        $this->assertSame('525.13', $balance->total_amount);
        $this->assertCount(2, $sheet->fresh()->checkouts);

        $this->post(route('admin.checkout-orders.proforma.store', $deposit))
            ->assertSessionHasNoErrors();
        $deposit->refresh();
        $this->assertNotNull($deposit->admin_document_id);
        $this->assertSame('proforma_pending', $deposit->order_status);
        $this->assertSame('100.00', $deposit->proforma->fresh()->total);
        $this->assertSame('Mario Rossi c/o MV Group Italia srls', $deposit->proforma->fresh()->shipping_name);
        $depositProformaItem = $deposit->proforma->items()->firstOrFail();
        $this->assertSame('ACCONTO ORDINE ORD-000321', $depositProformaItem->description);
        $this->assertNull($depositProformaItem->item_code);
        $depositSummaryItems = $deposit->proforma->items()->where('informational', true)->orderBy('position')->get();
        $this->assertSame("\u{00A0}", $depositSummaryItems->first()->description);
        $summaryHeading = $depositSummaryItems->firstWhere('description', 'RIEPILOGO ORDINE');
        $this->assertNotNull($summaryHeading);
        $this->assertSame('0.00', $summaryHeading->quantity);
        $summaryProduct = $depositSummaryItems->firstWhere('description', 'MAGLIA BS052 - NERO');
        $this->assertNotNull($summaryProduct);
        $this->assertSame('0.00', $summaryProduct->line_subtotal);
        $this->assertFalse($depositSummaryItems->contains('description', 'SPESE DI TRASPORTO'));
        $this->assertSame('Proforma nr. BOZZA.pdf', app(\App\Services\AdminDocumentPdfService::class)->filename($deposit->proforma));

        $balance->update([
            ...$deposit->only([
                'contact_email', 'contact_first_name', 'contact_last_name', 'contact_phone',
                'shipping_first_name', 'shipping_last_name', 'shipping_address', 'shipping_postal_code',
                'shipping_city', 'shipping_province', 'billing_customer_type', 'billing_first_name',
                'billing_last_name', 'billing_tax_code', 'billing_address', 'billing_postal_code',
                'billing_city', 'billing_province',
            ]),
            'status' => 'bank_transfer_pending',
            'details_completed_at' => now(),
        ]);
        $this->post(route('admin.checkout-orders.proforma.store', $balance))->assertSessionHasNoErrors();
        $balance->refresh();
        $balanceProforma = $balance->proforma->fresh('items');
        $this->assertSame('525.13', $balanceProforma->total);
        $this->assertSame('MAGLIA BS052 - NERO', $balanceProforma->items->first()->description);
        $this->assertSame('ACCONTO RIF. NS. PROFORMA NR. ---', $balanceProforma->items->last()->description);
        $this->assertNull($balanceProforma->items->last()->item_code);
        $this->assertSame('-81.97', $balanceProforma->items->last()->unit_price);
        $this->assertSame('0.01', $balanceProforma->rounding_adjustment);

        $deletedProformaId = $balanceProforma->id;
        $this->delete(route('admin.documents.destroy', $balanceProforma))->assertSessionHasNoErrors();
        $this->assertNull($balance->fresh()->admin_document_id);
        $this->assertSame('balance_pending', $balance->fresh()->order_status);
        $this->assertSame('balance_pending', $deposit->fresh()->order_status);
        $this->get(route('admin.checkout-orders.show', $balance))
            ->assertRedirect(route('admin.checkout-orders.show', $deposit));
        $this->get(route('admin.checkout-orders.show', $deposit))
            ->assertOk()
            ->assertSee('Proforma da generare')
            ->assertSee('Checkout saldo completato')
            ->assertSee('Genera proforma saldo');

        $this->post(route('admin.checkout-orders.proforma.store', $balance))->assertSessionHasNoErrors();
        $balance->refresh();
        $this->assertNotNull($balance->admin_document_id);
        $this->assertNotSame($deletedProformaId, $balance->admin_document_id);
        $this->assertDatabaseHas('admin_documents', ['id' => $balance->admin_document_id, 'type' => 'proforma']);

        $this->delete(route('admin.documents.destroy', $deposit->proforma))->assertSessionHasNoErrors();
        $this->assertNull($deposit->fresh()->admin_document_id);
        $this->assertSame('proforma_pending', $deposit->fresh()->order_status);
        $this->get(route('admin.checkout-orders.show', $deposit))
            ->assertOk()
            ->assertSee('Piano di pagamento')
            ->assertSee('Acconto')
            ->assertSee('Saldo')
            ->assertSee('Proforma da generare');

        $this->post(route('admin.checkout-orders.proforma.store', $deposit))->assertSessionHasNoErrors();
        $deposit->refresh()->update(['status' => 'bank_transfer_pending']);
        $this->mock(CheckoutOrderCustomerNotificationService::class, function ($mock) {
            $mock->shouldReceive('orderProcessing')->once()->andReturn(['sent' => true, 'channel' => 'whatsapp']);
        });
        $this->patch(route('admin.checkout-orders.payment.paid', $deposit))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
        $this->assertSame('paid', $deposit->fresh()->status);
        $this->assertSame('paid', $deposit->proforma->fresh()->payment_status);
        $this->assertSame('in_production', $deposit->fresh()->order_status);

        $this->patch(route('admin.checkout-orders.payment.paid', $balance))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
        $this->assertSame('paid', $balance->fresh()->status);
        $this->assertSame('paid', $balance->proforma->fresh()->payment_status);
        $this->assertSame('ready', $deposit->fresh()->order_status);
        $this->assertSame('ready', $balance->fresh()->order_status);

        $this->patch(route('admin.checkout-orders.payment.paid', $deposit))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'già pagato'));

        $paidDepositProforma = $deposit->fresh()->proforma;
        $this->delete(route('admin.documents.destroy', $paidDepositProforma))->assertSessionHasNoErrors();
        $this->assertNull($deposit->fresh()->admin_document_id);
        $this->assertSame('bank_transfer_pending', $deposit->fresh()->status);
        $this->assertSame('proforma_pending', $deposit->fresh()->order_status);
        $this->assertSame('proforma_pending', $balance->fresh()->order_status);

        $this->post(route('admin.checkout-orders.proforma.store', $deposit))->assertSessionHasNoErrors();
        $this->assertSame('bank_transfer_pending', $deposit->fresh()->status);
        $this->assertSame('proforma_pending', $deposit->fresh()->order_status);
        $this->assertSame('proforma_pending', $balance->fresh()->order_status);

        $this->mock(CheckoutOrderCustomerNotificationService::class, function ($mock) {
            $mock->shouldReceive('orderProcessing')->once()->andReturn(['sent' => true, 'channel' => 'whatsapp']);
            $mock->shouldReceive('shipped')->once()->andReturn(['sent' => true, 'channel' => 'whatsapp']);
        });

        $this->patch(route('admin.checkout-orders.status.update', $deposit), [
            'order_status' => 'in_production',
        ])->assertSessionHasNoErrors();
        $this->assertSame('in_production', $deposit->fresh()->order_status);

        $this->patch(route('admin.checkout-orders.status.update', $deposit), [
            'order_status' => 'shipped',
        ])->assertSessionHasNoErrors();
        $this->assertSame('shipped', $deposit->fresh()->order_status);

        $this->get(route('admin.checkout-orders.show', $deposit))
            ->assertOk()
            ->assertSee('Aggiorna stato')
            ->assertDontSee('value="completed"', false)
            ->assertDontSee('Segna come completato');
    }

    public function test_product_mockup_accepts_webp_and_normalizes_it_for_the_pdf(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner(), 'admin');
        $lead = $this->lead();
        $sheet = $lead->salesSheets()->create([
            'order_number' => 'ORD-WEBP',
            'name' => 'Ordine WEBP',
            'status' => 'draft',
            'revenue_total' => 100,
            'cost_total' => 50,
            'margin_total' => 50,
        ]);
        $item = $sheet->items()->create([
            'product_code' => 'WEBP-1',
            'product_name' => 'Maglia WEBP',
            'quantity' => 10,
            'product_unit_cost' => 5,
            'product_unit_price' => 10,
            'final_unit_price' => 10,
            'revenue_total' => 100,
        ]);

        $image = imagecreatetruecolor(40, 40);
        imagefill($image, 0, 0, imagecolorallocate($image, 32, 106, 233));
        ob_start();
        imagewebp($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        Livewire::test(LeadSalesSheetComponent::class, ['leadId' => $lead->id, 'sheetId' => $sheet->id])
            ->set("itemMockupFronts.{$item->id}", UploadedFile::fake()->createWithContent('mockup-fronte.webp', $contents))
            ->assertHasNoErrors()
            ->assertSee('Mockup fronte salvato e pronto per il PDF.');

        $attachment = $item->attachments()->where('role', 'mockup_front')->firstOrFail();
        $this->assertSame('mockup-fronte.png', $attachment->filename);
        $this->assertSame('image/png', $attachment->mime_type);
        $this->assertStringEndsWith('.png', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_sales_sheet_calculates_product_print_and_margin(): void
    {
        $admin = $this->owner();
        $lead = $this->lead();
        $product = CrmProduct::create(['code' => 'TS01', 'name' => 'T-shirt', 'unit_cost' => 5, 'is_active' => true]);
        $product->priceTiers()->create(['min_quantity' => 10, 'max_quantity' => 19, 'unit_price' => 12]);
        $print = CrmPrintType::create(['code' => 'CUORE1', 'name' => 'Lato cuore 1 colore', 'is_active' => true]);
        $print->priceTiers()->create(['min_quantity' => 10, 'max_quantity' => 19, 'unit_cost' => 1, 'unit_price' => 3]);

        $this->actingAs($admin, 'admin')->post("/leads/{$lead->id}/sales-sheet/items", ['product_id' => $product->id, 'quantity' => 12])->assertSessionHasNoErrors();
        $item = $lead->fresh()->salesSheet->items()->firstOrFail();
        $this->actingAs($admin, 'admin')->post("/leads/{$lead->id}/sales-sheet/items/{$item->id}/prints", ['print_type_id' => $print->id])->assertSessionHasNoErrors();

        $sheet = $lead->fresh()->salesSheet;
        $this->assertSame('189.90', $sheet->revenue_total);
        $this->assertSame('81.90', $sheet->cost_total);
        $this->assertSame('108.00', $sheet->margin_total);
        $this->assertSame('180.00', $sheet->product_revenue_total);
        $this->assertSame('9.90', $sheet->shipping_charge);
        $this->assertSame('9.90', $sheet->shipping_cost);
        $this->assertSame('108.00', $lead->fresh()->margin_amount);
    }

    public function test_shipping_is_charged_through_250_and_absorbed_above_the_threshold(): void
    {
        $this->actingAs($this->owner(), 'admin');
        $lead = $this->lead();
        $product = CrmProduct::create(['code' => 'SHIP01', 'name' => 'Prodotto spedizione', 'unit_cost' => 5, 'is_active' => true]);
        $product->priceTiers()->create(['min_quantity' => 1, 'max_quantity' => 20, 'unit_price' => 25]);

        Livewire::test(LeadSalesSheetComponent::class, ['leadId' => $lead->id])
            ->set('productId', (string) $product->id)
            ->set('quantity', '10')
            ->call('addProduct')
            ->assertHasNoErrors();

        $item = $lead->fresh()->salesSheet->items()->firstOrFail();
        $sheet = $lead->fresh()->salesSheet;
        $this->assertSame('250.00', $sheet->product_revenue_total);
        $this->assertSame('8.12', $sheet->shipping_charge);
        $this->assertSame('258.12', $sheet->revenue_total);
        $this->assertSame('200.00', $sheet->margin_total);

        Livewire::test(LeadSalesSheetComponent::class, ['leadId' => $lead->id])
            ->set("itemFinalPrices.{$item->id}", '26.00')
            ->call('updateFinalPrice', $item->id)
            ->assertHasNoErrors();

        $sheet = $lead->fresh()->salesSheet;
        $this->assertSame('260.00', $sheet->product_revenue_total);
        $this->assertSame('0.00', $sheet->shipping_charge);
        $this->assertSame('8.12', $sheet->shipping_cost);
        $this->assertSame('201.88', $sheet->margin_total);

        Livewire::test(LeadSalesSheetComponent::class, ['leadId' => $lead->id])
            ->set('shippingFee', '12.50')
            ->set('freeShippingThreshold', '300.00')
            ->call('saveShipping')
            ->assertHasNoErrors()
            ->assertSee('Regole di spedizione aggiornate.');

        $sheet = $lead->fresh()->salesSheet;
        $this->assertSame('12.50', $sheet->shipping_charge);
        $this->assertSame('12.50', $sheet->shipping_cost);
        $this->assertSame('272.50', $sheet->revenue_total);
        $this->assertSame('210.00', $sheet->margin_total);
    }

    public function test_product_sheet_shows_cac_profit_and_economic_status(): void
    {
        $this->actingAs($this->owner(), 'admin');
        $lead = $this->lead(['status' => 'order_completed']);
        $product = CrmProduct::create(['code' => 'ECON01', 'name' => 'Prodotto redditizio', 'unit_cost' => 10, 'is_active' => true]);
        $product->priceTiers()->create(['min_quantity' => 1, 'max_quantity' => 20, 'unit_price' => 20]);
        $googleAds = \Mockery::mock(GoogleAdsReportingService::class);
        $googleAds->shouldReceive('performance')->andReturn(['available' => true, 'spend' => 20]);
        $this->app->instance(GoogleAdsReportingService::class, $googleAds);

        Livewire::test(LeadSalesSheetComponent::class, ['leadId' => $lead->id])
            ->set('productId', (string) $product->id)
            ->set('quantity', '5')
            ->call('addProduct')
            ->assertHasNoErrors()
            ->assertSee('CAC medio')
            ->assertSee('Questo è il primo ordine del cliente: il CAC medio degli ultimi 30 giorni viene sottratto qui una sola volta.')
            ->assertSee('€ 20,00')
            ->assertSee('€ 30,00')
            ->assertSee('€ 17,56')
            ->assertSee('27,7%')
            ->assertSee('Non sostenibile');
    }

    public function test_reorder_does_not_apply_customer_acquisition_cost_again(): void
    {
        $this->actingAs($this->owner(), 'admin');
        $lead = $this->lead(['status' => 'order_completed']);
        $lead->salesSheets()->create([
            'order_number' => 'ORD-000001',
            'name' => 'Primo ordine',
            'revenue_total' => 100,
            'cost_total' => 50,
            'margin_total' => 50,
        ]);
        $reorder = $lead->salesSheets()->create([
            'order_number' => 'ORD-000002',
            'name' => 'Riordino',
            'revenue_total' => 100,
            'cost_total' => 60,
            'margin_total' => 40,
        ]);
        $reorder->items()->create([
            'product_code' => 'REORDER',
            'product_name' => 'Prodotto riordinato',
            'quantity' => 1,
            'product_unit_cost' => 60,
            'product_unit_price' => 100,
            'final_unit_price' => 100,
            'cost_total' => 60,
            'revenue_total' => 100,
            'margin_total' => 40,
        ]);
        $googleAds = \Mockery::mock(GoogleAdsReportingService::class);
        $googleAds->shouldNotReceive('performance');
        $this->app->instance(GoogleAdsReportingService::class, $googleAds);

        Livewire::test(LeadSalesSheetComponent::class, ['leadId' => $lead->id, 'sheetId' => $reorder->id])
            ->assertSee('CAC riordino')
            ->assertSee('Il costo di acquisizione viene attribuito soltanto al primo ordine del cliente. Per questo riordino il CAC è pari a zero.')
            ->assertSee('€ 0,00')
            ->assertSee('€ 40,00')
            ->assertSee('Senza un nuovo CAC restano 40,0% del totale vendita.');
    }

    public function test_lost_lead_requires_a_standard_loss_reason(): void
    {
        $lead = $this->lead(['status' => 'confirmed']);

        $this->actingAs($this->owner(), 'admin')
            ->patch("/leads/{$lead->id}", ['status' => 'lost'])
            ->assertSessionHasErrors('loss_reason');

        $this->actingAs($this->owner(), 'admin')
            ->patch("/leads/{$lead->id}", ['status' => 'lost', 'loss_reason' => 'no_response'])
            ->assertSessionHasNoErrors();

        $this->assertSame('no_response', $lead->fresh()->loss_reason);
    }

    public function test_sales_sheet_can_be_updated_with_livewire_without_reloading_the_lead_page(): void
    {
        $this->actingAs($this->owner(), 'admin');
        $lead = $this->lead();
        $product = CrmProduct::create(['code' => 'POLO01', 'name' => 'Polo', 'unit_cost' => 8, 'is_active' => true]);
        $product->priceTiers()->create(['min_quantity' => 1, 'max_quantity' => 20, 'unit_price' => 18]);

        Livewire::test(LeadSalesSheetComponent::class, ['leadId' => $lead->id])
            ->set('productId', (string) $product->id)
            ->set('configurationName', 'Polo staff evento')
            ->set('quantity', '5')
            ->call('addProduct')
            ->assertHasNoErrors()
            ->assertSee('Prodotto aggiunto alla scheda vendita.')
            ->assertSee('90,00')
            ->assertSee('IVA 22%')
            ->assertSee('21,59')
            ->assertSee('Totale da pagare IVA inclusa')
            ->assertSee('119,71')
            ->set('shippingFee', '0')
            ->call('saveShipping')
            ->assertHasNoErrors()
            ->assertSee('19,80')
            ->assertSee('109,80');

        $this->assertDatabaseHas('lead_sales_items', [
            'product_code' => 'POLO01',
            'configuration_name' => 'Polo staff evento',
            'quantity' => 5,
            'revenue_total' => 90,
        ]);
        $this->assertSame('Polo staff evento', $lead->fresh()->product);
    }

    public function test_final_price_includes_prints_and_can_be_overridden(): void
    {
        $this->actingAs($this->owner(), 'admin');
        $lead = $this->lead();
        $product = CrmProduct::create(['code' => 'TS10', 'name' => 'T-shirt', 'unit_cost' => 5, 'is_active' => true]);
        $product->priceTiers()->create(['min_quantity' => 1, 'max_quantity' => 20, 'unit_price' => 12]);
        $print = CrmPrintType::create(['code' => 'FRONTE', 'name' => 'Stampa fronte', 'is_active' => true]);
        $print->priceTiers()->create(['min_quantity' => 1, 'max_quantity' => 20, 'unit_cost' => 1, 'unit_price' => 3]);

        Livewire::test(LeadSalesSheetComponent::class, ['leadId' => $lead->id])
            ->set('productId', (string) $product->id)
            ->set('quantity', '10')
            ->assertSet('finalUnitPrice', '12.00')
            ->call('addProduct')
            ->assertHasNoErrors();

        $item = $lead->fresh()->salesSheet->items()->firstOrFail();

        Livewire::test(LeadSalesSheetComponent::class, ['leadId' => $lead->id])
            ->set("printTypeIds.{$item->id}", (string) $print->id)
            ->call('addPrint', $item->id)
            ->assertHasNoErrors()
            ->assertSet("itemFinalPrices.{$item->id}", '15.00')
            ->set("itemFinalPrices.{$item->id}", '20.00')
            ->call('updateFinalPrice', $item->id)
            ->assertHasNoErrors();

        $this->assertSame('20.0000', $item->fresh()->final_unit_price);
        $this->assertSame('200.00', $item->fresh()->revenue_total);
        $this->assertTrue($item->fresh()->final_price_overridden);
    }

    public function test_product_materials_are_private_and_order_send_is_queued_with_readable_name(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');
        $account = EmailAccount::create([
            'admin_user_id' => $admin->id,
            'email' => 'operatore@stuart-company.com',
            'from_name' => 'Operatore',
            'username' => 'operatore@stuart-company.com',
            'is_active' => true,
        ]);
        $account->setPassword('secret');
        $account->save();
        $lead = $this->lead(['name' => 'Cliente Festival']);
        $product = CrmProduct::create(['code' => 'POLO10', 'name' => 'Polo', 'unit_cost' => 8, 'is_active' => true]);
        $product->priceTiers()->create(['min_quantity' => 1, 'max_quantity' => 20, 'unit_price' => 18]);

        Livewire::test(LeadSalesSheetComponent::class, ['leadId' => $lead->id])
            ->set('productId', (string) $product->id)
            ->set('quantity', '5')
            ->call('addProduct')
            ->assertHasNoErrors();
        $item = $lead->fresh()->salesSheet->items()->firstOrFail();

        Livewire::test(LeadSalesSheetComponent::class, ['leadId' => $lead->id])
            ->set("itemColors.{$item->id}", "Blu navy — 3 pz\nBianco — 2 pz")
            ->set("itemNotes.{$item->id}", 'Logo lato cuore')
            ->set("itemUploads.{$item->id}", [UploadedFile::fake()->create('logo-finale.ai', 100, 'application/postscript')])
            ->set('orderName', 'Staff Festival Roma')
            ->call('sendOrder')
            ->assertHasNoErrors()
            ->assertSee('Ordine-Lead-staff-festival-roma.zip');

        $item->refresh();
        $this->assertSame(['Blu navy — 3 pz', 'Bianco — 2 pz'], $item->colors);
        $this->assertSame('Logo lato cuore', $item->notes);
        Storage::disk('local')->assertExists($item->attachments()->firstOrFail()->path);
        $this->assertDatabaseHas('lead_order_dispatches', [
            'filename' => 'Ordine-Lead-staff-festival-roma.zip',
            'to_email' => 'alessandro@stuart-company.com',
            'status' => 'pending',
        ]);
        Queue::assertPushed(SendLeadOrder::class);
        $this->assertSame('daniele.dallavia@gmail.com', config('lead_orders.cc.email'));

        $dispatch = $lead->fresh()->salesSheet->dispatches()->firstOrFail();
        $package = app(LeadOrderPackageService::class)->create($lead->fresh()->salesSheet, $dispatch);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($package['path'])));
        $this->assertNotFalse($zip->locateName('Riepilogo-ordine.pdf'));
        $this->assertNotFalse($zip->locateName('01-polo/dettagli.txt'));
        $this->assertNotFalse($zip->locateName('01-polo/grafiche/'.$item->attachments()->firstOrFail()->id.'-logo-finale.ai'));
        $zip->close();
    }

    public function test_categories_can_be_disabled_and_only_unused_categories_can_be_deleted(): void
    {
        $admin = $this->owner();
        $used = LeadCategory::create(['name' => 'Categoria usata', 'sort_order' => 10, 'is_active' => true]);
        $unused = LeadCategory::create(['name' => 'Categoria libera', 'sort_order' => 20, 'is_active' => true]);
        $this->lead(['lead_category_id' => $used->id, 'category' => $used->name]);

        $this->actingAs($admin, 'admin')
            ->patch("/settings/crm-catalog/categories/{$used->id}/toggle")
            ->assertSessionHasNoErrors();
        $this->assertFalse($used->fresh()->is_active);

        $this->actingAs($admin, 'admin')
            ->delete("/settings/crm-catalog/categories/{$used->id}")
            ->assertSessionHasErrors('category');
        $this->assertDatabaseHas('lead_categories', ['id' => $used->id]);

        $this->actingAs($admin, 'admin')
            ->delete("/settings/crm-catalog/categories/{$unused->id}")
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('lead_categories', ['id' => $unused->id]);
    }

    public function test_products_prints_and_their_price_tiers_can_be_modified_and_deleted(): void
    {
        $admin = $this->owner();
        $product = CrmProduct::create(['code' => 'TS01', 'name' => 'T-shirt', 'unit_cost' => 4, 'is_active' => true]);
        $productTier = $product->priceTiers()->create(['min_quantity' => 1, 'max_quantity' => 9, 'unit_price' => 12]);
        $print = CrmPrintType::create(['code' => 'CUORE', 'name' => 'Lato cuore', 'is_active' => true]);
        $printTier = $print->priceTiers()->create(['min_quantity' => 1, 'max_quantity' => 9, 'unit_cost' => 1, 'unit_price' => 3]);

        $this->actingAs($admin, 'admin')->patch("/settings/crm-catalog/products/{$product->id}", [
            'code' => 'TS02', 'name' => 'T-shirt Premium', 'unit_cost' => 5.5,
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->patch("/settings/crm-catalog/product-tiers/{$productTier->id}", [
            'min_quantity' => 10, 'max_quantity' => 19, 'unit_price' => 10.5,
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->patch("/settings/crm-catalog/prints/{$print->id}", [
            'code' => 'CUORE2', 'name' => 'Lato cuore due colori',
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->patch("/settings/crm-catalog/print-tiers/{$printTier->id}", [
            'min_quantity' => 10, 'max_quantity' => 19, 'unit_cost' => 1.5, 'unit_price' => 4,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('crm_products', ['id' => $product->id, 'code' => 'TS02', 'name' => 'T-shirt Premium', 'unit_cost' => 5.5]);
        $this->assertDatabaseHas('crm_product_price_tiers', ['id' => $productTier->id, 'min_quantity' => 10, 'unit_price' => 10.5]);
        $this->assertDatabaseHas('crm_print_types', ['id' => $print->id, 'code' => 'CUORE2', 'name' => 'Lato cuore due colori']);
        $this->assertDatabaseHas('crm_print_price_tiers', ['id' => $printTier->id, 'min_quantity' => 10, 'unit_cost' => 1.5, 'unit_price' => 4]);

        $this->actingAs($admin, 'admin')->delete("/settings/crm-catalog/product-tiers/{$productTier->id}")->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->delete("/settings/crm-catalog/print-tiers/{$printTier->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('crm_product_price_tiers', ['id' => $productTier->id]);
        $this->assertDatabaseMissing('crm_print_price_tiers', ['id' => $printTier->id]);

        $this->actingAs($admin, 'admin')->delete("/settings/crm-catalog/products/{$product->id}")->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->delete("/settings/crm-catalog/prints/{$print->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('crm_products', ['id' => $product->id]);
        $this->assertDatabaseMissing('crm_print_types', ['id' => $print->id]);
    }

    public function test_operator_can_be_given_read_only_access_to_the_crm_catalog(): void
    {
        $operator = $this->operator(['crm_catalog.view']);

        $this->actingAs($operator, 'admin')
            ->get('/settings/crm-catalog')
            ->assertOk()
            ->assertSee('Catalogo CRM')
            ->assertDontSee('Aggiungi categoria');

        $this->actingAs($operator, 'admin')
            ->post('/settings/crm-catalog/categories', ['name' => 'Non autorizzata'])
            ->assertForbidden();
    }

    public function test_operator_can_be_given_management_access_to_the_crm_catalog(): void
    {
        $operator = $this->operator(['crm_catalog.view', 'crm_catalog.manage']);

        $this->actingAs($operator, 'admin')
            ->get('/settings/crm-catalog')
            ->assertOk()
            ->assertSee('Aggiungi categoria');

        $this->actingAs($operator, 'admin')
            ->post('/settings/crm-catalog/categories', ['name' => 'Merchandising', 'sort_order' => 5])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('lead_categories', ['name' => 'Merchandising']);
    }

    public function test_operator_without_catalog_permissions_cannot_access_it(): void
    {
        $this->actingAs($this->operator(), 'admin')
            ->get('/settings/crm-catalog')
            ->assertForbidden();
    }

    public function test_catalog_permissions_can_be_assigned_from_the_admin_user_form(): void
    {
        $this->actingAs($this->owner(), 'admin')
            ->post('/settings/users', [
                'name' => 'Operatore Catalogo',
                'email' => 'catalogo@example.com',
                'password' => 'password-sicura',
                'password_confirmation' => 'password-sicura',
                'role' => 'operator',
                'permissions' => ['crm_catalog.view', 'crm_catalog.manage'],
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $operator = AdminUser::where('email', 'catalogo@example.com')->firstOrFail();

        $this->assertSame(['crm_catalog.view', 'crm_catalog.manage'], $operator->permissions);
    }

    public function test_admin_user_form_submits_permission_keys_instead_of_numeric_indexes(): void
    {
        $operator = $this->operator();

        $this->actingAs($this->owner(), 'admin')
            ->get("/settings/users?edit={$operator->id}")
            ->assertOk()
            ->assertSee('value="crm_catalog.view"', escape: false)
            ->assertSee('value="crm_catalog.manage"', escape: false)
            ->assertDontSee('name="permissions[]" value="0"', escape: false);
    }

    public function test_obsolete_permissions_do_not_block_updating_an_operator(): void
    {
        $operator = $this->operator(['legacy.permission']);

        $this->actingAs($this->owner(), 'admin')
            ->patch("/settings/users/{$operator->id}", [
                'name' => $operator->name,
                'email' => $operator->email,
                'role' => 'operator',
                'permissions' => ['legacy.permission', 'crm_catalog.view', 'crm_catalog.manage'],
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['crm_catalog.view', 'crm_catalog.manage'],
            $operator->fresh()->permissions,
        );
    }

    public function test_grouped_variants_use_the_total_quantity_for_product_and_print_tiers(): void
    {
        $lead = $this->lead(['status' => 'confirmed']);
        $product = CrmProduct::create(['code' => 'MAG', 'name' => 'Maglia', 'unit_cost' => 6, 'is_active' => true]);
        $product->priceTiers()->create(['min_quantity' => 1, 'max_quantity' => 19, 'unit_cost' => 6, 'unit_price' => 12]);
        $product->priceTiers()->create(['min_quantity' => 20, 'max_quantity' => null, 'unit_cost' => 4, 'unit_price' => 9]);
        $print = CrmPrintType::create(['code' => 'ST', 'name' => 'Stampa', 'is_active' => true]);
        $print->priceTiers()->create(['min_quantity' => 1, 'max_quantity' => 19, 'unit_cost' => 3, 'unit_price' => 5]);
        $print->priceTiers()->create(['min_quantity' => 20, 'max_quantity' => null, 'unit_cost' => 1, 'unit_price' => 2]);
        $sheet = $lead->salesSheets()->create(['order_number' => 'ORD-TEST', 'name' => 'Test gruppi', 'prices_include_vat' => false]);

        foreach (range(1, 4) as $variant) {
            $item = $sheet->items()->create([
                'crm_product_id' => $product->id, 'product_code' => 'MAG', 'product_name' => 'Maglia',
                'configuration_name' => "Grafica {$variant}", 'pricing_group_uuid' => 'same-group',
                'pricing_group_name' => 'Maglie stessa lavorazione', 'quantity' => 10,
                'product_unit_cost' => 6, 'product_unit_price' => 12,
            ]);
            $item->prints()->create(['crm_print_type_id' => $print->id, 'print_code' => 'ST', 'print_name' => 'Stampa', 'unit_cost' => 3, 'unit_price' => 5]);
        }

        app(LeadSalesSheetService::class)->recalculate($sheet);

        $this->assertSame(4.0, (float) $sheet->fresh()->items->first()->product_unit_cost);
        $this->assertSame(9.0, (float) $sheet->fresh()->items->first()->product_unit_price);
        $this->assertSame(1.0, (float) $sheet->fresh()->items->first()->prints->first()->unit_cost);
        $this->assertSame(2.0, (float) $sheet->fresh()->items->first()->prints->first()->unit_price);
    }

    public function test_same_lead_can_have_multiple_orders_with_separate_adjustments(): void
    {
        $admin = $this->owner();
        $lead = $this->lead(['status' => 'confirmed']);
        $first = $lead->salesSheets()->create(['order_number' => 'ORD-000001', 'name' => 'Primo', 'revenue_total' => 100, 'margin_total' => 40]);

        $this->actingAs($admin, 'admin')->post("/leads/{$lead->id}/orders", ['name' => 'Riordino agosto'])
            ->assertSessionHasNoErrors();

        $this->assertCount(2, $lead->fresh()->salesSheets);
        $this->assertDatabaseHas('lead_sales_sheets', ['lead_id' => $lead->id, 'name' => 'Riordino agosto']);
        $this->assertDatabaseHas('lead_sales_sheets', ['id' => $first->id, 'name' => 'Primo']);

        $second = $lead->fresh()->salesSheets->firstWhere('name', 'Riordino agosto');
        $this->actingAs($admin, 'admin')->delete("/leads/{$lead->id}/orders/{$second->id}")
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $lead->fresh()->salesSheets);
        $this->assertDatabaseMissing('lead_sales_sheets', ['id' => $second->id]);
    }

    public function test_discount_rounding_and_shipping_are_calculated_per_order(): void
    {
        $lead = $this->lead(['status' => 'confirmed']);
        $sheet = $lead->salesSheets()->create([
            'order_number' => 'ORD-DISCOUNT', 'name' => 'Ordine scontato',
            'discount_type' => 'percentage', 'discount_value' => 10,
            'rounding_adjustment' => -0.10, 'shipping_fee' => 9.90,
            'free_shipping_threshold' => 250,
        ]);
        $sheet->items()->create([
            'product_code' => 'TEST', 'product_name' => 'Prodotto test', 'quantity' => 20,
            'product_unit_cost' => 5, 'product_unit_price' => 15,
        ]);

        app(LeadSalesSheetService::class)->recalculate($sheet);
        $sheet->refresh();

        $this->assertSame(30.0, (float) $sheet->discount_amount);
        $this->assertSame(0.0, (float) $sheet->shipping_charge);
        $this->assertSame(269.9, (float) $sheet->revenue_total);
        $this->assertEquals(59.38, $sheet->vatTotals()['vat']);
        $this->assertEquals(329.28, $sheet->vatTotals()['gross']);
        $this->assertSame(160.0, (float) $sheet->margin_total);
    }

    public function test_leads_can_be_exported_as_filtered_csv(): void
    {
        $admin = $this->owner();
        $this->lead(['name' => 'Cliente incluso', 'status' => 'confirmed']);
        $this->lead(['name' => 'Cliente escluso', 'status' => 'lost']);

        $response = $this->actingAs($admin, 'admin')->get('/leads/export.csv?status=confirmed');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Cliente incluso', $csv);
        $this->assertStringNotContainsString('Cliente escluso', $csv);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function test_live_mockup_graphics_are_visible_and_downloadable_from_the_lead(): void
    {
        Storage::fake('live_mockups');
        Storage::fake('live_mockup_assets');
        Storage::disk('live_mockups')->put(
            'live-mockups/CRM1234/grafica.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>'
        );
        Storage::disk('live_mockup_assets')->put(
            'premium/premium-black-front.webp',
            'mockup-webp'
        );

        $admin = $this->owner();
        $lead = $this->lead([
            'live_mockup_used' => true,
            'cta_origin' => 'live_mockup',
            'live_mockup_color' => 'Black',
            'live_mockup_front_file' => 'live-mockups/CRM1234/grafica.svg',
            'live_mockup_configured_at' => now(),
            'calculator_model' => 'Premium',
            'calculator_quantity' => 100,
            'calculator_personalization' => 'heart_logo',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.leads.index', $lead))
            ->assertOk()
            ->assertSee('Grafiche mockup')
            ->assertSee('Premium')
            ->assertSee('Black')
            ->assertSee('Anteprima live')
            ->assertSee('lg:row-start-2', false)
            ->assertSee(route('admin.leads.mockup-base.show', [$lead, 'front']), false)
            ->assertSee('top: 22%; left: 62%; width: 16%; height: 16%;', false)
            ->assertSee('Logo lato cuore')
            ->assertSee(route('admin.leads.mockup-files.show', [$lead, 'front']), false)
            ->assertSee(route('admin.leads.mockup-files.download', [$lead, 'front']), false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.leads.mockup-files.show', [$lead, 'front']))
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.leads.mockup-files.download', [$lead, 'front']))
            ->assertOk()
            ->assertDownload('grafica-fronte.svg');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.leads.mockup-base.show', [$lead, 'front']))
            ->assertOk()
            ->assertHeader('content-type', 'image/webp');
    }

    public function test_lead_always_shows_the_mockup_diagnostic_section(): void
    {
        Storage::fake('live_mockups');
        $admin = $this->owner();
        $lead = $this->lead();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.leads.index', $lead))
            ->assertOk()
            ->assertSee('Grafiche mockup')
            ->assertSee('Nessuna grafica')
            ->assertSee('Grafica non caricata.');
    }

    private function owner(): AdminUser
    {
        return AdminUser::create([
            'name' => 'Owner CRM',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    private function operator(array $permissions = []): AdminUser
    {
        return AdminUser::create([
            'name' => 'Operatore CRM',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => 'operator',
            'permissions' => $permissions,
            'is_active' => true,
        ]);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::create([
            'uuid' => fake()->unique()->bothify('CRM####'),
            'status' => 'pre',
            'name' => 'Lead CRM',
            'email' => fake()->unique()->safeEmail(),
            'privacy_consent' => true,
            ...$attributes,
        ]);
    }
}
