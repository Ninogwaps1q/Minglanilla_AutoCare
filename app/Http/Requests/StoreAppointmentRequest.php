<?php

namespace App\Http\Requests;

use App\Models\Shop;
use App\Models\ShopService;
use App\Services\AppointmentSchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'customer';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', Rule::exists('shops', 'id')->where('verification_status', 'approved')->where('shop_status', 'active')],
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('customer_id', $this->user()?->id)],
            'service_id' => ['nullable', 'integer', Rule::exists('shop_services', 'service_id')->where('shop_id', $this->integer('shop_id'))->where('availability', true)],
            'service_ids' => ['required_without:service_id', 'array', 'min:1', 'max:10'],
            'service_ids.*' => ['required', 'integer', 'distinct', Rule::exists('shop_services', 'service_id')->where('shop_id', $this->integer('shop_id'))->where('availability', true)],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'service_barangay_id' => ['nullable', 'integer', Rule::exists('barangays', 'id')->where('status', 'active')],
            'service_street' => ['nullable', 'string', 'max:255'],
            'service_landmark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $shop = Shop::query()->find($this->integer('shop_id'));
            $serviceIds = $this->input('service_ids') ?: [$this->integer('service_id')];
            $offers = ShopService::query()->where('shop_id', $this->integer('shop_id'))
                ->whereIn('service_id', $serviceIds)->where('availability', true)
                ->with('service:id,estimated_duration')->get();
            $preferredTime = $this->input('appointment_time');
            if (! $shop || $offers->count() !== count($serviceIds) || $offers->contains(fn (ShopService $offer): bool => ! $offer->service)) {
                return;
            }
            if ($shop->isIndependentMechanic() && ! $this->filled('service_barangay_id')) {
                $validator->errors()->add('service_barangay_id', 'Choose the barangay where the mechanic should meet you.');
            }
            if ($shop->isIndependentMechanic() && blank($this->input('service_street'))) {
                $validator->errors()->add('service_street', 'Enter the street or house number where the mechanic should meet you.');
            }
            if ($this->input('appointment_date').' '.$preferredTime <= now()->format('Y-m-d H:i')) {
                $validator->errors()->add('appointment_time', 'Choose a future appointment time.');

                return;
            }

            $schedule = app(AppointmentSchedule::class);
            $duration = $offers->sum(fn (ShopService $offer): int => (int) ($offer->service->estimated_duration ?: 60));
            if (! $schedule->isAvailable($shop, $this->input('appointment_date'), $preferredTime, $duration)) {
                $validator->errors()->add('appointment_time', 'The shop is closed or this time is blocked. Choose another slot.');

                return;
            }
            if (! $schedule->hasCapacity($schedule->bookedIntervals($shop, $this->input('appointment_date')), $preferredTime, $duration, (int) $shop->booking_capacity)) {
                $validator->errors()->add('appointment_time', 'The shop is fully booked during this service. Choose another time.');
            }
        }];
    }
}
