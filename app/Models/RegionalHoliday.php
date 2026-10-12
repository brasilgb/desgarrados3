<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegionalHoliday extends Model
{
    protected $fillable = ['provider', 'provider_event_key', 'name', 'date', 'scope', 'state_code', 'optional', 'reference_year', 'source_url', 'last_synced_at'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'optional' => 'boolean', 'last_synced_at' => 'datetime'];
    }
}
