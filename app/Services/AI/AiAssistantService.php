<?php

namespace App\Services\AI;

use App\Models\User;
use Illuminate\Support\Str;

class AiAssistantService
{
    /**
     * @return array{message: string, action: array{label: string, url: string, icon: string}|null}|null
     */
    public function respond(User $user, string $message): ?array
    {
        $command = rtrim(Str::lower(Str::squish(Str::transliterate($message))), ' .!?');
        if (in_array($command, ['hello', 'hi', 'help', 'what can you do', 'what is minglanilla autocare', 'tabang', 'tulong'], true)) {
            return ['message' => __('assistant.scope'), 'action' => null];
        }

        $command = $this->withoutRequestPrefix($command);
        $guided = $this->guidedAction($user, $command);

        if ($guided !== null) {
            return $guided;
        }

        $action = $this->navigationAction($command);

        if ($action !== null) {
            return [
                'message' => $action['reply'],
                'action' => ['label' => $action['label'], 'url' => route($action['route']), 'icon' => $action['icon']],
            ];
        }

        return null;
    }

    /** @return array{message: string, action: array{label: string, url: string, icon: string}}|null */
    private function guidedAction(User $user, string $command): ?array
    {
        if ($this->matches($command, 'repair status|repair progress|'.$this->statusQuestion('repair', 'status|progress').'|unsa na ang akong repair|ano na ang status ng repair ko')) {
            return $this->repairStatus($user);
        }

        if ($this->matches($command, '(?:appointment|booking) status|'.$this->statusQuestion('appointment|booking', 'status').'|when is my (?:appointment|booking)|asa na (?:ang )?akong booking|ano na ang status ng booking ko')) {
            return $this->appointmentStatus($user);
        }

        if ($this->matches($command, '(?:invoice|bill|payment) (?:status|balance)|'.$this->statusQuestion('invoice|bill|payment', 'status|balance').'|how much do i owe|how much is my bill|has my payment gone through|tagpila ang akong bayranan|magkano ang babayaran ko')) {
            return $this->invoiceStatus($user);
        }

        return match (true) {
            $this->matches($command, '(?:i need |request |file |submit )?(?:a )?(?:follow[ -]up|complaint|warranty claim)(?: for my repair)?') => $this->followUpGuide($user),
            $this->matches($command, '(?:book|schedule) (?:a |an |my )?(?:service|appointment|oil change|inspection)(?: for my (?:car|vehicle|motorcycle))?|new appointment|(?:magpa[ -]?book|pa[ -]?book)(?: (?:og |ng )?(?:service|appointment))?|(?:unsaon (?:pag[ -]?)?|paano (?:mag[ -]?)?)book(?: (?:og |ng )?(?:service|appointment))?') => $this->bookingGuide($user),
            default => null,
        };
    }

    /** @return array{message: string, action: array{label: string, url: string, icon: string}} */
    private function repairStatus(User $user): array
    {
        $repair = $user->repairJobs()->with(['vehicle:id,plate_number'])->latest('updated_at')->first();

        return [
            'message' => $repair
                ? 'Your latest repair '.$repair->job_number.' for '.($repair->vehicle?->plate_number ?? 'your vehicle').' is '.str_replace('_', ' ', $repair->status).'. Open its record for the full progress and any estimate that needs your approval.'
                : 'You do not have a repair job yet. Open Repair progress to check again after a shop starts one.',
            'action' => [
                'label' => 'Open repair progress',
                'url' => route('customer.repairs.index', $repair ? ['repair_job_id' => $repair->id] : []),
                'icon' => 'tools',
            ],
        ];
    }

    /** @return array{message: string, action: array{label: string, url: string, icon: string}} */
    private function appointmentStatus(User $user): array
    {
        $appointment = $user->appointments()->with(['shop:id,name'])->latest('created_at')->first();

        return [
            'message' => $appointment
                ? 'Your latest appointment request #'.$appointment->id.' with '.($appointment->shop?->name ?? 'the shop').' is '.str_replace('_', ' ', $appointment->status).'. The requested visit is '.$appointment->appointment_date->format('M j, Y').' at '.$appointment->appointment_time->format('g:i A').'. Open the appointment for the latest details.'
                : 'You do not have an appointment yet. Open Appointments to request one.',
            'action' => ['label' => 'Open my appointments', 'url' => route('customer.appointments.index'), 'icon' => 'calendar-check'],
        ];
    }

