<?php

namespace App\Models;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
