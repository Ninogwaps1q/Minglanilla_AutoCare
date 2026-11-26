<?php

namespace App\Notifications\Workflow;

use App\Models\User;
use App\Models\WorkflowAlert;
use App\Notifications\ImportantEventMail;
use App\Notifications\WorkflowEvent;
use App\Services\SmsService;
use App\Services\WebPushDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

abstract class CustomerWorkflowNotification extends Notification
{
    public function __construct(public WorkflowAlert $alert) {}

    public function via(User $user): array
    {
        $stage = $this->alert->event->stage()->value;
        $channels = [WorkflowInboxChannel::class];
        if ($user->email && $user->receivesEventVia($stage, 'email')) {
            $channels[] = 'mail';
        }
        if (filled(config('services.semaphore.api_key')) && $user->receivesEventVia($stage, 'sms')
            && app(SmsService::class)->phone($user->phone)) {
            $channels[] = WorkflowSmsChannel::class;
        }
        if (app(WebPushDelivery::class)->configured() && $user->receivesEventVia($stage, 'push')) {
            $channels[] = WorkflowPushChannel::class;
        }

        return $channels;
    }

    public function title(User $user): string
    {
        return $this->text('title', $user);
    }

    public function message(User $user): string
    {
        $data = $this->alert->data;
        $vehicle = $data['plate'] ? "{$data['vehicle']} ({$data['plate']})" : __('workflow.vehicle_unlinked', [], $user->locale ?? 'en');
        $next = $this->text('next', $user);
        if ($this->alert->event === WorkflowEvent::InspectionResults) {
            $locale = $user->locale ?? 'en';
            $next .= ' '.__('workflow.inspection_details', ['count' => $data['problems'] ?? 0, 'photos' => $data['photos'] ?? 0], $locale);
            if (! empty($data['findings'])) {
                $next .= ' '.__('workflow.inspection_examples', ['items' => implode(', ', $data['findings'])], $locale);
            }
        }

        return $data['shop'].' · '.$vehicle.'. '.$next;
    }

    public function smsText(User $user): string
    {
        $data = $this->alert->data;
        $action = Str::ascii($this->text('sms', $user));
        $link = route('customer.workflow-alert.open', $this->alert);
        $shop = Str::ascii(Str::squish($data['shop']));
        $vehicle = Str::ascii(Str::squish($data['vehicle'] ?? __('workflow.vehicle_unlinked', [], $user->locale ?? 'en')));
        $plate = $data['plate'] ? ' ('.Str::ascii(Str::squish($data['plate'])).')' : '';
        $room = max(12, 160 - strlen($action.' '.$link.$plate.': '));
        $shopLimit = min(strlen($shop), max(5, $room - min(strlen($vehicle), (int) ceil($room / 2))));
        $vehicleLimit = max(5, $room - $shopLimit - 1);

        return Str::limit($shop, $shopLimit, '').' '.Str::limit($vehicle, $vehicleLimit, '')."{$plate}: {$action} {$link}";
    }

    public function toMail(User $user): MailMessage
    {
        $url = $this->alert->destination();

        return (new ImportantEventMail($this->title($user), $this->message($user), $url))->toMail($user)
            ->action(__('workflow.open_button', [], $user->locale ?? 'en'), $url);
    }

    private function text(string $field, User $user): string
    {
        $name = $this->alert->event->value;
        if ($this->alert->event === WorkflowEvent::EstimateReady && ($this->alert->data['revised'] ?? false)) {
            $name = 'estimate_updated';
        }

        $locale = $user->locale ?? 'en';
        $replacements = [];
        if ($this->alert->event === WorkflowEvent::AppointmentReminder) {
            $start = CarbonImmutable::parse($this->alert->data['starts_at'])->locale($locale);
            $replacements['when'] = $start->translatedFormat($field === 'sms' ? 'M j, g:i A' : 'M j, Y, g:i A').' PHT';
        }

        return __("workflow.{$name}.{$field}", $replacements, $locale);
    }
}
