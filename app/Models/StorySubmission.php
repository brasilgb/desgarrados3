<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class StorySubmission extends Model
{
    protected $fillable = ['title', 'story', 'contributor_name', 'origin_city', 'origin_state', 'contact_email', 'contact_consent', 'rights_confirmed', 'publication_consent', 'display_name_consent', 'photo_path', 'photo_original_name', 'photo_credit', 'photo_publication_consent', 'status', 'moderation_note', 'reviewed_by', 'publication_id', 'reviewed_at', 'moderation_approved_at', 'terms_accepted_at', 'terms_version', 'terms_version_id'];

    protected function casts(): array
    {
        return ['rights_confirmed' => 'boolean', 'contact_consent' => 'boolean', 'publication_consent' => 'boolean', 'display_name_consent' => 'boolean', 'photo_publication_consent' => 'boolean', 'reviewed_at' => 'datetime', 'moderation_approved_at' => 'datetime', 'terms_accepted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // A recorded acceptance documents what the contributor agreed to; it is never rewritten.
        static::updating(function (StorySubmission $submission): void {
            foreach (['terms_accepted_at', 'terms_version', 'terms_version_id'] as $field) {
                if ($submission->getOriginal($field) !== null && $submission->isDirty($field)) {
                    throw new LogicException('The recorded acceptance of the submission terms cannot be changed.');
                }
            }
        });
    }

    /** @return BelongsTo<TermsVersion, $this> */
    public function acceptedTerms(): BelongsTo
    {
        return $this->belongsTo(TermsVersion::class, 'terms_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<Publication, $this> */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /** @return HasOne<Media, $this> */
    public function publicMedia(): HasOne
    {
        return $this->hasOne(Media::class, 'source_submission_id');
    }
}
