<?php

namespace App\Actions\Operations;

use App\Enums\RestoreTestStatus;
use App\Models\RestoreTestRecord;
use App\Models\User;
use InvalidArgumentException;

class RecordRestoreTest
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        ?User $verifiedBy,
        RestoreTestStatus $status,
        bool $databaseVerified,
        bool $mediaVerified,
        ?string $backupDisk = null,
        ?string $backupPath = null,
        ?string $notes = null,
        ?array $metadata = null,
    ): RestoreTestRecord {
        if ($status === RestoreTestStatus::Passed && (! $databaseVerified || ! $mediaVerified)) {
            throw new InvalidArgumentException('Passed restore tests must verify both database and critical media.');
        }

        return RestoreTestRecord::query()->create([
            'verified_by' => $verifiedBy?->id,
            'status' => $status,
            'backup_disk' => $backupDisk,
            'backup_path' => $backupPath,
            'database_verified' => $databaseVerified,
            'media_verified' => $mediaVerified,
            'notes' => $notes,
            'metadata' => $metadata,
            'tested_at' => now(),
        ]);
    }
}
