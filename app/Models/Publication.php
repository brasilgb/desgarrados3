<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Publication extends Model
{
    protected $hidden = ['media_visibility_synced_at'];

    protected $fillable = ['user_id', 'category_id', 'title', 'slug', 'excerpt', 'body', 'type', 'credit', 'origin_state', 'origin_city', 'origin_scope', 'municipality_code', 'source_references', 'cover_path', 'status', 'published_at', 'seo_title', 'seo_description', 'is_featured', 'series_id', 'series_position'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'media_visibility_synced_at' => 'datetime', 'is_featured' => 'boolean', 'lock_version' => 'integer', 'source_references' => 'array'];
    }

    /** @return HasMany<PublicationRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PublicationRevision::class)->latest('created_at');
    }

    public function recordRevision(User $editor, ?PublicationRevision $restoredFrom = null): PublicationRevision
    {
        $snapshot = $this->only([
            'title', 'slug', 'excerpt', 'body', 'type', 'category_id', 'credit', 'origin_state', 'origin_city', 'origin_scope', 'municipality_code', 'source_references',
            'cover_path', 'status', 'published_at', 'seo_title', 'seo_description', 'is_featured', 'series_id', 'series_position',
        ]);
        $publishedAt = $this->getAttribute('published_at');
        $snapshot['published_at'] = filled($publishedAt) ? Carbon::parse($publishedAt)->toISOString() : null;
        $snapshot['collection_ids'] = $this->collections()->pluck('collections.id')->all();
        $snapshot['category_ids'] = $this->categories()->pluck('categories.id')->when($this->category_id, fn ($ids) => $ids->push($this->category_id))->unique()->values()->all();

        return $this->revisions()->create([
            'user_id' => $editor->id,
            'restored_from_revision_id' => $restoredFrom?->id,
            'snapshot' => $snapshot,
            'created_at' => now(),
        ]);
    }

    /** @return BelongsTo<TerritorialUnit, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(TerritorialUnit::class, 'municipality_code', 'ibge_code');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'publication_category')->orderBy('name');
    }

    /** @return BelongsTo<Media, $this> */
    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_path', 'path');
    }

    /** @return BelongsToMany<Collection, $this> */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'publication_collection');
    }

    /** @return BelongsTo<PublicationSeries, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(PublicationSeries::class, 'series_id');
    }

    /** Only what readers may see: published, or scheduled with a publication time already reached. */
    /**
     * @param  Builder<Publication>  $query
     * @return Builder<Publication>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now());
    }

    /** Portal text search on title, excerpt and body; LIKE wildcards typed by the reader are matched literally. */
    /**
     * @param  Builder<Publication>  $query
     * @return Builder<Publication>
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '\\%_').'%';

        return $query->where(fn (Builder $builder) => $builder->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)->orWhere('body', 'like', $like));
    }
}
