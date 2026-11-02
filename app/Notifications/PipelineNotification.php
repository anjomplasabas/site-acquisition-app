<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Pipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PipelineNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly NotificationType $type,
        public readonly Pipeline $pipeline,
        public readonly string $message,
        public readonly string $url,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type->value,
            'title' => $this->type->label(),
            'message' => $this->message,
            'pipeline_id' => $this->pipeline->id,
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->type->label()}: {$this->pipeline->name}")
            ->greeting($this->type->label())
            ->line($this->message)
            ->line("Site: {$this->pipeline->name} ({$this->pipeline->pipeline_id})")
            ->line("Current stage: {$this->pipeline->status->value}")
            ->action('View Site', $this->url);
    }
}
