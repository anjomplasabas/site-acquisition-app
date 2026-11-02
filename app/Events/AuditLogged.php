<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditLogged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $action,
        public readonly string $description,
        public readonly ?int $userId,
        public readonly ?string $subjectType,
        public readonly ?int $subjectId,
        public readonly ?string $ipAddress,
        public readonly array $metadata = [],
    ) {}

    public static function emit(string $action, string $description, ?Model $subject = null, array $metadata = []): void
    {
        static::dispatch(
            action: $action,
            description: $description,
            userId: auth()->id(),
            subjectType: $subject ? $subject::class : null,
            subjectId: $subject?->getKey(),
            ipAddress: request()->ip(),
            metadata: $metadata,
        );
    }
}
