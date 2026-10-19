<?php

namespace App\Models;

use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['operating_days' => 'array'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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

        return collect(['business_permit' => 'Business permit', 'mayors_permit' => 'Mayor’s permit'])
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
