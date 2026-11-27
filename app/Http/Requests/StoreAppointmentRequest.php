<?php

namespace App\Http\Requests;

use App\Models\Appointment;
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
            'shop_id' => ['required', 'integer', Rule::exists('shops', 'id')->whereNull('deleted_at')->where('verification_status', 'approved')->where('shop_status', 'active')],
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')->where('customer_id', $this->user()?->id)],
            'service_id' => ['nullable', 'integer', Rule::exists('shop_services', 'service_id')->where('shop_id', $this->integer('shop_id'))->where('availability', true)],
            'service_ids' => ['required_without:service_id', 'array', 'min:1', 'max:10'],
            'service_ids.*' => ['required', 'integer', 'distinct', Rule::exists('shop_services', 'service_id')->where('shop_id', $this->integer('shop_id'))->where('availability', true)],
            'appointment_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today(Appointment::TIMEZONE)->toDateString()],
            'appointment_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'promo_code' => ['nullable', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_-]+$/D'],
            'service_barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
            'service_street' => ['nullable', 'string', 'max:255'],
            'service_landmark' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:4,22'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:112,128'],
            'mobile_quote_total' => ['nullable', 'integer', 'min:0'],
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
            $schedule = app(AppointmentSchedule::class);
            if (! $schedule->isUpcoming($this->input('appointment_date'), $preferredTime)) {
                $validator->errors()->add('appointment_time', 'Choose a future appointment time.');

                return;
            }

            $duration = $schedule->duration($shop, $offers->sum(fn (ShopService $offer): int => (int) ($offer->service->estimated_duration ?: 60)));
            if (! $schedule->isAvailable($shop, $this->input('appointment_date'), $preferredTime, $duration)) {
                $validator->errors()->add('appointment_time', 'The shop is closed or this time is blocked. Choose another slot.');

                return;
            }
            if (! $schedule->hasCapacity($schedule->bookedIntervals($shop, $this->input('appointment_date')), $preferredTime, $duration, $shop->appointmentCapacity())) {
                $validator->errors()->add('appointment_time', 'The shop is fully booked during this service. Choose another time.');
            }
        }];
    }
}
