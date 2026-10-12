<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    /** @return HasMany<Publication, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    /** @return BelongsToMany<Publication, $this> */
    public function categorizedPublications(): BelongsToMany
    {
        return $this->belongsToMany(Publication::class, 'publication_category');
    }
}
