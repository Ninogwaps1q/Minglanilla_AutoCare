<?php

use App\Models\User;
use App\Services\AI\AiProviderInterface;
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
