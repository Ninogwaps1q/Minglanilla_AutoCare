<?php

use App\Models\User;
use App\Services\AI\AiProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('requires a customer session to access the AI assistant', function () {
    $this->get(route('customer.ai-assistant.index'))->assertRedirect(route('login'));
});

it('keeps the assistant available when the provider is not configured', function () {
    config(['ai.api_key' => null]);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->post(route('customer.ai-assistant.store'), ['message' => 'How do I book an oil change?'])
        ->assertRedirect()
        ->assertSessionHasErrors('message');

    $this->assertDatabaseHas('ai_conversations', ['user_id' => $customer->id]);
    $this->assertDatabaseHas('ai_messages', ['role' => 'user', 'message' => 'How do I book an oil change?']);
    $this->assertDatabaseHas('ai_usage_logs', ['user_id' => $customer->id, 'feature' => 'assistant', 'request_status' => 'failed']);
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

it('stores and displays assistant replies without raw markdown symbols', function () {
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

    $this->assertDatabaseHas('ai_messages', [
        'role' => 'assistant',
        'message' => "Recommended steps\n\n• Check the oil level.\n• Book a technician if the warning remains.\n\nDo not ignore a red warning light.",
    ]);

    $this->actingAs($customer)
        ->get(route('customer.ai-assistant.index'))
        ->assertSee('Recommended steps')
        ->assertSee('• Check the oil level.')
        ->assertDontSee('# Recommended steps')
        ->assertDontSee('* Check the oil level.');
});
