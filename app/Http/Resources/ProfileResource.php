<?php

declare(strict_types=1);

namespace Kami\Cocktail\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Kami\Cocktail\Models\User
 */
class ProfileResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'is_admin' => $this->isAdmin(),
            'search_host' => config('scout.meilisearch.host'),
            'search_api_key' => $this->search_api_key,
            'favorite_cocktails' => $this->favorites->pluck('cocktail_id'),
            'shelf_ingredients' => $this->shelfIngredients->pluck('ingredient_id'),
            'shopping_lists' => $this->shoppingLists->pluck('ingredient_id'),
        ];
    }
}
