<?php

declare(strict_types=1);

namespace Kami\Cocktail\OpenAPI\Schemas;

use OpenApi\Attributes as OAT;

#[OAT\Schema(required: ['id', 'name', 'cocktails_count'])]
class Tag
{
    #[OAT\Property(example: 1)]
    public int $id;
    #[OAT\Property(example: 'Floral')]
    public string $name;
    #[OAT\Property(property: 'cocktails_count', example: 12)]
    public int $cocktailsCount;
}
