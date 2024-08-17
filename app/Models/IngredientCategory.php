<?php

declare(strict_types=1);

namespace Kami\Cocktail\Models;

use Kalnoy\Nestedset\NodeTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kami\Cocktail\Models\Concerns\HasBarAwareScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IngredientCategory extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\IngredientCategoryFactory> */
    use HasFactory;
    use HasBarAwareScope;
    use NodeTrait;

    /**
     * @return HasMany<Ingredient>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    protected function getScopeAttributes(): array
    {
        return ['bar_id'];
    }
}
