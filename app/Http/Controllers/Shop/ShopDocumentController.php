<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShopDocumentRequest;
use App\Models\ShopDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopDocumentController extends Controller
{
    public function store(StoreShopDocumentRequest $request): RedirectResponse
    {
        $shop = $request->user()->shops()->firstOrFail();
        $path = $request->file('document')->store("shop-documents/{$shop->id}");

        $shop->documents()->create([
            'document_type' => $request->validated('document_type'),
            'document_path' => $path,
            'expires_at' => $request->validated('expires_at'),
            'verification_status' => 'pending',
        ]);

        return back()->with('status', 'Verification document uploaded securely.');
    }

    public function download(Request $request, ShopDocument $shopDocument): StreamedResponse
    {
        abort_unless($shopDocument->shop->owner_id === $request->user()->id, 404);

        return Storage::download($shopDocument->document_path);
    }

    public function destroy(Request $request, ShopDocument $shopDocument): RedirectResponse
    {
        abort_unless($shopDocument->shop->owner_id === $request->user()->id, 404);
        abort_if($shopDocument->verification_status === 'verified', 409, 'Verified documents cannot be removed.');
        Storage::delete($shopDocument->document_path);
        $shopDocument->delete();

        return back()->with('status', 'Document removed.');
    }
}
