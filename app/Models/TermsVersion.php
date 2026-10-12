<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** A text of the submission terms exactly as contributors saw it. Rows are never changed or removed. */
class TermsVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['document', 'content_hash', 'label', 'source', 'title', 'body'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Accepted terms versions are immutable.'));
        static::deleting(fn () => throw new LogicException('Accepted terms versions cannot be deleted.'));
    }
}
