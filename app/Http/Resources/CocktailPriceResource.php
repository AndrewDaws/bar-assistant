<?php

declare(strict_types=1);

namespace Kami\Cocktail\Http\Resources;

use Throwable;
use Kami\Cocktail\Models\Price;
use Kami\RecipeUtils\UnitConverter\Units;
use Kami\Cocktail\Models\CocktailIngredient;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Kami\Cocktail\Models\CocktailPrice
 */
class CocktailPriceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        $prices = $this->cocktail->ingredients->map(function (CocktailIngredient $cocktailIngredient) {
            $price = $cocktailIngredient->getMinPriceInCategory($this->priceCategory);

            if ($price === null) {
                return null;
            }

            $converted = $cocktailIngredient->getConvertedTo(Units::tryFrom($price->units), []);
            if ($converted->getUnits() === null) {
                return null;
            }

            try {
                $pricePerPour = $price->getPricePerPour($converted->getAmount(), $converted->getUnits());
            } catch (Throwable) {
                return null;
            }

            return [
                'ingredient' => new IngredientBasicResource($cocktailIngredient->ingredient),
                'price_per_amount' => new PriceResource(new Price($price->getPricePerUnit())),
                'price_per_pour' => new PriceResource(new Price($pricePerPour)),
            ];
        })->filter()->values();

        return [
            'missing_prices_count' => $this->cocktail->ingredients->count() - $prices->count(),
            'price_category' => new PriceCategoryResource($this->priceCategory),
            'total_price' => new PriceResource(new Price($this->cocktail->calculatePrice($this->priceCategory))),
            'prices_per_ingredient' => $prices,
        ];
    }
}