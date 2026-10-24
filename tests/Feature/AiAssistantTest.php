<?php

use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AI\AiProviderInterface;
use App\Services\AI\AiServiceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('does not create AI storage tables after migrations run', function () {
    expect(Schema::hasTable('ai_conversations'))->toBeFalse();
    expect(Schema::hasTable('ai_messages'))->toBeFalse();
    expect(Schema::hasTable('ai_usage_logs'))->toBeFalse();
});

it('requires a customer session to access the AI assistant', function () {
    $this->get(route('customer.ai-assistant.index'))->assertRedirect(route('login'));
});

it('keeps the assistant available when the provider is not configured', function () {
    config(['ai.api_key' => null]);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->post(route('customer.ai-assistant.store'), ['message' => 'How do I book an oil change?'])
        ->assertRedirect()
        ->assertSessionHasErrors('message');

    $this->assertDatabaseCount('users', 1);
});

it('uses Gemini through the server-side provider', function () {
    config(['ai.provider' => 'gemini', 'ai.api_key' => 'test-key', 'ai.model' => 'gemini-2.0-flash']);
    Http::preventStrayRequests();
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Book through Appointments, then choose your vehicle and service.']]]]],
        ]),
    ]);

    $reply = app(AiProviderInterface::class)->respond([
        ['role' => 'system', 'content' => 'Stay within the product scope.'],
        ['role' => 'user', 'content' => 'How do I book?'],
    ]);

    expect($reply)->toContain('Appointments');
    Http::assertSent(fn ($request): bool => $request->hasHeader('x-goog-api-key', 'test-key')
        && str_contains($request->url(), 'gemini-2.0-flash:generateContent'));
});

it('returns Gemini customer-facing text after a thinking part', function () {
    config(['ai.provider' => 'gemini', 'ai.api_key' => 'test-key', 'ai.model' => 'gemini-3.5-flash', 'ai.thinking_level' => 'low']);
    Http::preventStrayRequests();
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [
                    ['text' => 'Internal reasoning', 'thought' => true],
                    ['text' => 'Check tire pressure monthly.'],
                    ['text' => 'Ask a technician if a tire keeps losing air.'],
                ]],
            ]],
        ]),
    ]);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->postJson(route('customer.ai-assistant.store'), [
        'message' => 'How should I maintain tire pressure?',
    ])->assertOk()->assertJsonPath('reply', 'Check tire pressure monthly.'.chr(10).'Ask a technician if a tire keeps losing air.');

    Http::assertSent(fn ($request): bool => data_get($request->data(), 'generationConfig.thinkingConfig.thinkingLevel') === 'low');
});

it('shows formatted assistant replies from temporary session history', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->app->instance(AiProviderInterface::class, new class implements AiProviderInterface
    {
        public function respond(array $messages): string
        {
            return "# Recommended steps\n\n* Check the oil level.\n* Book a technician if the warning remains.\n\n**Do not ignore a red warning light.**";
        }
    });

    $this->actingAs($customer)
        ->post(route('customer.ai-assistant.store'), ['message' => 'What should I do?'])
        ->assertRedirect(route('customer.ai-assistant.index'));

    $this->get(route('customer.ai-assistant.index'))
        ->assertSee('Recommended steps')
        ->assertSee('Check the oil level.')
        ->assertDontSee('# Recommended steps')
        ->assertDontSee('* Check the oil level.');
    $this->assertDatabaseCount('users', 1);
});

it('returns widget replies to the current page and reopens the chat', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->app->instance(AiProviderInterface::class, new class implements AiProviderInterface
    {
        public function respond(array $messages): string
        {
            return 'You can review your booking under My appointments.';
        }
    });

    $this->actingAs($customer)
        ->from(route('dashboard'))
        ->post(route('customer.ai-assistant.store'), ['message' => 'Where is my booking?', 'widget' => true])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('ai_widget_open', true);

    $this->get(route('dashboard'))->assertSee('Here are your appointments, including pending and confirmed service visits.');
});

