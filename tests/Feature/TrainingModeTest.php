<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\EmailAccount;
use App\Models\EmailConversation;
use App\Models\Lead;
use App\Models\WhatsappConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TrainingModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_training_mode_isolates_real_and_training_leads(): void
    {
        $operator = $this->operator();
        $realLead = $this->lead(['uuid' => 'REAL01']);
        $trainingLead = $this->lead([
            'uuid' => 'TRAIN1',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);
        $operator->forceFill(['training_mode_active' => false])->save();

        $this->actingAs($operator, 'admin');

        $this->assertSame([$realLead->id], Lead::pluck('id')->all());

        $operator->forceFill(['training_mode_active' => true])->save();
        $this->assertSame([$trainingLead->id], Lead::pluck('id')->all());
    }

    public function test_operator_can_create_complete_training_scenario(): void
    {
        $operator = $this->operator();
        $this->emailAccount($operator);

        $this->actingAs($operator, 'admin')
            ->post('/training/scenarios', ['scenario' => 'complete'])
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);
        $this->assertDatabaseHas('whatsapp_conversations', [
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);
        $this->assertDatabaseHas('email_conversations', [
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);
    }

    public function test_training_whatsapp_reply_never_calls_meta_api(): void
    {
        Http::fake();
        $operator = $this->operator();
        $lead = $this->lead([
            'uuid' => 'TRAIN2',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);
        $conversation = WhatsappConversation::withoutGlobalScope('training')->create([
            'lead_id' => $lead->id,
            'contact_phone' => $lead->phone,
            'mode' => 'manual',
            'status' => 'open',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);

        $this->actingAs($operator, 'admin')
            ->post("/conversations/{$conversation->id}/messages", ['message' => 'Risposta formativa'])
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_conversation_id' => $conversation->id,
            'body' => 'Risposta formativa',
            'status' => 'sent',
        ]);
    }

    public function test_real_document_areas_are_blocked_during_training(): void
    {
        $operator = $this->operator();
        $operator->forceFill(['permissions' => ['documents.view']])->save();

        $this->actingAs($operator, 'admin')
            ->get('/documents')
            ->assertRedirect('/training')
            ->assertSessionHas('error');
    }

    public function test_training_payment_completion_does_not_send_external_events(): void
    {
        Http::fake();
        $operator = $this->operator();
        $lead = $this->lead([
            'uuid' => 'TRAIN3',
            'is_training' => true,
            'training_owner_id' => $operator->id,
            'payment_amount' => 350,
        ]);

        $this->actingAs($operator, 'admin')
            ->post("/training/leads/{$lead->id}/complete-payment")
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertSame('order_completed', $lead->fresh()->status);
    }

    public function test_training_email_reply_never_uses_smtp(): void
    {
        Http::fake();
        $operator = $this->operator();
        $account = $this->emailAccount($operator);
        $lead = $this->lead([
            'uuid' => 'TRAIN4',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);
        $conversation = EmailConversation::withoutGlobalScope('training')->create([
            'email_account_id' => $account->id,
            'lead_id' => $lead->id,
            'assigned_user_id' => $operator->id,
            'subject' => 'Email formativa',
            'contact_email' => $lead->email,
            'status' => 'open',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);

        $this->actingAs($operator, 'admin')
            ->post("/email/conversations/{$conversation->id}/messages", ['body' => 'Risposta email formativa'])
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertDatabaseHas('email_messages', [
            'email_conversation_id' => $conversation->id,
            'body_text' => 'Risposta email formativa',
            'status' => 'sent',
        ]);
    }

    public function test_training_stripe_link_is_simulated(): void
    {
        Http::fake();
        $operator = $this->operator();
        $lead = $this->lead([
            'uuid' => 'TRAIN5',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);

        $this->actingAs($operator, 'admin')
            ->post("/leads/{$lead->id}/stripe-payment-link", ['payment_amount' => 420])
            ->assertRedirect();

        Http::assertNothingSent();
        $lead->refresh();
        $this->assertSame('link_sent', $lead->status);
        $this->assertStringStartsWith('https://checkout.stripe.test/', $lead->payment_link);
    }

    private function operator(): AdminUser
    {
        return AdminUser::create([
            'name' => 'Operatore Formazione',
            'email' => 'operatore@example.test',
            'password' => 'password-password',
            'role' => 'operator',
            'is_active' => true,
            'training_mode_enabled' => true,
            'training_mode_active' => true,
        ]);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::withoutGlobalScope('training')->create([
            'uuid' => 'LEAD01',
            'status' => 'confirmed',
            'name' => 'Cliente Test',
            'email' => 'cliente@example.test',
            'phone' => '393330000001',
            ...$attributes,
        ]);
    }

    private function emailAccount(AdminUser $operator): EmailAccount
    {
        return EmailAccount::create([
            'admin_user_id' => $operator->id,
            'email' => 'operatore@stuart-company.test',
            'from_name' => $operator->name,
            'username' => 'operatore@stuart-company.test',
            'imap_host' => 'stuart-company.test',
            'smtp_host' => 'stuart-company.test',
            'is_active' => true,
        ]);
    }
}
