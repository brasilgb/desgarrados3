<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionGuide extends Model
{
    protected $fillable = ['title', 'slug', 'category', 'region', 'state_code', 'summary', 'body', 'source_name', 'source_url', 'reference_date', 'status', 'updated_by'];

    protected function casts(): array
    {
        return ['reference_date' => 'date:Y-m-d'];
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
