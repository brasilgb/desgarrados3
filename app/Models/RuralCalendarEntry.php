<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuralCalendarEntry extends Model
{
    protected $fillable = [
        'activity', 'category', 'activity_type', 'frequency', 'region', 'state', 'municipality',
        'starts_on', 'ends_on', 'reference_period', 'source_name', 'source_url', 'verified_at', 'notes',
        'is_published', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'verified_at' => 'date:Y-m-d', 'is_published' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
