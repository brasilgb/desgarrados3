<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZarcRecord extends Model
{
    protected $fillable = ['dataset_key', 'row_hash', 'season_start', 'season_end', 'culture', 'state_code', 'municipality_code', 'municipality', 'source_row'];

    protected function casts(): array
    {
        return ['source_row' => 'array'];
    }
}
