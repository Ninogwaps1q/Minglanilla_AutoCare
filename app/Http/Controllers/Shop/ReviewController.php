<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewResponseRequest;
use App\Models\Review;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        return view('shop.reviews.index', [
            'reviews' => $this->shop($request)->reviews()
                ->when($request->integer('focus') > 0, fn ($query) => $query->whereKey($request->integer('focus')))
                ->with(['customer', 'repairJob'])->latest()->get(),
        ]);
    }

    public function update(StoreReviewResponseRequest $request, Review $review): RedirectResponse
    {
        abort_unless($review->shop_id === $this->shop($request)->id, 404);
        $review->update($request->validated());
        $review->customer->notifications()->create([
            'type' => 'review_response',
            'title' => 'Shop replied to your review',
            'message' => "{$review->shop->name} replied to your review.",
            'data' => ['review_id' => $review->id],
        ]);

        return back()->with('status', 'Review response published.');
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
