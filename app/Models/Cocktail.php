<?php
declare(strict_types=1);

namespace Kami\Cocktail\Models;

use Laravel\Scout\Searchable;
use Spatie\Sluggable\HasSlug;
use Kami\Cocktail\SearchActions;
use Spatie\Sluggable\SlugOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cocktail extends Model
{
    use HasFactory, Searchable, HasImages, HasSlug;

    private $appImagesDir = 'cocktails/';
    private $missingImageFileName = 'no-image.jpg'; // TODO: WEBP

    protected static function booted()
    {
        static::saved(function($cocktail) {
            SearchActions::update($cocktail);
        });

        static::deleted(function($cocktail) {
            SearchActions::delete($cocktail);
        });
    }

    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function glass(): BelongsTo
    {
        return $this->belongsTo(Glass::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(CocktailIngredient::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function delete()
    {
        $this->deleteImages();

        return parent::delete();
    }

    public function toSiteSearchArray()
    {
        return [
            'key' => 'co_' . (string) $this->id,
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'image_url' => $this->getImageUrl(),
            'type' => 'cocktail',
        ];
    }

    public function toSearchableArray(): array
    {
        // Some attributes are not searchable as per SearchActions settings
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'source' => $this->source,
            'garnish' => $this->garnish,
            'image_url' => $this->getImageUrl(),
            'short_ingredients' => $this->ingredients->pluck('ingredient.name'),
            'user_id' => $this->user_id,
            'tags' => $this->tags->pluck('name'),
            'date' => $this->updated_at->format('Y-m-d H:i:s')
        ];
    }
}
