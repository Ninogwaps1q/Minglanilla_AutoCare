<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ShopPlanPayment;
use App\Services\PayMongoRefunds;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubscriptionBillingController extends Controller
{
    public function refund(Request $request, ShopPlanPayment $shopPlanPayment, PayMongoRefunds $refunds): RedirectResponse
    {
        abort_unless($shopPlanPayment->shop->owner_id === $request->user()->id, 404);
        $attributes = $request->validate(['reason' => ['required', 'string', 'max:500'], 'amount' => ['prohibited']]);
        $refunds->request($shopPlanPayment, $request->user()->id, $attributes['reason']);

        return back()->with('status', 'Full refund requested. Your coverage stays active until PayMongo confirms the refund.');
    }

    public function receipt(Request $request, ShopPlanPayment $shopPlanPayment): Response
    {
        abort_unless($request->user()->role === 'admin' || $shopPlanPayment->shop->owner_id === $request->user()->id, 404);
        abort_unless($shopPlanPayment->status === 'approved', 404);

        return Pdf::loadView('components.subscription-receipt', ['payment' => $shopPlanPayment->load('shop.owner')])
            ->setPaper('a4')->download('subscription-'.$shopPlanPayment->id.'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }
}
