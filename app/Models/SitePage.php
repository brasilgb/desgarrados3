<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SitePage extends Model
{
    protected $fillable = ['title', 'slug', 'summary', 'body', 'status', 'updated_by'];
}
