<?php

declare(strict_types=1);

namespace Kami\Cocktail\Services;

use Meilisearch\Client;
use Meilisearch\Endpoints\Keys;

final class MeilisearchService
{
    private bool $isNewMeilisearchKey;

    public function __construct(private readonly Client $client)
    {
    }

    public function isNewMeilisearchKey(): bool
    {
        return $this->isNewMeilisearchKey;
    }

    public function getSearchAPIKey(): Keys
    {
        $searchApiKey = null;

        $keys = $this->client->getKeys();
        /** @var \Meilisearch\Endpoints\Keys */
        foreach ($keys->getResults() as $key) {
            if ($key->getName() === 'bar-assistant') {
                $this->isNewMeilisearchKey = false;
                $searchApiKey = $key;
            }
        }

        if (!$searchApiKey) {
            $this->isNewMeilisearchKey = true;
            $searchApiKey = $this->client->createKey([
                'actions' => ['search'],
                'indexes' => ['cocktails', 'ingredients'],
                'expiresAt' => null,
                'name' => 'bar-assistant',
                'description' => 'Client key generated by Bar Assistant Server. Key for accessing cocktails and ingredients indexes.'
            ]);
        }

        return $searchApiKey;
    }
}