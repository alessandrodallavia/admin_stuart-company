<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Services\EmailLeadWelcomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoodieEmailWelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_command_includes_hoodies_and_homepage_only(): void
    {
        foreach ([
            'HOOD01' => 'Richiesta contatto via email dalla landing felpe.',
            'HOME01' => 'Richiesta contatto via email dalla homepage.',
            'OTHER1' => 'Altra richiesta',
        ] as $uuid => $message) {
            Lead::create(['uuid' => $uuid, 'status' => 'confirmed', 'email' => 'test@example.test', 'message' => $message, 'is_training' => false]);
        }

        $this->mock(EmailLeadWelcomeService::class, function ($mock) {
            $mock->shouldReceive('send')->once()->withArgs(fn (Lead $lead) => $lead->uuid === 'HOOD01')->andReturn(true);
            $mock->shouldReceive('send')->once()->withArgs(fn (Lead $lead) => $lead->uuid === 'HOME01')->andReturn(true);
        });

        $this->artisan('email:send-lead-welcomes')->expectsOutput('Prime email inviate: 2.')->assertSuccessful();
    }
}
