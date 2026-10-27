<?php

namespace App\Services;

use App\Models\RepairJob;
use App\Models\RepairJobItem;
use App\Models\SparePart;
use Illuminate\Validation\ValidationException;

class RepairInventoryService
{
    public function availableQuantity(SparePart $part): int
    {
        return max(0, $part->quantity - (int) $part->reservations()->where('status', 'reserved')->sum('quantity'));
    }

    public function reserveItem(RepairJobItem $item): void
    {
        if ($item->type !== 'part' || $item->spare_part_id === null) {
            throw ValidationException::withMessages(['part' => 'Choose a recorded inventory part for this work item.']);
        }
        if ($item->inventoryTransactions()->where('type', 'repair_consumption')->exists()) {
            return;
        }
        $part = SparePart::query()->where('shop_id', $item->repairJob->shop_id)->lockForUpdate()->findOrFail($item->spare_part_id);
        $reservation = $item->reservation()->first();
        if ($reservation?->status === 'reserved') {
            return;
        }
        if ($this->availableQuantity($part) < $item->quantity) {
            throw ValidationException::withMessages(['part' => "Only {$this->availableQuantity($part)} unreserved units of {$part->name} are available. Restock before reserving this repair."]);
        }
        $item->reservation()->updateOrCreate(
            ['repair_job_item_id' => $item->id],
            ['spare_part_id' => $part->id, 'quantity' => $item->quantity, 'status' => 'reserved'],
        );
    }

    public function reserveAvailableForJob(RepairJob $job): bool
    {
        $allAvailable = true;
        foreach ($job->items()->where('type', 'part')->whereNotNull('spare_part_id')->orderBy('spare_part_id')->orderBy('id')->get() as $item) {
            try {
                $this->reserveItem($item);
            } catch (ValidationException $exception) {
                $allAvailable = false;
            }
        }

        return $allAvailable;
    }

    public function hasMissingReservations(RepairJob $job): bool
    {
        return $job->items()->where('type', 'part')->whereNotNull('spare_part_id')
            ->whereDoesntHave('reservation', fn ($query) => $query->whereIn('status', ['reserved', 'consumed']))
            ->whereDoesntHave('inventoryTransactions', fn ($query) => $query->where('type', 'repair_consumption'))
            ->exists();
    }

    public function releaseForJob(RepairJob $job): void
    {
        foreach ($job->items()->whereHas('reservation', fn ($query) => $query->where('status', 'reserved'))
            ->with('reservation')->orderBy('id')->get() as $item) {
            $item->reservation->update(['status' => 'released']);
        }
    }
}