    /** @return array{message: string, action: array{label: string, url: string, icon: string}} */
    private function invoiceStatus(User $user): array
    {
        $invoice = $user->invoices()->latest('created_at')->first();

        return [
            'message' => $invoice
                ? 'Your latest invoice '.$invoice->invoice_number.' is '.str_replace('_', ' ', $invoice->status).'. Total: PHP '.number_format((float) $invoice->total, 2).'. Remaining confirmed balance: PHP '.number_format($invoice->remainingBalance(), 2).'. Review the bill before making a payment.'
                : 'You do not have an invoice yet. Bills appear after a shop issues one for your service.',
            'action' => ['label' => 'View bills and payments', 'url' => route('customer.invoices.index'), 'icon' => 'receipt'],
        ];
    }

    /** @return array{message: string, action: array{label: string, url: string, icon: string}} */
    private function followUpGuide(User $user): array
    {
        $repair = $user->repairJobs()->whereIn('status', ['completed', 'released'])->latest('completed_at')->first();

        return [
            'message' => $repair
                ? 'You can request aftercare for completed repair '.$repair->job_number.'. Open the repair, choose Follow-up, Warranty claim, or Complaint, then review your subject and description before you submit. Nothing is sent from this chat.'
                : 'A follow-up request becomes available after a repair is completed. Open Repair progress to check your jobs.',
            'action' => [
                'label' => $repair ? 'Review aftercare form' : 'Open repair progress',
                'url' => route('customer.repairs.index', $repair ? ['repair_job_id' => $repair->id] : []),
                'icon' => 'chat-left-text',
            ],
        ];
    }

    /** @return array{message: string, action: array{label: string, url: string, icon: string}} */
    private function bookingGuide(User $user): array
    {
        $vehicles = $user->vehicles()->orderBy('id')->limit(2)->get(['id', 'plate_number']);
        $vehicle = $vehicles->count() === 1 ? $vehicles->first() : null;

        return [
            'message' => $vehicle
                ? 'I found your vehicle '.$vehicle->plate_number.'. Open the booking form to review the shop, service, date, time, and listed price. Your request is sent only when you select Request appointment.'
                : 'I can start your service booking. You will still choose the shop, vehicle, service, date, and time before submitting.',
            'action' => [
                'label' => 'Book a service',
                'url' => route('customer.appointments.create', $vehicle ? ['vehicle_id' => $vehicle->id] : []),
                'icon' => 'calendar-plus',
            ],
        ];
    }

