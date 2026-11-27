<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['operating_days' => 'array', 'schedule_blocks' => 'array', 'roadside_available' => 'boolean', 'trial_started_at' => 'datetime', 'trial_revoked_at' => 'datetime', 'plan_expires_at' => 'datetime', 'next_plan_starts_at' => 'datetime', 'mechanic_profile' => 'array', 'mechanic_settings' => 'array', 'mechanic_tools' => 'array', 'mechanic_portfolio' => 'array'];
    }

    public function appointmentCapacity(): int
    {
        return $this->isIndependentMechanic() ? 1 : (int) $this->booking_capacity;
    }

    public function mechanicOnline(): bool
    {
        return (bool) ($this->mechanic_settings['online'] ?? true);
    }

    public function offersHomeService(): bool
    {
        return $this->isIndependentMechanic() && (bool) ($this->mechanic_settings['home_service'] ?? true);
    }

    public function mechanicEntitlements(): array
    {
        $slug = $this->activePlanSlug();

        return ShopPlan::query()->where('slug', $slug)->first()?->mechanicFeatures() ?? ShopPlan::mechanicDefaults($slug);
    }

    public function acceptsNewBookings(): bool
    {
        return $this->isIndependentMechanic() ? $this->mechanicOnline() && $this->offersHomeService() : $this->hasPlanAtLeast('basic');
    }

    public function isIndependentMechanic(): bool
    {
        return $this->provider_type === 'independent_mechanic';
    }

    public function providerLabel(): string
    {
        return $this->isIndependentMechanic() ? 'Independent mechanic' : 'Service shop';
    }

    public function scopeInArea(Builder $query, array $filters): Builder
    {
        $area = array_filter(array_intersect_key($filters, Barangay::AREA_FILTER_RULES));

        return $area === [] ? $query : $query->whereHas('barangay', fn (Builder $barangays): Builder => $barangays->inArea($area));
    }

    public function scopePublicListing(Builder $query): Builder
    {
        return $query->where('verification_status', 'approved')->where('shop_status', 'active')
            ->whereHas('owner', fn (Builder $owner): Builder => $owner->where('status', 'active'));
    }

    public function scopeMatchingPlace(Builder $query, string $search): Builder
    {
        $pattern = '%'.$search.'%';

        return $query->where(fn (Builder $query): Builder => $query
            ->where('name', 'like', $pattern)
            ->orWhereHas('barangay', fn (Builder $barangay): Builder => $barangay->where('name', 'like', $pattern))
            ->orWhereHas('barangay.municipality', fn (Builder $city): Builder => $city->where('name', 'like', $pattern))
            ->orWhereHas('barangay.municipality.province', fn (Builder $province): Builder => $province->where('name', 'like', $pattern)));
    }

    /** @return array{latitude: float, longitude: float}|null */
    public function publicMapPosition(): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        $precision = $this->isIndependentMechanic() ? 2 : 7;

        return [
            'latitude' => round((float) $this->latitude, $precision),
            'longitude' => round((float) $this->longitude, $precision),
        ];
    }

    public function activePlanSlug(): string
    {
        if ($this->trialIsActive()) {
            return 'premium';
        }

        if ($this->next_plan_starts_at?->lte(now())) {
            return $this->currentPaidPeriod()?->plan_slug ?? ($this->plan_expires_at?->isFuture() ? $this->plan_slug : 'free');
        }

        if (in_array($this->plan_slug, ['basic', 'premium'], true) && $this->plan_expires_at?->isFuture()) {
            return $this->plan_slug;
        }

        return 'free';
    }

    public function trialEndsAt(): ?CarbonInterface
    {
        return $this->trial_started_at?->copy()->addDays(30);
    }

    public function trialIsActive(): bool
    {
        return $this->trial_revoked_at === null && ($this->trialEndsAt()?->isFuture() ?? false);
    }

    public function activePlanEndsAt(): ?CarbonInterface
    {
        if ($this->trialIsActive()) {
            return $this->trialEndsAt();
        }

        if ($this->next_plan_starts_at?->lte(now()) && ($current = $this->currentPaidPeriod())) {
            return $current->period_ends_at;
        }

        return $this->activePlanSlug() !== 'free' ? $this->plan_expires_at : null;
    }

    private function currentPaidPeriod(): ?ShopPlanPayment
    {
        return $this->planPayments()->validPeriods()->where('period_starts_at', '<=', now())->where('period_ends_at', '>', now())->latest('period_starts_at')->first();
    }

    public function daysUntilAccessEnds(): ?int
    {
        $endsAt = $this->activePlanEndsAt();

        return $endsAt ? max(0, (int) ceil(now()->diffInSeconds($endsAt, false) / 86400)) : null;
    }

    public function hasPlanAtLeast(string $minimum): bool
    {
        $rank = ['free' => 0, 'basic' => 1, 'premium' => 2];

        return $rank[$this->activePlanSlug()] >= $rank[$minimum];
    }

    public function scopeWithOnlineBookings(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->whereNull('trial_revoked_at')->where('trial_started_at', '>', now()->subDays(30));
            })
                ->orWhere(function (Builder $query): void {
                    $query->whereIn('plan_slug', ['basic', 'premium'])
                        ->where('plan_expires_at', '>', now());
                })
                ->orWhereHas('planPayments', fn (Builder $query): Builder => $query->validPeriods()->where('period_starts_at', '<=', now())->where('period_ends_at', '>', now()));
        });
    }

    public function scopeWithAppointmentBookings(Builder $query): Builder
    {
        return $query->where(fn (Builder $providers): Builder => $providers
            ->where(fn (Builder $shops): Builder => $shops->where('provider_type', 'shop')->withOnlineBookings())
            ->orWhere(fn (Builder $mechanics): Builder => $mechanics->where('provider_type', 'independent_mechanic')
                ->where(fn (Builder $availability): Builder => $availability->whereNull('mechanic_settings->online')->orWhere('mechanic_settings->online', true))
                ->where(fn (Builder $home): Builder => $home->whereNull('mechanic_settings->home_service')->orWhere('mechanic_settings->home_service', true))));
    }

    public function planPayments(): HasMany
    {
        return $this->hasMany(ShopPlanPayment::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class, 'staff_shop_id');
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function repairJobs(): HasMany
    {
        return $this->hasMany(RepairJob::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(ShopService::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ShopDocument::class);
    }

    public function hasCurrentVerifiedPermits(): bool
    {
        return collect($this->permitChecklist())->every(fn (array $permit): bool => $permit['status'] === 'verified');
    }

    /**
     * @return array<int, array{type: string, label: string, status: string, action: string, document: ?ShopDocument}>
     */
    public function permitChecklist(): array
    {
        $documents = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();

        $requiredDocuments = $this->isIndependentMechanic()
            ? ['government_id' => 'Government ID', 'mechanic_credentials' => 'Mechanic qualifications or work history']
            : ['business_permit' => 'Business permit', 'mayors_permit' => 'Mayor’s permit'];

        return collect($requiredDocuments)
            ->map(function (string $label, string $type) use ($documents): array {
                $document = $documents->where('document_type', $type)->sortByDesc('id')->first();
                $status = match (true) {
                    $document === null => 'missing',
                    $document->expires_at?->isBefore(today()) => 'expired',
                    default => $document->verification_status,
                };

                return [
                    'type' => $type,
                    'label' => $label,
                    'status' => $status,
                    'action' => match ($status) {
                        'verified' => 'Current and verified.',
                        'pending' => 'Waiting for admin review.',
                        'rejected' => $document->admin_notes ?: 'Upload a corrected permit for review.',
                        'expired' => 'Upload a renewed permit.',
                        default => 'Upload this permit to continue verification.',
                    },
                    'document' => $document,
                ];
            })->values()->all();
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class);
    }

    public function technicians(): HasMany
    {
        return $this->hasMany(Technician::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function refreshPublishedRating(): void
    {
        $publishedReviews = $this->reviews()->where('status', 'published');
        $this->update([
            'average_rating' => $publishedReviews->avg('rating') ?? 0,
            'total_reviews' => $publishedReviews->count(),
        ]);
    }

    public function messageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class);
    }

    public function emergencyRequests(): HasMany
    {
        return $this->hasMany(EmergencyRequest::class, 'assigned_shop_id');
    }
}
