<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Shop;
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
            $preferredTime = $this->input('appointment_time');
            if ($shop?->opening_time && $preferredTime < substr($shop->opening_time, 0, 5)) {
                $validator->errors()->add('appointment_time', 'Choose a time after this shop opens.');

                return;
            }
            if ($shop?->closing_time && $preferredTime >= substr($shop->closing_time, 0, 5)) {
                $validator->errors()->add('appointment_time', 'Choose a time before this shop closes.');

                return;
            }

            $isTaken = Appointment::query()
                ->where('shop_id', $this->integer('shop_id'))
                ->whereDate('appointment_date', $this->input('appointment_date'))
                ->where('appointment_time', $this->input('appointment_time'))
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->exists();

            if ($isTaken) {
                $validator->errors()->add('appointment_time', 'This shop already has an active appointment at the selected time. Please choose another time.');
            }
        }];
    }
}