    /** @return array{reply: string, label: string, route: string, icon: string}|null */
    private function navigationAction(string $command): ?array
    {
        $actions = [
            ['pattern' => '(?:request |i need )?(?:roadside (?:help|assistance)|emergency request)|roadside|nasiraan(?: ako)?|tabang sa dalan', 'reply' => 'Open Roadside Assistance to share your pickup point and request help from a nearby provider.', 'label' => 'Open roadside assistance', 'route' => 'customer.emergency-requests.index', 'icon' => 'life-preserver'],
            ['pattern' => '(?:find |look for )?(?:a |the )?(?:nearby |service |repair )?(?:shops?|mechanics?)(?: near me| on the map)?|(?:provider |shop )map|map|pangita(?: og)? (?:shop|mekaniko)|maghanap ng (?:shop|mekaniko)', 'reply' => 'Open the directory to find verified shops and mechanics. Use the service filters or switch to Map to see providers in your area.', 'label' => 'Find shops and mechanics', 'route' => 'shops.index', 'icon' => 'geo-alt'],
            ['pattern' => '(?:my )?(?:appointments?|bookings?)|where is my (?:booking|appointment)|(?:cancel|reschedule) (?:my |an? )?(?:appointment|booking)', 'reply' => 'Here are your appointments, including pending and confirmed service visits.', 'label' => 'Open my appointments', 'route' => 'customer.appointments.index', 'icon' => 'calendar-check'],
            ['pattern' => '(?:my )?repairs?|track (?:my )?repairs?|repair photos|inspection results', 'reply' => 'Open Repair progress to review the timeline, inspection results, and any photos uploaded by your shop.', 'label' => 'Track my repairs', 'route' => 'customer.repairs.index', 'icon' => 'tools'],
            ['pattern' => '(?:my )?(?:vehicles?|garage)|add (?:a |my )?vehicle|sakyanan', 'reply' => 'Open My Vehicle to manage your vehicles and view their service history.', 'label' => 'Open my vehicles', 'route' => 'customer.vehicles.index', 'icon' => 'car-front'],
            ['pattern' => '(?:my )?(?:invoices?|bills?|payments?)|(?:pay|pay for) (?:my |an? |the )?(?:invoice|bill)|pay (?:with|through|via|using) paymongo|paymongo|bayranan|unsaon pagbayad|paano magbayad', 'reply' => 'Open Payments and select your invoice. Review the balance and available PayMongo options on the invoice page.', 'label' => 'View bills and payments', 'route' => 'customer.invoices.index', 'icon' => 'receipt'],
            ['pattern' => '(?:my )?(?:estimates?|quotations?)|(?:review|approve|decline) (?:my |an? |the )?(?:estimate|quotation)|is my estimate ready|estimate status', 'reply' => 'Open Estimates and select the estimate to review its status, items, and total before you approve or decline it.', 'label' => 'Open estimates', 'route' => 'customer.quotations.index', 'icon' => 'file-text'],
            ['pattern' => 'request (?:an? )?(?:estimate|quotation)', 'reply' => 'Open the estimate request form to choose a provider and vehicle. The provider sets the price in the estimate they send.', 'label' => 'Request an estimate', 'route' => 'customer.quotations.create', 'icon' => 'file-earmark-text'],
            ['pattern' => '(?:my )?(?:maintenance reminders?|reminders?|maintenance schedule)|(?:set|add) (?:a )?(?:maintenance )?reminder', 'reply' => 'Open the Maintenance planner to review or schedule reminders for your vehicles.', 'label' => 'Open maintenance reminders', 'route' => 'customer.reminders.index', 'icon' => 'calendar-event'],
            ['pattern' => 'message (?:my |a |the )?shop|(?:my )?messages|chat (?:with )?(?:my |a |the )?shop', 'reply' => 'Open Messages to continue your conversations with service providers.', 'label' => 'Open messages', 'route' => 'customer.messages.index', 'icon' => 'chat-dots'],
            ['pattern' => '(?:my )?reviews?|(?:leave|write) (?:a )?review', 'reply' => 'Open My Reviews. You can review a provider after a completed booking with them.', 'label' => 'Open my reviews', 'route' => 'customer.reviews.index', 'icon' => 'star'],
            ['pattern' => 'notification (?:preferences|settings)|(?:enable|turn on|set up) sms(?: alerts)?|sms settings', 'reply' => 'Open Notification preferences to save your mobile number and choose the updates you want to receive by SMS, email, or push.', 'label' => 'Open notification preferences', 'route' => 'preferences.notifications.edit', 'icon' => 'bell'],
            ['pattern' => '(?:my )?profile|edit profile|account settings', 'reply' => 'Open your profile to review or update your customer information.', 'label' => 'Open my profile', 'route' => 'customer.profile.edit', 'icon' => 'person'],
        ];

        foreach ($actions as $action) {
            if ($this->matches($command, $action['pattern'])) {
                return $action;
            }
        }

        return null;
    }

    private function withoutRequestPrefix(string $command): string
    {
        return preg_replace('/^(?:please |can you |how (?:do|can) i |where (?:do|can) i |show(?: me)? |open |view |check |see )+/', '', $command) ?? $command;
    }

    private function statusQuestion(string $records, string $details): string
    {
        return "(?:what(?: is|'s) )?(?:my (?:latest )?(?:{$records}) (?:{$details})|(?:the )?(?:{$details}) (?:of|for|on) my (?:latest )?(?:{$records})|my latest (?:{$records}))";
    }

    private function matches(string $command, string $pattern): bool
    {
        return preg_match('~\A(?:'.$pattern.')\z~', $command) === 1;
    }
}
