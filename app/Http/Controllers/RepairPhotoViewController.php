<?php

namespace App\Http\Controllers;

use App\Models\RepairPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RepairPhotoViewController extends Controller
{
    public function __invoke(Request $request, RepairPhoto $repairPhoto): BinaryFileResponse
    {
        $job = $repairPhoto->repairJob;
        $user = $request->user();
        abort_unless($job->customer_id === $user->id || $job->shop->owner_id === $user->id || $user->isAdmin(), 404);
        abort_unless(Storage::disk('local')->exists($repairPhoto->path), 404);

        return response()->file(Storage::disk('local')->path($repairPhoto->path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
