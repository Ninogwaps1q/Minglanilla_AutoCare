<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyShopDocumentRequest;
use App\Models\ActivityLog;
use App\Models\ShopDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopDocumentVerificationController extends Controller
{
    public function update(VerifyShopDocumentRequest $request, ShopDocument $shopDocument): RedirectResponse
    {
        $shopDocument->update([
            ...$request->validated(),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'verified_shop_document',
            'module' => 'shop_verification',
            'description' => "Document #{$shopDocument->id} marked {$shopDocument->verification_status}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        $shopDocument->shop->owner->notifications()->create([
            'type' => 'shop_document',
            'title' => 'Verification document reviewed',
            'message' => ucfirst(str_replace('_', ' ', $shopDocument->document_type))." was {$shopDocument->verification_status}.",
            'data' => ['shop_document_id' => $shopDocument->id],
        ]);

        return back()->with('status', 'Document review saved.');
    }

    public function download(ShopDocument $shopDocument): StreamedResponse
    {
        return Storage::download($shopDocument->document_path);
    }
}
