<?php

namespace App\Notifications;

use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PipelineTouchedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Pipeline $pipeline,
        public readonly User $actor,
        public readonly string $action,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Site updated: {$this->pipeline->name}")
            ->greeting('Site activity update')
            ->line("{$this->actor->full_name} {$this->action}.")
            ->line("Site: {$this->pipeline->name} ({$this->pipeline->pipeline_id})")
            ->line("Current stage: {$this->pipeline->status->value}")
            ->action('View Site', route('pipelines.show', $this->pipeline));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'pipeline_id' => $this->pipeline->id,
            'actor_id' => $this->actor->id,
            'action' => $this->action,
        ];
    }
}
