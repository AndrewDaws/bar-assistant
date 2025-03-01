<?php

declare(strict_types=1);

namespace Kami\Cocktail\Services;

use Throwable;
use Illuminate\Log\LogManager;
use Kami\Cocktail\Models\Image;
use Kami\Cocktail\Models\Cocktail;
use Kami\Cocktail\Models\Ingredient;
use Illuminate\Database\Eloquent\Model;
use Kami\Cocktail\Models\IngredientPrice;
use Kami\Cocktail\Models\ComplexIngredient;
use Kami\Cocktail\Exceptions\IngredientException;
use Kami\Cocktail\OpenAPI\Schemas\IngredientRequest;
use Kami\Cocktail\Exceptions\IngredientParentException;
use Kami\Cocktail\Exceptions\ImagesNotAttachedException;

final class IngredientService
{
    public function __construct(
        private readonly LogManager $log,
    ) {
    }

    public function createIngredient(IngredientRequest $dto): Ingredient
    {
        try {
            if ($dto->name === '') {
                throw new IngredientException('Invalid ingredient name');
            }

            $ingredient = new Ingredient();
            $ingredient->bar_id = $dto->barId;
            $ingredient->name = $dto->name;
            $ingredient->ingredient_category_id = $dto->ingredientCategoryId;
            $ingredient->strength = $dto->strength;
            $ingredient->description = $dto->description;
            $ingredient->origin = $dto->origin;
            $ingredient->color = $dto->color;
            $ingredient->parent_ingredient_id = $dto->parentIngredientId;
            $ingredient->created_user_id = $dto->userId;
            $ingredient->calculator_id = $dto->calculatorId;
            $ingredient->save();

            foreach ($dto->complexIngredientParts as $ingredientPartId) {
                $part = new ComplexIngredient();
                $part->ingredient_id = $ingredientPartId;
                $part->main_ingredient_id = $ingredient->id;
                $part->save();
            }

            foreach ($dto->prices as $ingredientPriceDto) {
                $price = new IngredientPrice();
                $price->ingredient_id = $ingredient->id;
                $price->price_category_id = $ingredientPriceDto->priceCategoryId;
                $price->price = $ingredientPriceDto->price;
                $price->amount = $ingredientPriceDto->amount;
                $price->units = $ingredientPriceDto->units;
                $price->description = $ingredientPriceDto->description;
                $price->save();
            }
        } catch (Throwable $e) {
            $this->log->error('[INGREDIENT_SERVICE] ' . $e->getMessage());

            throw $e;
        }

        if (count($dto->images) > 0) {
            try {
                $imageModels = Image::findOrFail($dto->images);
                $ingredient->attachImages($imageModels);
            } catch (Throwable $e) {
                throw new ImagesNotAttachedException();
            }
        }

        // Refresh model for response
        $ingredient->refresh();
        // Upsert scout index
        $ingredient->save();

        return $ingredient;
    }

    public function updateIngredient(int $id, IngredientRequest $dto): Ingredient
    {
        if ($dto->parentIngredientId === $id) {
            throw new IngredientParentException('Parent ingredient is the same as the current ingredient!');
        }

        $originalStrength = null;

        try {
            $ingredient = Ingredient::findOrFail($id);
            $originalStrength = $ingredient->strength;
            $ingredient->name = $dto->name;
            $ingredient->ingredient_category_id = $dto->ingredientCategoryId;
            $ingredient->strength = $dto->strength;
            $ingredient->description = $dto->description;
            $ingredient->origin = $dto->origin;
            $ingredient->color = $dto->color;
            $ingredient->parent_ingredient_id = $dto->parentIngredientId;
            $ingredient->updated_user_id = $dto->userId;
            $ingredient->updated_at = now();
            $ingredient->calculator_id = $dto->calculatorId;
            $ingredient->save();

            Model::unguard();
            $currentIngredientParts = [];
            foreach ($dto->complexIngredientParts as $complexPartId) {
                $currentIngredientParts[] = $complexPartId;
                $ingredient->ingredientParts()->updateOrCreate([
                    'ingredient_id' => $complexPartId
                ]);
            }
            $ingredient->ingredientParts()->whereNotIn('ingredient_id', $currentIngredientParts)->delete();
            Model::reguard();

            if (count($dto->prices) > 0) {
                $ingredient->prices()->delete();
                foreach ($dto->prices as $ingredientPriceDto) {
                    $price = new IngredientPrice();
                    $price->ingredient_id = $ingredient->id;
                    $price->price_category_id = $ingredientPriceDto->priceCategoryId;
                    $price->price = $ingredientPriceDto->price;
                    $price->amount = $ingredientPriceDto->amount;
                    $price->units = $ingredientPriceDto->units;
                    $price->description = $ingredientPriceDto->description;
                    $price->save();
                }
            }

        } catch (Throwable $e) {
            $this->log->error('[INGREDIENT_SERVICE] ' . $e->getMessage());

            throw $e;
        }

        if (count($dto->images) > 0) {
            try {
                $imageModels = Image::findOrFail($dto->images);
                $ingredient->attachImages($imageModels);
            } catch (Throwable $e) {
                throw new ImagesNotAttachedException();
            }
        }

        $this->log->info('[INGREDIENT_SERVICE] Ingredient updated with id:' . $ingredient->id);

        // Refresh model for response
        $ingredient->refresh();
        // Upsert scout index
        $ingredient->save();

        $ingredient->loadMissing('cocktails.ingredients.ingredient');

        if ($originalStrength !== null && $originalStrength !== $ingredient->strength) {
            $this->log->debug('[INGREDIENT_SERVICE] Updated ingredient strength, updating ' . $ingredient->cocktails->count() . ' cocktails.');
            $ingredient->cocktails->each(function (Cocktail $cocktail) {
                $cocktail->abv = $cocktail->getABV();
                $cocktail->save();
            });
        }

        $ingredient->cocktails->each(fn ($cocktail) => $cocktail->searchable());

        return $ingredient;
    }
}