it('returns a customer chat reply as JSON and keeps it in temporary session history', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->app->instance(AiProviderInterface::class, new class implements AiProviderInterface
    {
        public function respond(array $messages): string
        {
            throw new LogicException('A navigation command must not call the external AI provider.');
        }
    });

    $response = $this->actingAs($customer)->postJson(route('customer.ai-assistant.store'), [
        'message' => 'Please book a service for my car',
        'widget' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('action.label', 'Book a service')
        ->assertJsonPath('action.url', route('customer.appointments.create'))
        ->assertJsonPath('reply', 'I can start your service booking. You will still choose the shop, vehicle, service, date, and time before submitting.');
    expect(session('ai_history_'.$customer->id))->toHaveCount(2);
});

it('returns a readable JSON error without adding a failed question to history', function () {
    config(['ai.api_key' => null]);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->postJson(route('customer.ai-assistant.store'), [
        'message' => 'What does brake fluid do?',
        'widget' => true,
    ])->assertServiceUnavailable()->assertJsonPath('message', 'The AI assistant is not configured yet. Please contact the administrator.');

    expect(session('ai_history_'.$customer->id))->toBeNull();
});

it('validates in-place chat messages before contacting the provider', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->postJson(route('customer.ai-assistant.store'), [
        'message' => '',
        'widget' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('message');
});

it('turns a safe customer command into a verified session action', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->app->instance(AiProviderInterface::class, new class implements AiProviderInterface
    {
        public function respond(array $messages): string
        {
            throw new LogicException('Navigation commands must not call the external AI provider.');
        }
    });

    $this->actingAs($customer)
        ->from(route('dashboard'))
        ->post(route('customer.ai-assistant.store'), ['message' => 'Please book a service for my car', 'widget' => true])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('ai_widget_open', true);

    $this->get(route('dashboard'))
        ->assertSee('Book a service')
        ->assertSee(route('customer.appointments.create'));
});

it('keeps only the latest six turns in session conversation history', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->app->instance(AiProviderInterface::class, new class implements AiProviderInterface
    {
        public function respond(array $messages): string
        {
            return 'A short answer.';
        }
    });
    $history = [];
    for ($turn = 1; $turn <= 7; $turn++) {
        $this->withSession(['ai_history_'.$customer->id => $history]);
        $this->actingAs($customer)->post(route('customer.ai-assistant.store'), ['message' => 'Question '.$turn])->assertRedirect();
        $history = session('ai_history_'.$customer->id);
    }

    expect(count($history))->toBe(12);
    expect($history[0]['message'])->toBe('Question 2');
    expect($history[11]['message'])->toBe('A short answer.');
});

it('retries a temporary Gemini failure and records the recovered status', function () {
    config(['ai.api_key' => 'test-key', 'ai.model' => 'gemini-2.0-flash', 'ai.retry_attempts' => 2]);
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push(['error' => ['status' => 'UNAVAILABLE']], 503)
        ->push(['candidates' => [['content' => ['parts' => [['text' => 'The assistant is ready.']]]]]], 200)]);

    $reply = app(AiProviderInterface::class)->respond([['role' => 'user', 'content' => 'Hello']]);

    expect($reply)->toBe('The assistant is ready.');
    expect(app(AiServiceStatus::class)->snapshot()['state'])->toBe('healthy');
    Http::assertSentCount(2);
});

it('does not retry a Gemini configuration error', function () {
    config(['ai.api_key' => 'test-key', 'ai.retry_attempts' => 3]);
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403)]);

    expect(fn () => app(AiProviderInterface::class)->respond([['role' => 'user', 'content' => 'Hello']]))
        ->toThrow(RuntimeException::class);
    expect(app(AiServiceStatus::class)->snapshot()['state'])->toBe('configuration_error');
    Http::assertSentCount(1);
});

it('never includes another customers repair in a status shortcut', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $otherVehicle = Vehicle::factory()->create(['customer_id' => $otherCustomer->id]);
    $otherRepair = RepairJob::factory()->forVehicle($otherVehicle)->create();

    $this->actingAs($customer)->postJson(route('customer.ai-assistant.store'), [
        'message' => 'Show my latest repair status',
    ])->assertOk()->assertJsonPath('reply', 'You do not have a repair job yet. Open Repair progress to check again after a shop starts one.')
        ->assertDontSee($otherRepair->job_number);
});

it('links a customers own repair status without contacting Gemini', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $repair = RepairJob::factory()->forVehicle($vehicle)->create(['status' => 'diagnosis']);
    Http::preventStrayRequests();

    $this->actingAs($customer)->postJson(route('customer.ai-assistant.store'), [
        'message' => 'Show my latest repair status',
    ])->assertOk()->assertJsonPath('action.url', route('customer.repairs.index', ['repair_job_id' => $repair->id]))
        ->assertSee($repair->job_number);
    Http::assertNothingSent();
});

it('prefills only the customers own vehicle in the booking form', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $ownVehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $otherVehicle = Vehicle::factory()->create();

    $this->actingAs($customer)->get(route('customer.appointments.create', ['vehicle_id' => $ownVehicle->id]))
        ->assertOk()->assertSee('value='.chr(34).$ownVehicle->id.chr(34).' selected', false);
    $this->get(route('customer.appointments.create', ['vehicle_id' => $otherVehicle->id]))
        ->assertOk()->assertDontSee('value='.chr(34).$otherVehicle->id.chr(34), false);
});
