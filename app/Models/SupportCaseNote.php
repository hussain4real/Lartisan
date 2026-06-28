<?php

namespace App\Models;

use Database\Factories\SupportCaseNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $support_case_id
 * @property int|null $author_id
 * @property string $body
 * @property bool $is_internal
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'support_case_id',
    'author_id',
    'body',
    'is_internal',
    'metadata',
])]
class SupportCaseNote extends Model
{
    /** @use HasFactory<SupportCaseNoteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<SupportCase, $this>
     */
    public function supportCase(): BelongsTo
    {
        return $this->belongsTo(SupportCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
