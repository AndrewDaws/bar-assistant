<?php

declare(strict_types=1);

namespace Kami\Cocktail\Scraper;

use Symfony\Component\Uid\Ulid;
use Kami\RecipeUtils\Parser\Parser;
use Kami\RecipeUtils\RecipeIngredient;
use Kami\Cocktail\External\Model\Schema;
use Kami\RecipeUtils\UnitConverter\Units;
use Symfony\Component\DomCrawler\Crawler;
use Kami\Cocktail\External\Model\Cocktail;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\BrowserKit\HttpBrowser;
use Kami\Cocktail\External\Model\IngredientBasic;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;

abstract class AbstractSiteExtractor implements SiteExtractorContract
{
    protected readonly Crawler $crawler;
    protected readonly Parser $ingredientParser;

    public function __construct(
        protected readonly string $url,
        protected readonly ?Units $defaultConvertTo = null,
    ) {
        $store = new Store(storage_path('http_cache/'));
        $client = HttpClient::create([
            'max_redirects' => 0,
            'timeout' => 10,
        ]);
        $client = new NoPrivateNetworkHttpClient($client);
        $client = new CachingHttpClient($client, $store);
        $browser = new HttpBrowser($client);

        $browser->request('GET', $url);

        /** @var \Symfony\Component\BrowserKit\Response $response */
        $response = $browser->getResponse();
        $this->crawler = new Crawler($response->getContent());
        $this->ingredientParser = new Parser();
    }

    /**
     * Array with a list of support sites. All sites must be defined
     * with protocol (ex: https://) and end without slash
     *
     * @return array<string>
     */
    abstract public static function getSupportedUrls(): array;

    /**
     * Cocktail name
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * Cocktail description, can support markdown
     *
     * @return null|string
     */
    public function description(): ?string
    {
        return null;
    }

    /**
     * Cocktail source URL
     *
     * @return null|string
     */
    public function source(): ?string
    {
        return null;
    }

    /**
     * Cocktail preparation instructions, can support markdown
     *
     * @return null|string
     */
    abstract public function instructions(): ?string;

    /**
     * Cocktail tags
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [];
    }

    /**
     * Cocktail serving glass
     *
     * @return null|string
     */
    public function glass(): ?string
    {
        return null;
    }

    /**
     * Array containing cocktail ingredients
     *
     * @return array<RecipeIngredient>
     */
    public function ingredients(): array
    {
        return [];
    }

    /**
     * Cocktail garnish, can support markdown
     *
     * @return null|string
     */
    public function garnish(): ?string
    {
        return null;
    }

    /**
     * Array containing image information
     *
     * @return null|array{"uri": string|null, "copyright": string|null}
     */
    public function image(): ?array
    {
        return null;
    }

    /**
     * Cocktail method (shake, stir...)
     *
     * @return null|string
     */
    public function method(): ?string
    {
        return null;
    }

    /**
     * Cocktail information as array
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $ingredients = $this->ingredients();
        $ingredients = array_map(function (RecipeIngredient $recipeIngredient, int $sort) {
            return [
                '_id' => Ulid::generate(),
                'name' => $this->clean(ucfirst($recipeIngredient->name)),
                'amount' => $recipeIngredient->amount,
                'amount_max' => $recipeIngredient->amountMax,
                'units' => $recipeIngredient->units === '' ? null : $recipeIngredient->units,
                'note' => $recipeIngredient->comment === '' ? null : $recipeIngredient->comment,
                'original_amount' => $recipeIngredient->originalAmount,
                'source' => $this->clean($recipeIngredient->source),
                'optional' => false,
                'sort' => $sort + 1,
            ];
        }, $ingredients, array_keys($ingredients));

        $meta = array_map(function (array $org) {
            return [
                '_id' => $org['_id'],
                'source' => $org['source'],
                'original_amount' => $org['original_amount'],
            ];
        }, $ingredients);

        $cocktail = Cocktail::fromDraft2Array([
            'name' => $this->clean($this->name()),
            'instructions' => $this->instructions(),
            'description' => $this->cleanDescription($this->description()),
            'source' => $this->source(),
            'glass' => $this->glass(),
            'garnish' => $this->clean($this->garnish()),
            'tags' => $this->tags(),
            'method' => $this->method(),
            'images' => [
                $this->convertImagesToDataUri()
            ],
            'ingredients' => $ingredients,
        ]);

        $model = new Schema(
            $cocktail,
            array_map(fn ($ingredient) => IngredientBasic::fromDraft2Array($ingredient), $ingredients),
        );

        return [
            'schema_version' => $model::SCHEMA_VERSION,
            'schema' => $model->toDraft2Array(),
            'scraper_meta' => $meta,
        ];
    }

    /**
     * Cleans up white space in a string and decodes HTML entities.
     *
     * @param ?string $str The string to clean up.
     * @return ?string The cleaned up string.
     */
    protected function clean(?string $str): ?string
    {
        if (!$str) {
            return null;
        }

        $str = str_replace(' ', " ", $str);
        $str = preg_replace("/\s+/u", " ", $str);

        return html_entity_decode($str, encoding: 'UTF-8');
    }

    /**
     * Clean up the cocktail description.
     *
     * This function will be used to clean up the string produced by {@see AbstractSiteExtractor::description() description()}.
     * Can be overriden by scrapers that do the clean up internally within {@see AbstractSiteExtractor::description() description()}
     * so that they can, for example, produce Markdown with properly separated paragraphs.
     *
     * @param ?string $description The cocktail description to clean up.
     * @return ?string The cleaned up description.
     */
    protected function cleanDescription(?string $description): ?string
    {
        return $this->clean($description);
    }

    /**
     * @return array<string, string|null>
     */
    private function convertImagesToDataUri(): array
    {
        $image = $this->image();
        if ($image['uri']) {
            $url = parse_url($image['uri']);
            $cleanUrl = ($url['scheme'] ?? '') . '://' . ($url['host'] ?? '') . (isset($url['path']) ? $url['path'] : '');

            $dataUri = null;
            $type = pathinfo($cleanUrl, PATHINFO_EXTENSION);
            if ($data = file_get_contents($cleanUrl)) {
                $dataUri = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }

            return [
                'uri' => $dataUri,
                'copyright' => $image['copyright'],
            ];
        }

        return $image;
    }
}
