<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Avatar extends Model
{

    use SoftDeletes;
    
    protected $fillable = [
        'filename',
        'path',
        'is_default',
        'description',
        'is_active',
        'gender',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeForGender(Builder $query, ?string $gender): Builder
    {
        return $gender ? $query->where('gender', $gender) : $query;
    }

    public static function fallbackFor(?string $gender): ?self
    {
        if ($gender) {
            $fallback = static::where('filename', "default_profile_{$gender}.jpg")->first();

            if ($fallback) {
                return $fallback;
            }
        }

        return static::selectable()->forGender($gender)->inRandomOrder()->first()
            ?? static::selectable()->inRandomOrder()->first();
    }
}
