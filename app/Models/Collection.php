<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Collection extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_featured'];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean'];
    }

    /** @return BelongsToMany<Publication, $this> */
    public function publications(): BelongsToMany
    {
        return $this->belongsToMany(Publication::class, 'publication_collection');
    }
}
