<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogger
{
    public static function record(
        Request $request,
        ?User $actor,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = []
    ): void {
        AuditLog::query()->create([
            'user_id' => $actor?->id,
            'user_email' => $actor?->email,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
