<?php

namespace App\Actions\SupportCases;

use App\Actions\Audit\RecordAuditLog;
use App\Models\SupportCase;
use App\Models\SupportCaseNote;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AddSupportCaseNote
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(SupportCase $supportCase, User $author, string $body): SupportCaseNote
    {
        Gate::forUser($author)->authorize('update', $supportCase);

        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('An internal note is required.');
        }

        $note = SupportCaseNote::query()->create([
            'support_case_id' => $supportCase->id,
            'author_id' => $author->id,
            'body' => $body,
            'is_internal' => true,
            'metadata' => ['source' => 'support_inbox'],
        ]);

        $this->recordAuditLog->handle(
            actor: $author,
            action: 'support_case.note.created',
            subject: $supportCase,
            after: [
                'note_id' => $note->id,
                'is_internal' => true,
            ],
        );

        return $note->refresh();
    }
}
