<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\RepairJob;
use App\Models\RepairPhoto;
use App\Services\SharedRepairPhoto;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use stdClass;

class RepairTimelineShareController extends Controller
{
    private const HEADERS = [
        'Cache-Control' => 'private, no-store',
        'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        'Referrer-Policy' => 'no-referrer',
        'X-Content-Type-Options' => 'nosniff',
    ];

    public function index(Request $request, RepairJob $repairJob): View
    {
        abort_unless($repairJob->customer_id === $request->user()->id, 404);

        return view('customer.repairs.share', [
            'repairJob' => $repairJob->load('photos'),
            'shares' => $this->shares($repairJob)->whereNull('revoked_at')->where('expires_at', '>', now())->latest('created_at')->get(),
        ]);
    }

    public function store(Request $request, RepairJob $repairJob): RedirectResponse
    {
        abort_unless($repairJob->customer_id === $request->user()->id, 404);
        $data = $request->validate([
            'days' => ['required', 'integer', 'between:1,30'],
            'photo_ids' => ['sometimes', 'array', 'max:18'],
            'photo_ids.*' => ['integer', 'distinct'],
        ]);
        $photos = array_map('intval', $data['photo_ids'] ?? []);
        if ($repairJob->photos()->whereKey($photos)->count() !== count($photos)) {
            throw ValidationException::withMessages(['photo_ids' => __('extensions.share.invalid_photo')]);
        }

        $token = bin2hex(random_bytes(32));
        DB::transaction(function () use ($request, $repairJob, $data, $photos, $token): void {
            RepairJob::query()->lockForUpdate()->findOrFail($repairJob->id);
            if ($this->shares($repairJob)->where('expires_at', '>', now())->whereNull('revoked_at')->count() >= 10) {
                throw ValidationException::withMessages(['days' => __('extensions.share.limit')]);
            }
            $id = $this->shares($repairJob)->insertGetId(['repair_job_id' => $repairJob->id, 'token_hash' => hash('sha256', $token),
                'photo_ids' => json_encode($photos, JSON_THROW_ON_ERROR), 'created_at' => now(), 'expires_at' => now()->addDays((int) $data['days'])]);
            $this->log($request, $id, 'created_repair_share');
        });

        return back()->with('repair_share_url', route('repairs.shared.show', $token));
    }

    public function revoke(Request $request, RepairJob $repairJob, int $share): RedirectResponse
    {
        abort_unless($repairJob->customer_id === $request->user()->id, 404);
        DB::transaction(function () use ($request, $repairJob, $share): void {
            $link = $this->shares($repairJob)->where('id', $share);
            abort_unless((clone $link)->exists(), 404);
            if ($link->whereNull('revoked_at')->update(['revoked_at' => now()])) {
                $this->log($request, $share, 'revoked_repair_share');
            }
        });

        return back()->with('status', __('extensions.share.revoked'));
    }

    public function show(string $token): Response
    {
        $share = $this->activeShare($token);
        $job = $share ? RepairJob::query()->find($share->repair_job_id) : null;
        if (! $job) {
            return $this->unavailable();
        }

        $events = $job->statusHistories()->get(['to_status', 'changed_at']);
        $photos = $job->photos()->whereKey($this->photoIds($share))->get(['id', 'stage']);

        return response()->view('repairs.shared-timeline', compact('job', 'events', 'photos', 'token'), 200, self::HEADERS);
    }

    public function photo(string $token, int $photo, SharedRepairPhoto $images): Response
    {
        $share = $this->activeShare($token);
        if (! $share || ! in_array($photo, $this->photoIds($share), true)) {
            return $this->unavailable();
        }
        $photo = RepairPhoto::query()->where('repair_job_id', $share->repair_job_id)->find($photo);
        $image = $photo ? $images->read($photo) : null;

        return $image ? response($image['data'], 200, self::HEADERS + ['Content-Type' => $image['mime']]) : $this->unavailable();
    }

    private function activeShare(string $token): ?stdClass
    {
        return DB::table('repair_timeline_shares')->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')->where('expires_at', '>', now())
            ->whereIn('repair_job_id', RepairJob::query()->whereHas('customer', fn ($query) => $query->whereNull('users.deleted_at'))->select('id'))
            ->first();
    }

    /** @return list<int> */
    private function photoIds(stdClass $share): array
    {
        return json_decode($share->photo_ids, true, flags: JSON_THROW_ON_ERROR);
    }

    private function shares(RepairJob $repairJob): Builder
    {
        return DB::table('repair_timeline_shares')->where('repair_job_id', $repairJob->id);
    }

    private function log(Request $request, int $share, string $action): void
    {
        ActivityLog::query()->create(['user_id' => $request->user()->id, 'module' => 'repairs', 'action' => $action,
            'description' => __('extensions.share.audit', ['id' => $share])]);
    }

    private function unavailable(): Response
    {
        return response(__('extensions.share.unavailable'), 404, self::HEADERS + ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
