<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['user_id', 'path', 'original_name', 'alt_text', 'credit', 'mime_type', 'size', 'width', 'height', 'source_submission_id', 'is_public', 'private_path'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<StorySubmission, $this> */
    public function sourceSubmission(): BelongsTo
    {
        return $this->belongsTo(StorySubmission::class, 'source_submission_id');
    }
}
