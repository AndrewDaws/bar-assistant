<?php

declare(strict_types=1);

namespace Kami\Cocktail\Http\Resources;

use Kami\Cocktail\Models\MenuCocktail;
use Kami\Cocktail\Models\ValueObjects\Price;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Kami\Cocktail\Models\Menu
 */
class MenuResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'is_enabled' => (bool) $this->is_enabled,
            'created_at' => $this->created_at->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
            'categories' => $this->menuCocktails->groupBy('category_name')->map(function ($categoryCocktails, $name) {
                return [
                    'name' => $name,
                    'cocktails' => $categoryCocktails->map(function (MenuCocktail $menuCocktail) {
                        return [
                            'id' => $menuCocktail->cocktail_id,
                            'slug' => $menuCocktail->cocktail->slug,
                            'sort' => $menuCocktail->sort,
                            'price' => new PriceResource(new Price($menuCocktail->getMoney())),
                            'name' => $menuCocktail->cocktail->name,
                            'short_ingredients' => $menuCocktail->cocktail->getIngredientNames(),
                        ];
                    }),
                ];
            })->values()
        ];
    }
}
