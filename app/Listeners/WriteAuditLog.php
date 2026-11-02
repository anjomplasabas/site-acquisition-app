<?php

namespace App\Listeners;

use App\Events\AuditLogged;
use App\Models\AuditLog;
use App\Models\User;

class WriteAuditLog
{
    public function handle(AuditLogged $event): void
    {
        $actor = $event->userId ? User::find($event->userId) : null;

        AuditLog::create([
            'user_id' => $event->userId,
            'actor_name' => $actor?->full_name,
            'actor_email' => $actor?->email,
            'action' => $event->action,
            'subject_type' => $event->subjectType,
            'subject_id' => $event->subjectId,
            'description' => $event->description,
            'ip_address' => $event->ipAddress,
            'metadata' => $event->metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
