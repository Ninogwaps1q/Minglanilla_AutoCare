<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(): View
    {
        return view('customer.favorites.index', ['favorites' => auth()->user()->favorites()->with('shop.barangay')->latest()->paginate(20)]);
    }

    public function store(Shop $shop): RedirectResponse
    {
        abort_unless($shop->verification_status === 'approved' && $shop->shop_status === 'active', 404);
        Favorite::query()->firstOrCreate(['customer_id' => auth()->id(), 'shop_id' => $shop->id]);

        return back()->with('status', 'Shop saved to favorites.');
    }

    public function destroy(Shop $shop): RedirectResponse
    {
        auth()->user()->favorites()->where('shop_id', $shop->id)->delete();

        return back()->with('status', 'Shop removed from favorites.');
    }
}
