<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\BusinessTag;
use App\Services\GoogleGeocoderService;
use App\Services\GooglePlaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class AutocompleteController
 * @package App\Http\Controllers\Api\v1
 */
class AutocompleteController extends Controller
{
    /**
     * @var int
     */
    const BUSINESS_TAGS_MAX_LIMIT = 10;

    /**
     * @OA\Get(
     *     path="/v1/autocomplete/maps/{engine}",
     *     operationId="getLocation",
     *     tags={"Location"},
     *     @OA\Parameter (
     *        name="engine",
     *        in="path",
     *
     *        @OA\Schema (
     *        type="array",
     *          @OA\Items(
     *             type="object"
     *           )
     *        )
     *     ),
     *
     *     @OA\Response(
     *     response="200",
     *      description="Successfully compiled",
     *      @OA\MediaType (
     *         mediaType="application/json",
     *         @OA\Schema (
     *          type="object",
     *         )
     *       )
     *    ),
     *
     *      @OA\Response(
     *         response=400,
     *         description="Error")
     *   )
     * )
     */

    /**
     * @param Request $request
     * @param string $engine
     * @return JsonResponse
     */
    public function maps(Request $request, string $engine): JsonResponse
    {
        if ($engine === 'places') {
            $service = new GooglePlaceService();
        } else {
            $service = new GoogleGeocoderService();
        }

        if ($type = $request->get('locationType')) {
            $service->type($type);
        }

        $search = $request->get('search');
        $results = $search ? $service->get($search) : [];

        return $this->buildSimpleJsonSuccess($results);
    }

    /**
     * @OA\Get(
     *     path="/v1/autocomplete/business-tags",
     *     operationId="getBusiness",
     *     tags={"Business"},
     *
     *     @OA\Parameter (
     *     name="limit",
     *     in="query",
     *
     *      @OA\Schema (
     *      type="array",
     *         @OA\Items(
     *          type="object"
     *        )
     *       )
     *     ),
     *
     *     @OA\Response(
     *       response="200",
     *       description="Success",
     *      @OA\MediaType (
     *         mediaType="application/json",
     *         @OA\Schema (
     *          type="object",
     *         )
     *       )
     *     ),
     *
     *     @OA\Response(
     *     response="400",
     *     description="Error"
     *     ),
     * )
     *
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function businessTags(Request $request): JsonResponse
    {
        $results = [];

        if ($search = trim($request->get('search', ''))) {
            $limit = $request->get('limit', 5);
            $limit = $limit <= self::BUSINESS_TAGS_MAX_LIMIT ? $limit : self::BUSINESS_TAGS_MAX_LIMIT;

            $results = BusinessTag::query()
                ->select(['id', 'name'])
                ->where('name', 'like', "%{$search}%")
                ->limit($limit)
                ->get();
        }

        return $this->buildSimpleJsonSuccess($results);
    }
}
