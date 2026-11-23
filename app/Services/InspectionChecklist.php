<?php

namespace App\Services;

use App\Models\RepairJob;

class InspectionChecklist
{
    /** @return array<int, array{category: string, item_name: string, condition: string, recommendation: string}> */
    public function forRepairJob(RepairJob $repairJob): array
    {
        $services = $repairJob->appointment?->bookedServices()->with('service.category')->get()
            ->pluck('service')->filter() ?? collect();
        if ($services->isEmpty() && $repairJob->appointment?->service) {
            $services = collect([$repairJob->appointment->service->loadMissing('category')]);
        }

        $items = [['category' => 'Safety', 'item_name' => 'Visible leaks or damage']];
        foreach ($services as $service) {
            $name = mb_strtolower($service->name.' '.$service->category?->name);
            $checks = match (true) {
                str_contains($name, 'brake') => ['Brake pads and discs', 'Brake fluid and lines', 'Brake response'],
                str_contains($name, 'tire'), str_contains($name, 'wheel') => ['Tread and sidewalls', 'Tire pressure', 'Wheel fasteners'],
                str_contains($name, 'electrical'), str_contains($name, 'battery'), str_contains($name, 'alternator') => ['Battery terminals', 'Wiring and connectors', 'Charging output'],
                str_contains($name, 'air conditioning'), str_contains($name, 'cooling') => ['Hoses and leaks', 'Cooling performance', 'Compressor or radiator'],
                str_contains($name, 'engine'), str_contains($name, 'oil'), str_contains($name, 'maintenance') => ['Fluid level and condition', 'Filters and hoses', 'Engine warning indicators'],
                default => ['Requested service component', 'Related safety parts'],
            };
            foreach ($checks as $check) {
                $items[] = ['category' => $service->name, 'item_name' => $check];
            }
        }

        return collect($items)->unique(fn (array $item): string => $item['category'].'|'.$item['item_name'])
            ->map(fn (array $item): array => [...$item, 'condition' => 'Good', 'recommendation' => ''])->values()->all();
    }
}
