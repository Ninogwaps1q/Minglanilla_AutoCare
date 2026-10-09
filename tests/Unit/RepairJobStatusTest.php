<?php

use App\Models\RepairJob;

it('exposes only terminal transitions for completed repairs', function () {
    $repairJob = new RepairJob(['status' => 'completed']);

    expect($repairJob->availableStatuses())->toBe(['released' => 'Release vehicle']);
});

it('does not expose any transition for released or cancelled repairs', function (string $status) {
    $repairJob = new RepairJob(['status' => $status]);

    expect($repairJob->availableStatuses())->toBe([]);
})->with(['released', 'cancelled']);
