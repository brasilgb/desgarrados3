<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionalEvent extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'category', 'municipality', 'state', 'venue', 'starts_on', 'ends_on',
        'organizer', 'source_name', 'source_url', 'verified_at', 'additional_url', 'status', 'is_featured', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'verified_at' => 'date:Y-m-d', 'is_featured' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
