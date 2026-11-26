<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\EmergencyRequest;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ArchivedRecordsController extends Controller
{
    private const TYPES = ['users' => User::class, 'shops' => Shop::class, 'vehicles' => Vehicle::class, 'appointments' => Appointment::class];

    public function index(Request $request): View
    {
        $type = $request->query('type', 'users');
        abort_unless(is_string($type) && isset(self::TYPES[$type]), 404);
        $active = $request->boolean('active');
        $query = self::TYPES[$type]::query();
        $active ? $query->orderByDesc('id') : $query->onlyTrashed()->orderByDesc('deleted_at');

        return view('admin.archived-records', [
            'records' => $query->paginate(20)->withQueryString(), 'type' => $type, 'types' => array_keys(self::TYPES), 'active' => $active,
        ]);
    }

    public function update(Request $request, string $type, int $record): RedirectResponse
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500'], 'action' => ['required', 'in:archive,restore']]);
        DB::transaction(function () use ($request, $type, $record, $data): void {
            $model = self::TYPES[$type]::withTrashed()->lockForUpdate()->findOrFail($record);
            $restore = $data['action'] === 'restore';
            abort_if($model instanceof User && ($model->isAdmin() || $model->anonymized_at), 403);
            abort_if($restore !== $model->trashed(), 409);

            if ($restore) {
                $parentUnavailable = match (true) {
                    $model instanceof Shop => ! $model->owner()->exists(),
                    $model instanceof Vehicle => ! $model->customer()->withoutTrashed()->exists(),
                    $model instanceof Appointment => ! $model->customer()->withoutTrashed()->exists()
                        || ! $model->shop()->withoutTrashed()->exists() || ! $model->vehicle()->withoutTrashed()->exists(),
                    default => false,
                };
                if ($parentUnavailable) {
                    throw ValidationException::withMessages(['reason' => __('extensions.archive.parent')]);
                }
                $model->restore();
            } else {
                $activeWork = match (true) {
                    $model instanceof Appointment => ! in_array($model->status, ['completed', 'cancelled', 'no_show'], true),
                    $model instanceof User => $model->shops()->exists()
                        || $model->emergencyRequests()->whereIn('status', ['pending', 'accepted', 'en_route'])->exists()
                        || $model->appointments()->whereIn('status', ['pending', 'confirmed', 'in_progress'])->exists()
                        || RepairJob::query()->where('customer_id', $model->id)->whereNotIn('status', ['released', 'cancelled'])->exists(),
                    $model instanceof Shop => $model->appointments()->whereIn('status', ['pending', 'confirmed', 'in_progress'])->exists()
                        || EmergencyRequest::query()->where('assigned_shop_id', $model->id)->whereIn('status', ['pending', 'accepted', 'en_route'])->exists()
                        || $model->repairJobs()->whereNotIn('status', ['released', 'cancelled'])->exists(),
                    default => Appointment::query()->where('vehicle_id', $model->id)->whereIn('status', ['pending', 'confirmed', 'in_progress'])->exists()
                        || RepairJob::query()->where('vehicle_id', $model->id)->whereNotIn('status', ['released', 'cancelled'])->exists(),
                };
                if ($activeWork) {
                    throw ValidationException::withMessages(['reason' => __('extensions.archive.active_work')]);
                }
                $model->delete();
            }

            ActivityLog::query()->create(['user_id' => $request->user()->id, 'module' => 'records', 'action' => $restore ? 'restored_record' : 'archived_record',
                'description' => __('extensions.archive.audit', ['type' => $type, 'id' => $record, 'reason' => $data['reason']])]);
        });

        return back()->with('status', __('extensions.archive.saved'));
    }
}
