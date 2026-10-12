<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegionalIntegration extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'name', 'data_type', 'source_url', 'license', 'availability', 'enabled', 'records_count', 'last_synced_at', 'last_attempted_at', 'next_sync_at', 'last_error', 'metadata'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'metadata' => 'array', 'last_synced_at' => 'datetime', 'last_attempted_at' => 'datetime', 'next_sync_at' => 'datetime'];
    }
}
