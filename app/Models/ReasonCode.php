<?php

namespace App\Models;

use App\Enums\ReasonCodeCategory;
use Database\Factories\ReasonCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property ReasonCodeCategory $category
 * @property string $code
 * @property string $label
 * @property string|null $description
 * @property bool $active
 */
#[Fillable(['category', 'code', 'label', 'description', 'active'])]
class ReasonCode extends Model
{
    /** @use HasFactory<ReasonCodeFactory> */
    use HasFactory;

    /**
     * @param  Builder<ReasonCode>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * @param  Builder<ReasonCode>  $query
     */
    public function scopeForCategory(Builder $query, ReasonCodeCategory $category): void
    {
        $query->where('category', $category);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'category' => ReasonCodeCategory::class,
        ];
    }
}
