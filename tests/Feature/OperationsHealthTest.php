<?php

use App\Jobs\QueueHeartbeat;
use App\Services\OperationsAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('checks database connectivity and reports an unavailable queue without details', function () {
    config()->set('queue.default', 'sync');
    $this->getJson('/up')->assertOk()->assertExactJson(['status' => 'ok']);

    Queue::shouldReceive('size')->once()->andThrow(new RuntimeException('private debug text'));
    $this->getJson('/up')->assertStatus(503)->assertExactJson(['status' => 'unavailable'])->assertDontSee('private debug text');
});

it('requires a recent worker heartbeat when enabled', function () {
    config()->set(['queue.default' => 'database', 'monitoring.require_queue_heartbeat' => true]);
    $this->getJson('/up')->assertStatus(503);
    (new QueueHeartbeat)->handle();
    $this->getJson('/up')->assertOk();
    $this->travel(6)->minutes();
    $this->getJson('/up')->assertStatus(503);
});

it('limits repeated failure emails for each scheduled task', function () {
    config()->set(['monitoring.alert_email' => 'operations@example.test', 'mail.default' => 'array']);
    $alerts = app(OperationsAlert::class);
    $alerts->scheduleFailure('app:reconcile-paymongo-payments');
    $alerts->scheduleFailure('app:reconcile-paymongo-payments');
    $alerts->scheduleFailure('app:send-maintenance-reminders');

    $outbox = Mail::mailer()->getSymfonyTransport()->messages();
    expect($outbox)->toHaveCount(2);
    expect($outbox->first()->getOriginalMessage()->getTextBody())->toContain('app:reconcile-paymongo-payments');
});
