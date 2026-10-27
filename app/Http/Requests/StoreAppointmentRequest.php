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
            'service_id' => ['required', 'integer', Rule::exists('shop_services', 'service_id')->where('shop_id', $this->integer('shop_id'))->where('availability', true)],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:2000'],
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
            $offer = ShopService::query()->where('shop_id', $this->integer('shop_id'))
                ->where('service_id', $this->integer('service_id'))->with('service:id,estimated_duration')->first();
            $preferredTime = $this->input('appointment_time');
            if (! $shop || ! $offer?->service) {
                return;
            }
            if ($this->input('appointment_date').' '.$preferredTime <= now()->format('Y-m-d H:i')) {
                $validator->errors()->add('appointment_time', 'Choose a future appointment time.');

                return;
            }

            $schedule = app(AppointmentSchedule::class);
            $duration = (int) ($offer->service->estimated_duration ?: 60);
            if (! $schedule->fitsHours($shop, $preferredTime, $duration)) {
                $validator->errors()->add('appointment_time', 'Choose a time that leaves enough time for this service before the shop closes.');

                return;
            }
            if (! $schedule->hasCapacity($schedule->bookedIntervals($shop, $this->input('appointment_date')), $preferredTime, $duration, (int) $shop->booking_capacity)) {
                $validator->errors()->add('appointment_time', 'The shop is fully booked during this service. Choose another time.');
            }
        }];
    }
}
