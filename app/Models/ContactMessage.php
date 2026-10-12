<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'subject', 'message', 'privacy_acknowledged_at', 'forwarded_at', 'read_at'];

    protected function casts(): array
    {
        return ['privacy_acknowledged_at' => 'datetime', 'forwarded_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
