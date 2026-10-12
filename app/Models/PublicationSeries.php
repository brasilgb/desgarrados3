<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicationSeries extends Model
{
    protected $table = 'series';

    protected $fillable = ['title', 'slug', 'description'];

    /** @return HasMany<Publication, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class, 'series_id')->orderBy('series_position');
    }
}
