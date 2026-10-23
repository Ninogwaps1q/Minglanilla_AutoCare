<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\RepairJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RepairPhotoController extends Controller
{
    public function store(Request $request, RepairJob $repairJob): RedirectResponse
    {
        abort_unless($repairJob->shop_id === $request->user()->shops()->firstOrFail()->id, 404);
        $data = $request->validate([
            'stage' => ['required', Rule::in(['before', 'after'])],
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);
        $workHasStarted = $repairJob->started_at !== null
            || in_array($repairJob->status, ['repairing', 'in_progress', 'waiting_parts', 'completed', 'released'], true);
        if ($repairJob->status === 'cancelled'
            || ($data['stage'] === 'after' && ! $workHasStarted)
            || ($data['stage'] === 'before' && $workHasStarted)) {
            throw ValidationException::withMessages(['stage' => 'Before photos must be taken before work starts; after photos require a started repair. Cancelled repairs cannot receive photos.']);
        }
        if ($repairJob->photos()->where('stage', $data['stage'])->count() >= 6) {
            throw ValidationException::withMessages(['photo' => 'This repair already has six photos for this stage.']);
        }

        $path = $request->file('photo')->store('repair-evidence/'.$repairJob->id, 'local');
        if (! $path) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be saved. Please try again.']);
        }
        try {
            $repairJob->photos()->create([
                'stage' => $data['stage'], 'path' => $path,
                'caption' => $data['caption'] ?? null, 'uploaded_by' => $request->user()->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('status', ucfirst($data['stage']).' repair photo saved.');
    }
}
