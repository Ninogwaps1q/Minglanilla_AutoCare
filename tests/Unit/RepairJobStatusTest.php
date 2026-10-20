<?php

use App\Models\Quotation;
use App\Models\RepairJob;
use Tests\TestCase;

uses(TestCase::class);

it('exposes only terminal transitions for completed repairs', function () {
    $repairJob = new RepairJob(['status' => 'completed']);

    expect($repairJob->availableStatuses())->toBe(['released' => 'Release vehicle']);
});

it('does not expose any transition for released or cancelled repairs', function (string $status) {
    $repairJob = new RepairJob(['status' => $status]);

    expect($repairJob->availableStatuses())->toBe([]);
})->with(['released', 'cancelled']);

it('requires an approved estimate before the shop can start a diagnosed repair', function () {
    $repairJob = new RepairJob(['status' => 'diagnosis']);

    expect($repairJob->availableStatuses())->toBe(['cancelled' => 'Cancel']);
});

it('requires the shop to start an approved repair before it can be completed', function () {
    $repairJob = new RepairJob(['status' => 'approved']);
    $repairJob->setRelation('quotation', new Quotation(['status' => 'accepted']));

    expect($repairJob->availableStatuses())->toBe(['repairing' => 'Start repair', 'cancelled' => 'Cancel']);

    $repairJob->status = 'repairing';

    expect($repairJob->availableStatuses())->toBe(['waiting_parts' => 'Waiting for parts', 'completed' => 'Complete repair', 'cancelled' => 'Cancel']);
});
