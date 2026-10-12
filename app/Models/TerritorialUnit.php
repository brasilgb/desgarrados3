<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TerritorialUnit extends Model
{
    protected $primaryKey = 'ibge_code';

    public $incrementing = false;

    protected $fillable = ['ibge_code', 'unit_type', 'name', 'state_code', 'state_name', 'region_name'];
}
