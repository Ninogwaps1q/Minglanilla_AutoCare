<?php

namespace App\Services;

use App\Models\Quotation;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(private BookingPromotion $promotions) {}

    /** @param array<int, array{description: string, quantity: int, unit_price: numeric-string|float|int}> $items */
    public function send(Quotation $quotation, array $items, ?string $validUntil, ?string $notes): void
    {
        $totalCents = 0;
        $pricedItems = [];
        foreach ($items as $item) {
            $subtotalCents = (int) round((float) $item['unit_price'] * 100) * (int) $item['quantity'];
            $totalCents += $subtotalCents;
            $pricedItems[] = [...$item, 'subtotal' => $subtotalCents / 100];
        }
        if ($totalCents > 999999999999) {
            throw ValidationException::withMessages(['items' => 'The quotation total exceeds the supported amount.']);
        }
        $discount = $this->promotions->discountCents($quotation->appointment, $totalCents);
        $quotation->update(['subtotal' => $totalCents / 100, 'discount' => $discount / 100, 'total' => ($totalCents - $discount) / 100, 'status' => 'sent', 'valid_until' => $validUntil, 'notes' => $notes]);
        $quotation->items()->createMany($pricedItems);
    }
}
