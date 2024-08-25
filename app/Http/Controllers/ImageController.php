<?php

declare(strict_types=1);

namespace Kami\Cocktail\Http\Controllers;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OAT;
use Kami\Cocktail\Models\Image;
use Illuminate\Http\UploadedFile;
use Kami\Cocktail\OpenAPI as BAO;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Kami\Cocktail\Http\Requests\ImageRequest;
use Kami\Cocktail\DTO\Image\Image as ImageDTO;
use Kami\Cocktail\Services\Image\ImageService;
use Kami\Cocktail\Http\Resources\ImageResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Kami\Cocktail\Http\Requests\ImageUpdateRequest;
use Kami\Cocktail\Services\Image\ImageThumbnailService;

class ImageController extends Controller
{
    #[OAT\Get(path: '/images', tags: ['Images'], summary: 'List uploaded images', description: 'List all images uploaded by the authenticated user', parameters: [
        new BAO\Parameters\PageParameter(),
        new BAO\Parameters\PerPageParameter(),
    ])]
    #[OAT\Response(response: 200, description: 'Successful response', content: [
        new BAO\PaginateData(BAO\Schemas\Image::class),
    ])]
    #[BAO\NotAuthorizedResponse]
    #[BAO\NotFoundResponse]
    public function index(Request $request): JsonResource
    {
        $images = Image::where('created_user_id', $request->user()->id)->orderBy('created_at', 'desc')->paginate($request->get('per_page', 100));

        return ImageResource::collection($images->withQueryString());
    }

    #[OAT\Get(path: '/images/{id}', tags: ['Images'], summary: 'Show an image', parameters: [
        new BAO\Parameters\DatabaseIdParameter(),
    ])]
    #[OAT\Response(response: 200, description: 'Successful response', content: [
        new BAO\WrapObjectWithData(BAO\Schemas\Image::class),
    ])]
    #[BAO\NotAuthorizedResponse]
    #[BAO\NotFoundResponse]
    public function show(Request $request, int $id): JsonResource
    {
        $image = Image::findOrFail($id);

        if ($request->user()->cannot('show', $image)) {
            abort(403);
        }

        return new ImageResource($image);
    }

    #[OAT\Post(path: '/images', tags: ['Images'], summary: 'Upload an image', description: 'Used to upload multiple images at once. Uploaded images via this endpoint will not be attached to any resource. Images are converted to WebP format with 85% quality of the original image.', requestBody: new OAT\RequestBody(
        required: true,
        content: [
            new OAT\MediaType(mediaType: 'multipart/form-data', schema: new OAT\Schema(type: 'object', required: ['images'], properties: [
                new OAT\Property(property: 'images', type: 'array', items: new OAT\Items(ref: BAO\Schemas\ImageRequest::class)),
            ])),
        ]
    ))]
    #[OAT\Response(response: 200, description: 'Successful response', content: [
        new BAO\WrapItemsWithData(BAO\Schemas\Image::class),
    ])]
    public function store(ImageService $imageservice, ImageRequest $request): JsonResource
    {
        $images = [];
        foreach ($request->images ?? [] as $formImage) {
            if (isset($formImage['image'])) {
                /** @var UploadedFile $imageSource */
                $imageSource = $formImage['image']->get();
            } else {
                $imageSource = file_get_contents((string) $formImage['image_url']);
            }

            try {
                $image = new ImageDTO(
                    $imageSource,
                    $formImage['copyright'] ?? null,
                    (int) ($formImage['sort'] ?? 0),
                );
                $images[] = $image;
            } catch (Throwable $e) {
                Log::error($e->getMessage());
            }
        }

        $images = $imageservice->uploadAndSaveImages($images, $request->user()->id);

        return ImageResource::collection($images);
    }

    #[OAT\Post(path: '/images/{id}', tags: ['Images'], summary: 'Update image', parameters: [
        new BAO\Parameters\DatabaseIdParameter(),
    ], requestBody: new OAT\RequestBody(
        required: true,
        content: [
            new OAT\MediaType(mediaType: 'multipart/form-data', schema: new OAT\Schema(ref: BAO\Schemas\ImageRequest::class)),
        ]
    ))]
    #[OAT\Response(response: 200, description: 'Successful response', content: [
        new BAO\WrapObjectWithData(BAO\Schemas\Image::class),
    ])]
    #[BAO\NotAuthorizedResponse]
    public function update(int $id, ImageService $imageservice, ImageUpdateRequest $request): JsonResource
    {
        $image = Image::findOrFail($id);

        if ($request->user()->cannot('edit', $image)) {
            abort(403);
        }

        $imageSource = null;
        if ($request->hasFile('image')) {
            $imageSource = $request->file('image')->get();
        } elseif ($request->has('image_url')) {
            $imageSource = file_get_contents($request->input('image_url'));
        }

        $imageDTO = new ImageDTO(
            $imageSource,
            $request->input('copyright') ?? null,
            $request->has('sort') ? (int) $request->input('sort') : null,
        );

        $image = $imageservice->updateImage($id, $imageDTO, $request->user()->id);

        Cache::forget('image_thumb_' . $id);

        return new ImageResource($image);
    }

    #[OAT\Delete(path: '/images/{id}', tags: ['Images'], summary: 'Delete image', parameters: [
        new BAO\Parameters\DatabaseIdParameter(),
    ])]
    #[OAT\Response(response: 204, description: 'Successful response')]
    #[BAO\NotAuthorizedResponse]
    #[BAO\NotFoundResponse]
    public function delete(Request $request, int $id): Response
    {
        $image = Image::findOrFail($id);

        if ($request->user()->cannot('delete', $image)) {
            abort(403);
        }

        $image->delete();

        return new Response(null, 204);
    }

    #[OAT\Get(path: '/images/{id}/thumb', tags: ['Images'], summary: 'Get a thumbnail of an image', parameters: [
        new BAO\Parameters\DatabaseIdParameter(),
    ], security: [])]
    #[OAT\Response(response: 200, description: 'Successful response', content: [
        new OAT\MediaType(mediaType: 'image/jpg', schema: new OAT\Schema(type: 'string', format: 'binary')),
    ])]
    #[BAO\NotFoundResponse]
    public function thumb(int $id): Response
    {
        [$responseContent, $etag] = Cache::remember('image_thumb_' . $id, 1 * 24 * 60 * 60, function () use ($id) {
            $dbImage = Image::findOrFail($id);

            $responseContent = ImageThumbnailService::generateThumbnail($dbImage->getPath());
            if ($dbImage->updated_at) {
                $etag = md5($dbImage->id . '-' . $dbImage->updated_at->format('Y-m-d H:i:s'));
            } else {
                $etag = md5($dbImage->id . '-' . $dbImage->created_at->format('Y-m-d H:i:s'));
            }

            return [$responseContent, $etag];
        });

        $notModified = isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag;
        $statusCode = $notModified ? 304 : 200;

        return new Response($responseContent, $statusCode, [
            'Content-Type' => 'image/webp',
            'Content-Length' => strlen($responseContent),
            'Etag' => $etag
        ]);
    }
}
