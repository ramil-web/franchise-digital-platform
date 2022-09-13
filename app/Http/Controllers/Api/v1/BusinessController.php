<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\CachedDirectoriesHelper;
use App\Helpers\DataHelper;
use App\Models\Business;
use App\Models\BusinessAttribute;
use App\Http\Controllers\Controller;
use App\Models\BusinessAttributeVersion;
use App\Models\BusinessClientStep;
use App\Models\BusinessFile;
use App\Models\BusinessTag;
use App\Models\Eloquent\HasManySync;
use App\Models\User;
use App\Models\UserAction;
use App\Models\UserBusiness;
use App\Models\UserPreferences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MediaEmbed\MediaEmbed;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class BusinessController
 * @package App\Http\Controllers\Api\v1
 */
class BusinessController extends Controller
{
    private $hostUrl;
    private $fieldValueTpl = '__value__';
    private $fieldSelfTpl = '__self__';
    private $fieldTakeFromAttributeArray = '__takeFromAttributeArray__';
    private $fieldTakeFromFileTypeArray = '__takeFromFileTypeArray__';
    private $fieldChangeValue = '__change_value__';
    private $multiValueDelimiter = ',';

    private $userID = 0;

    private $attributeAliasList = [];

    private $actionTypes = [
        'like' => 1,
        'compare' => 2,
    ];

    private $fileTypes = [
        'file_available' => 0,
        'file_item_7_available' => 7,
        'file_item_19_available' => 19,
        'file_item_franchisees_available' => 101,
    ];

    private $userFranchiseID = 0;

    /**
     * BusinessController constructor.
     */
    public function __construct()
    {
        // get current site host
        $this->hostUrl = config('app.url');

        // get current user id
        $user = Auth::user();
        if ($user == null) {
            $userID = 0;
        } else {
            $userID = $user->id;
        }
        $this->userID = $userID;

        // get user franchise ID
        if ($userID) {
            $franchise = UserBusiness::where('user_id', $this->userID)->first();
            if ($franchise) {
                $this->userFranchiseID = $franchise->franchise_id;
            }
        }

        // collect attribute types with ID and alias names
        $this->attributeAliasList = CachedDirectoriesHelper::businessAttributeTypes()->pluck('id', 'alias');
    }

    /**
     * @OA\Get(
     *   path="/v1/user-franchise",
     *   operationId="getFranchiseId",
     *   tags={"FranchiseId"},
     *   description="Get the user franchise ID.",
     *   @OA\Schema(
     *     type="object",
     *     @OA\Items(
     *       type="object",
     *      )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Successfully gets",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *       )
     *     )
     *   ),
     *   @OA\Response(response="400", description="Error"
     *   ),
     * )
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserFranchise(Request $request): JsonResponse
    {
        $franchise_id = 0;
        $userApproved = 0;
        $userID = $this->userID;

        /** @var User $user */
        $user = $request->user();

        if ($request->get('accountId') && $user->user_type === User::USER_TYPE_MASTER_ADMIN) {
            $franchise = UserBusiness::query()->where('user_id', $request->get('accountId'))->first()->toArray();
            $franchise_id = $franchise ? $franchise['franchise_id'] : 0;
            $userApproved = true;
        } else {
            if ($userID) {
                $franchise = UserBusiness::where('user_id', $userID)->first()->toArray();
                $franchise_id = $franchise ? $franchise['franchise_id'] : 0;
                $userApproved = Business::isUserApproved($userID);
            }
        }

        return response()->json(['franchise_id' => $franchise_id, 'user_approved' => $userApproved]);
    }

    /**
     * @OA\Get(
     *   path="/v1/tag/get",
     *   operationId="getTagList",
     *   tags={"Tag"},
     *   description="Get get tag list.",
     *   @OA\Schema(
     *     type="object",
     *     @OA\Items(
     *       type="object",
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Successfully gets",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *       )
     *     )
     *   ),
     *   @OA\Response(response="400", description="Error"
     *   ),
     * )
     * @return BusinessTag[]|Collection
     */
    public function getTagList()
    {
        $result = [];
        $tags = BusinessTag::query()
            ->orderBy('name')
            ->get();
        foreach ($tags as $tag) {
            $result[] = [
                'id' => $tag->id,
                'value' => $tag->id,
                'title' => $tag->name,
                'name' => $tag->name,
            ];
        }
        return $result;
    }

    /**
     * @OA\Get(
     *   path="/v1/industry/get",
     *   operationId="getIndustryList",
     *   tags={"Industries"},
     *   description="Get list of industries.",
     *   @OA\Schema(
     *     type="object",
     *     @OA\Items(
     *       type="object",
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Successfully gets",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *          type="object",
     *       )
     *     )
     *   ),
     *   @OA\Response(response="400", description="Error"
     *   ),
     * )
     */
    public function getIndustryList(): JsonResponse
    {
        $industryPostfix = ' Franchises';

        // get data for the API method from DB
        $businessIndustryList = CachedDirectoriesHelper::industries();

        // collect needed information (industry name)
        $businessArray = [];
        foreach ($businessIndustryList as $businessIndustry) {
            $businessArray[] = [
                'title' => $businessIndustry,
                'short_title' => str_replace($industryPostfix, '', $businessIndustry),
                'value' => $businessIndustry,
                'slug' => Str::slug($businessIndustry)
            ];
        }

        // return converted to JSON data
        return response()->json($businessArray);
    }

    /**
     * @OA\Get(
     *   path="/v1/industry-franchisees/get",
     *   operationId="getFranchiseesIndustries",
     *   tags={"FranchiseesIndustries"},
     *   description="Get list of industries with amount of franchisees.",
     *   @OA\Schema(
     *     type="object",
     *     @OA\Items(
     *       type="object",
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *       )
     *     )
     *   ),
     *   @OA\Response(response="400", description="Error"
     *   ),
     * )
     */
    public function getIndustryListFranchisees(): string
    {
        // get data for the API method from DB
        $businessIndustryList = CachedDirectoriesHelper::industries();

        // collect needed information (industry name)
        $amountOfFranchisees = 0;
        $businessIndustryName = '';
        $businessArray = [];
        $total = 0;
        $csvResult = '';
        foreach ($businessIndustryList as $businessIndustry) {
            $businessIndustryName = str_replace(',', '', $businessIndustry);

            $amountOfFranchiseesQuery = BusinessAttribute::query()
                ->where('business_attribute_id', '=', $this->attributeAliasList['industry'])
                ->where('is_actual', '=', '1')
                ->where('attribute_value_string', '=', $businessIndustry)
                ->groupBy('franchise_id')
                ->get('franchise_id');

            $amountOfFranchisees = count($amountOfFranchiseesQuery);
            $total += $amountOfFranchisees;

            $businessArray[] = [
                'industry' => $businessIndustryName,
                'amount_of_franchisees' => $amountOfFranchisees,
            ];

            $csvResult .= $businessIndustryName . ',' . $amountOfFranchisees . '<br>';

            $amountOfFranchisees = 0;
        }
        return $csvResult;
    }

    /**
     * @OA\Get(
     *   path="/v1/likes/get/{franchise_id}",
     *   operationId="getLikes",
     *   tags={"Likes"},
     *   description="Get likes per franchise by ID",
     *   @OA\Parameter(
     *     name="franchise_id",
     *     in="query",
     *     @OA\Schema(
     *       type="object",
     *       @OA\Items(
     *         type="object",
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Successfully gets likes",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *       )
     *     )
     *   ),
     *   @OA\Response(response="400", description="Error"
     *   ),
     * )
     * @param $franchiseID
     * @return JsonResponse
     */
    public function getLikesFranchise($franchiseID): JsonResponse
    {
        $actionType = $this->actionTypes['like'];

        $userAction = UserAction::where('franchise_id', $franchiseID)
            ->where('type', '=', $actionType)
            ->get();

        $result = ['count' => count($userAction)];

        return $this->buildSimpleJsonSuccess($result);
    }

    /**
     * @OA\Get(
     *   path="/v1/likes-all/get/{franchises_ids}",
     *   operationId="getAllLikes",
     *   tags={"AllLikes"},
     *   description="Get likes for all franchisees.",
     *   @OA\Parameter(
     *     name="franchises_ids",
     *     in="path",
     *     required=true,
     *     @OA\Schema(
     *       type="object",
     *       @OA\Items(
     *         type="object",
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *       )
     *     )
     *   ),
     *   @OA\Response(response="400", description="Error"
     *   ),
     * )
     * @param Request $request
     * @return JsonResponse
     */
    public function getLikesFranchises(Request $request): JsonResponse
    {
        $actionType = $this->actionTypes['like'];

        $userAction = DB::table('user_actions')
            ->selectRaw('franchise_id, count(*) as amount')
            ->whereRaw('type = ' . $actionType);

        if ($request['franchises_ids']) {
            $franchises_list = $request['franchises_ids'];
            $franchises_list = addslashes(trim($franchises_list, ','));
            $userAction = $userAction->whereRaw('franchise_id in (' . $franchises_list . ')');
        }

        $userAction = $userAction->groupByRaw('franchise_id');

        $userAction = $userAction->get();

        //dd($userAction);

        $result = $userAction;

        return $this->buildSimpleJsonSuccess($result);
    }

    /**
     * @OA\Get(
     *   path="/v1/business",
     *   operationId="getAllLikes",
     *   tags={"AllLikes"},
     *   description="Get list of franchisees.",
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *      )
     *     )
     *   ),
     *   @OA\Response(response="400", description="Error"
     *   ),
     * )
     * @param Request $request
     * @return JsonResponse
     * @throws NotFoundHttpException
     */
    public function index(Request $request): JsonResponse
    {
        // get data for the API method from DB
        $business = $this->apiMainRequest('index');

        // pagination limit
        $perPage = 10;

        // paginate request
        $business = $business->paginate($perPage);

        // collect business info
        $businessArray = $this->collectBusinessInfo($business, $request);

        // return converted to JSON data
        return response()->json($businessArray);
    }

    /**
     * generate and send request by method type
     * @param $type
     * @param Request|null $request
     * @param null $args
     * @return Builder
     */
    private function apiMainRequest($type, $args = null, Request $request = null)
    {
        $result = [];

        // list of franchisees that are liked/in compare
        if ($type == 'getUserAction') {
            /** @var User $user */
            $user = $request->user();
            $userID = $user && $user->isMasterAdmin() ? $request->get('accountId', $this->userID) : $this->userID;
            $action = $args['action'];

            $result = UserAction::query()
                ->where('user_id', $userID)
                ->where('type', $this->actionTypes[$action]);
        }

        // one franchise
        if ($type == 'show') {
            $id = $args['id'];
            $isOwner = $args['isOwner'];
            #$versionID = $args['id'];

            $query = Business::query()->where('parent_id', '<>', '0');
            !is_numeric($id) ? $query->where('slug', $id) : $query->where('franchise_id', $id);

            if (!$isOwner) {
                $query->where('status', 0);
            }

            $result = $query;
        }

        // list of franchisees with filtration
        if ($type == 'search' || $type == 'index') {
            // mini fix when no requests
            $requestList = $request ? $request->toArray() : [];

            // sort field array
            $sortFieldArray = [
                'business_name' => 'attribute_value_string',
                'number_of_locations' => 'attribute_value_string + 0',
                'membership' => 'attribute_value_string',
                'rating' => 'rating_value',
                'investment' => 'attribute_value_numeric_low',
                'average_revenue' => 'attribute_value_string + 0',
                'franchise_fee' => 'attribute_value_numeric_low',
            ];

            // sort direction array
            $sortDirectionArray = ['asc', 'desc'];

            // list of fields requested to sort
            $requestSortList = [];

            // sort by fields that are apply by used
            $requestSort = [];

            // collect fields to sort by
            if (isset($request['sort'])) {
                $sort = $request['sort'];

                if (strpos($sort, ',') === false) {
                    $requestSortList[] = $sort;
                } else {
                    foreach (explode(',', $sort) as $item => $value) {
                        $requestSortList[] = $value;
                    }
                }

                foreach ($requestSortList as $requestItem => $requestValue) {
                    $requestValueTrim = trim($requestValue, '-');

                    if (key_exists($requestValueTrim, $sortFieldArray)) {
                        $requestSort[$requestValueTrim] = $requestValue;
                    }
                }
            }

            // list of db query search types
            $queryTypes = $this->getQuerySearchTypes();

            if (Arr::get($requestList, 'business_name')) {
                if (Arr::get($requestList, 'first_letter', false)) {
                    Arr::set(
                        $queryTypes,
                        'businessAttributesStringLike.fields.attribute_value_string.update_value_condition',
                        "^([[:digit:]|[:space:]])*({$this->fieldValueTpl}){1}"
                    );
                    Arr::set(
                        $queryTypes,
                        'businessAttributesStringLike.fields.attribute_value_string.operator',
                        'rlike'
                    );
                } elseif ((bool)Arr::get($requestList, 'strict_name_search', false)) {
                    Arr::set(
                        $queryTypes,
                        'businessAttributesStringLike.fields.attribute_value_string.update_value_condition',
                        "{$this->fieldValueTpl}%"
                    );
                }
            }

            // connect attributes names to attributes query types
            $queryTypesFields = [
                // table businesses
                'businessesSelf' => ['franchise_id',],
                'businessesSelfTrue' => [],
                'businessesSelfLike' => [],
                'businessesSelfMore' => [],
                // table business_state
                'businessesStatesID' => ['allowed_states_id',],
                // table business_tags
                'businessesTagsID' => ['allowed_tags_id',],
                // table business_attributes
                'businessAttributesSelfMore' => ['franchising_since', 'established'],
                'businessAttributesSelfTrue' => ['video'],
                'businessAttributesString' => ['industry', 'file_name', 'membership', 'company_page_published'],
                'businessAttributesStringLike' => ['business_name',],
                'businessAttributesRange' => ['investment', 'franchise_fee',],
                'businessAttributesStringRange' => ['number_of_locations', 'average_revenue',],
                // table business_rating
                'businessesRating' => ['rating',],
                // table business_files
                'businessFilesAvailable' => [
                    'file_available',
                    'file_item_7_available',
                    'file_item_19_available',
                    'file_item_franchises_available'
                ],
            ];

            // value rules to convert
            $valueRules = [
                'false' => 0,
                'no' => 0,
                'yes' => 1,
                'true' => 1,
            ];

            // skip values list
            $valueToSkip = [
                '',
                0,
                null,
                false,
                '0',
                'null',
                'false',
                'no',
            ];

            $query = Business::query()
                ->where('businesses.parent_id', '<>', 0)
                ->where('businesses.status', '<>', 1);

            // add sort condition
            foreach ($requestSort as $requestSortItemTrim => $requestSortItem) {
                if (!key_exists($requestSortItemTrim, $requestList)) {
                    $requestList[$requestSortItemTrim] = '';
                }
            }

            // sort alias
            $sortAlias = [];

            // go list of received attributes for search/filter, e.g. franchise_fee = [1000,20 000]
            foreach ($requestList as $requestItemKey => $requestItemValue) {
                // skip if attribute value is empty
                $skip = false;
                foreach ($valueToSkip as $skipValue) {
                    if ($requestItemValue === $skipValue) {
                        $skip = true;
                    }
                }

                if ($skip == true && !key_exists($requestItemKey, $requestSort)) {
                    continue;
                }

                // go list of query types to create SQL conditions JOIN and WHERE based on attribute name, $queryTypesFields and $queryTypesFields
                foreach ($queryTypesFields as $queryType => $queryTypeArray) {
                    // check if can use received attribute
                    if (in_array($requestItemKey, $queryTypeArray)) {
                        $join = [
                            'table' => $queryTypes[$queryType]['table'],
                            'alias' => $queryTypes[$queryType]['table'] . '_' . $requestItemKey
                        ];
                        $joinWhere = [];

                        if (isset($queryTypes[$queryType]['fields']['business_attribute_id'])) {
                            if ($queryTypes[$queryType]['fields']['business_attribute_id']['update_value_condition_extra'] == $this->fieldTakeFromAttributeArray) {
                                $attributeID = $this->attributeAliasList[$requestItemKey];
                                $joinWhere[] = [
                                    'column' => $join['alias'] . '.business_attribute_id',
                                    'operator' => '=',
                                    'value' => $attributeID
                                ];
                                $joinWhere[] = [
                                    'column' => $join['alias'] . '.is_actual',
                                    'operator' => '=',
                                    'value' => 1
                                ];
                            }
                        }

                        $query = $query->join(
                            $join['table'] . ' as ' . $join['alias'],
                            function (JoinClause $joinClause) use ($join, $joinWhere) {
                                $joinClause->on(
                                    'businesses.franchise_id',
                                    '=',
                                    $join['alias'] . '.franchise_id'
                                );

                                if ($joinWhere) {
                                    foreach ($joinWhere as $where) {
                                        $joinClause->where($where['column'], $where['operator'], $where['value']);
                                    }
                                }
                            }
                        );

                        // if the key will be used for sort, let's save alias for it
                        if (key_exists($requestItemKey, $requestSort)) {
                            $sortAlias[$requestItemKey] = $join['alias'];
                        }

                        // skip if attribute value is empty
                        if ($skip == true) {
                            continue;
                        }

                        foreach ($queryTypes[$queryType]['fields'] as $fieldName => $fieldItems) {
                            // search by field name = attribute name
                            if ($fieldName == $this->fieldSelfTpl) {
                                $fieldName = $requestItemKey;
                            }

                            // when we need to apply couple conditions to one field name, just add with same name and __clone postfix
                            $fieldName = str_replace('__clone', '', $fieldName);

                            // set value
                            $value = $requestItemValue;

                            // apply extra conditions
                            if ($fieldItems['update_value_condition_extra'] != null) {
                                // take value from array
                                if ($fieldItems['update_value_condition_extra'] == 'index_array') {
                                    // skip empty for values
                                    $indexArray = $fieldItems['index_array'];
                                    $value = $value[$indexArray] ?? '';
                                }

                                // take ID of attribute for sql query by attribute name
                                if ($fieldItems['update_value_condition_extra'] == $this->fieldTakeFromAttributeArray) {
                                    $value = $this->attributeAliasList[$requestItemKey];
                                }

                                // take ID of file type for sql query by file type name
                                if ($fieldItems['update_value_condition_extra'] == $this->fieldTakeFromFileTypeArray) {
                                    $value = $this->fileTypes[$requestItemKey];
                                }

                                // change value to default for a field
                                if ($fieldItems['update_value_condition_extra'] == $this->fieldChangeValue) {
                                    $value = $fieldItems['update_value_condition'];
                                }
                            }

                            // apply value rules
                            foreach ($valueRules as $valueRuleFrom => $valueRuleTo) {
                                $value = str_replace($valueRuleFrom, $valueRuleTo, $value);
                            }

                            // skip empty for values
                            if ($value == '') {
                                continue;
                            }

                            // check quote and delete double escaping
                            $value = str_replace(['\\\\', '\\\''], ['\\', '\''], addslashes($value));

                            // apply conditions
                            if ($fieldItems['update_value_condition'] != null) {
                                $value = str_replace(
                                    $this->fieldValueTpl,
                                    $value,
                                    $fieldItems['update_value_condition']
                                );
                            }

                            /*if($fieldItems['update_value_condition'] == $this->fieldSelfTpl){
                              $value =
                            }*/

                            $castToInt = false;
                            // temporary fix for search in string values like in numeric values
                            if ($queryType == 'businessAttributesStringRange') {
                                $castToInt = true;
                            }

                            // separate value to values if it's a multi-value (with __or__ delimiter)
                            $valueOrList = [];
                            if (strpos($value, $this->multiValueDelimiter) !== false) {
                                // additional value conditions
                                $valuePrefix = '';
                                $valuePostfix = '';
                                $valueClear = '';

                                // saving % for correct like behavior
                                if (substr_count($value, '%') == 2) {
                                    $valuePrefix = '%';
                                    $valuePostfix = '%';
                                    $valueClear = '%';
                                }

                                // save separated values to array
                                foreach (explode($this->multiValueDelimiter, $value) as $valueItem) {
                                    $valueOrList[] = $valuePrefix . trim($valueItem, $valueClear) . $valuePostfix;
                                }
                            } else {
                                $valueOrList[] = $value;
                            }

                            if ($fieldItems['operator'] === 'in') {
                                if ($castToInt && !in_array($fieldName, ['business_attribute_id', 'is_actual'])) {
                                    list($queryField, $queryValue) = DataHelper::castToInt(
                                        "{$join['alias']}.{$fieldName}",
                                        $valueOrList
                                    );
                                } else {
                                    $queryField = "{$join['alias']}.{$fieldName}";
                                    $queryValue = $valueOrList;
                                }

                                $query->whereIn(DB::raw($queryField), $queryValue);
                            } elseif (isset($fieldItems['not_empty'])) {
                                $queryField = "{$join['alias']}.{$fieldName}";

                                $query->where($queryField, $fieldItems['operator'], '')
                                    ->whereNotNull("{$join['alias']}.{$fieldName}");
                            } else {
                                $whereOr = [];
                                foreach ($valueOrList as $valueOrListItem) {
                                    if ($castToInt && !in_array($fieldName, ['business_attribute_id', 'is_actual'])) {
                                        list($queryField, $queryValue) = DataHelper::castToInt(
                                            "{$join['alias']}.{$fieldName}",
                                            $valueOrListItem
                                        );
                                    } else {
                                        $queryField = "{$join['alias']}.{$fieldName}";
                                        $queryValue = $valueOrListItem;
                                    }

                                    $whereOr[] = [
                                        'column' => $queryField,
                                        'operator' => $fieldItems['operator'],
                                        'value' => $queryValue
                                    ];
                                }

                                $query->where(
                                    function ($queryWhere) use ($whereOr) {
                                        foreach ($whereOr as $or) {
                                            /** @var Builder $queryWhere */
                                            $queryWhere->orWhere(DB::raw($or['column']), $or['operator'], $or['value']);
                                        }
                                    }
                                );
                            }
                        }
                    }
                }
            }

            // extra select for sorting
            $sortSelect = '';
            $sortGroup = '';
            if (count($requestSort) > 0) {
                foreach ($requestSort as $requestSortFieldTrim => $requestSortField) {
                    $sortSelect .= ', max(' . $sortAlias[$requestSortFieldTrim] . '.' . $sortFieldArray[$requestSortFieldTrim] . ')';
                }
            }

            // main request
            $query->selectRaw('businesses.franchise_id' . $sortSelect)
                ->groupByRaw('businesses.franchise_id' . $sortGroup);

            $orderBy = '';

            if (count($requestSort) > 0) {
                foreach ($requestSort as $requestSortFieldTrim => $requestSortField) {
                    $orderBy .= ' max(' . $sortAlias[$requestSortFieldTrim] . '.' . $sortFieldArray[$requestSortFieldTrim] . ')';
                    $orderBy .= $requestSortField[0] === '-' ? ' desc' : ' asc,';
                }

                $orderBy = trim($orderBy, ',');
                $query->orderByRaw($orderBy);
            }

            $result = $query;
        }

        return $result;
    }

    /**
     * List of query types for search API attribute filter
     *
     * @return array
     */
    private function getQuerySearchTypes(): array
    {
        $queryTypes = [];

        $queryTypes['businessesRating'] = [
            'table' => 'business_ratings',
            'fields' => [
                'rating_value' => [
                    'operator' => '=',
                    'update_value_condition' => null, // no updating
                    'update_value_condition_extra' => null,
                ]
            ]
        ];

        $queryTypes['businessAttributesSelfMore'] = [
            'table' => 'business_attributes',
            'fields' => [
                'attribute_value_string' => [
                    'operator' => '>',
                    'update_value_condition' => null, // no updating
                    'update_value_condition_extra' => null, // no extra updating
                ],
                'business_attribute_id' => [
                    'operator' => '=',
                    'update_value_condition' => null,
                    'update_value_condition_extra' => $this->fieldTakeFromAttributeArray,
                    // take ID of attribute by name
                ],
                'is_actual' => [
                    'operator' => '=',
                    'update_value_condition' => 1,
                    'update_value_condition_extra' => $this->fieldChangeValue,
                ],
            ]
        ];

        $queryTypes['businessAttributesSelfTrue'] = [
            'table' => 'business_attributes',
            'fields' => [
                'attribute_value_string' => [
                    'operator' => '<>',
                    'update_value_condition' => null, // no updating
                    'update_value_condition_extra' => null, // no extra updating
                    'not_empty' => true, // no extra updating
                ],
                'business_attribute_id' => [
                    'operator' => '=',
                    'update_value_condition' => null,
                    'update_value_condition_extra' => $this->fieldTakeFromAttributeArray,
                    // take ID of attribute by name
                ],
                'is_actual' => [
                    'operator' => '=',
                    'update_value_condition' => 1,
                    'update_value_condition_extra' => $this->fieldChangeValue,
                ],
            ]
        ];

        $queryTypes['businessAttributesString'] = [
            'table' => 'business_attributes',
            'fields' => [
                'attribute_value_string' => [
                    'operator' => '=',
                    'update_value_condition' => null, // no updating
                    'update_value_condition_extra' => null, // no extra updating
                ],
                'business_attribute_id' => [
                    'operator' => '=',
                    'update_value_condition' => null,
                    'update_value_condition_extra' => $this->fieldTakeFromAttributeArray,
                    // take ID of attribute by name
                ],
                'is_actual' => [
                    'operator' => '=',
                    'update_value_condition' => 1,
                    'update_value_condition_extra' => $this->fieldChangeValue,
                ],
            ]
        ];

        $queryTypes['businessAttributesStringLike'] = [
            'table' => 'business_attributes',
            'fields' => [
                'attribute_value_string' => [
                    'operator' => 'like',
                    'update_value_condition' => "%{$this->fieldValueTpl}%",
                    'update_value_condition_extra' => null,
                ],
                'business_attribute_id' => [
                    'operator' => '=',
                    'update_value_condition' => null,
                    'update_value_condition_extra' => $this->fieldTakeFromAttributeArray,
                    // take ID of attribute by name
                ],
                'is_actual' => [
                    'operator' => '=',
                    'update_value_condition' => 1,
                    'update_value_condition_extra' => $this->fieldChangeValue,
                ],
            ]
        ];

        //
        $queryTypes['businessAttributesStringLikeBegin'] = [
            'table' => 'business_attributes',
            'fields' => [
                'attribute_value_string' => [
                    'operator' => 'like',
                    'update_value_condition' => "{$this->fieldValueTpl}%",
                    'update_value_condition_extra' => null,
                ],
                'business_attribute_id' => [
                    'operator' => '=',
                    'update_value_condition' => null,
                    'update_value_condition_extra' => $this->fieldTakeFromAttributeArray,
                    // take ID of attribute by name
                ],
                'is_actual' => [
                    'operator' => '=',
                    'update_value_condition' => 1,
                    'update_value_condition_extra' => $this->fieldChangeValue,
                ],
            ]
        ];

        //
        $queryTypes['businessesSelf'] = [
            'table' => 'businesses',
            'fields' => [
                $this->fieldSelfTpl => [
                    'operator' => '=',
                    'update_value_condition' => null, // no updating
                    'update_value_condition_extra' => null,
                ]
            ]
        ];

        //
        $queryTypes['businessesSelfTrue'] = [
            'table' => 'businesses',
            'fields' => [
                $this->fieldSelfTpl => [
                    'operator' => '<>',
                    'update_value_condition' => '', // no updating
                    'update_value_condition_extra' => null,
                ]
            ]
        ];

        //
        $queryTypes['businessesSelfLike'] = [
            'table' => 'businesses',
            'fields' => [
                $this->fieldSelfTpl => [
                    'operator' => 'like',
                    'update_value_condition' => '%' . $this->fieldValueTpl . '%', // update to %some_value%
                    'update_value_condition_extra' => null,
                ],
            ]
        ];

        //
        $queryTypes['businessesSelfMore'] = [
            'table' => 'businesses',
            'fields' => [
                $this->fieldSelfTpl => [
                    'operator' => '>',
                    'update_value_condition' => '' . $this->fieldValueTpl . '-00-00', // update to %some_value%
                    'update_value_condition_extra' => null,
                ],
            ]
        ];

        //
        $queryTypes['businessesStatesID'] = [
            'table' => 'business_state',
            'fields' => [
                'state_id' => [
                    'operator' => 'in',
                    'update_value_condition' => null,
                    'update_value_condition_extra' => null,
                ],
            ]
        ];

        //
        $queryTypes['businessesTagsID'] = [
            'table' => 'business_tag',
            'fields' => [
                'tag_id' => [
                    'operator' => 'in',
                    'update_value_condition' => null,
                    'update_value_condition_extra' => null,
                ],
            ]
        ];

        //
        $queryTypes['businessAttributesRange'] = [
            'table' => 'business_attributes',
            'fields' => [
                'attribute_value_numeric_low' => [
                    'operator' => '>=',
                    'update_value_condition' => null, // no updating
                    'update_value_condition_extra' => 'index_array', //
                    'index_array' => 0,
                ],
                'attribute_value_numeric_low__clone' => [
                    'operator' => '<=',
                    'update_value_condition' => null, // no updating
                    'update_value_condition_extra' => 'index_array', //
                    'index_array' => 1,
                ],
                /*'attribute_value_numeric_high' => [
                    'operator' => '<=',
                    'update_value_condition' => null, // no updating
                    'update_value_condition_extra' => 'index_array', //
                    'index_array' => 1,
                ],*/
                'business_attribute_id' => [
                    'operator' => '=',
                    'update_value_condition' => null,
                    'update_value_condition_extra' => $this->fieldTakeFromAttributeArray,
                    // take ID of attribute by name
                ],
                'is_actual' => [
                    'operator' => '=',
                    'update_value_condition' => 1,
                    'update_value_condition_extra' => $this->fieldChangeValue,
                ],
            ]
        ];

        //
        $queryTypes['businessAttributesStringRange'] = [
            'table' => 'business_attributes',
            'fields' => [
                'attribute_value_string' => [
                    'operator' => '>=',
                    'update_value_condition' => null, // no updating
                    'update_value_condition_extra' => 'index_array', //
                    'index_array' => 0,
                ],
                'attribute_value_string__clone' => [
                    'operator' => '<=',
                    'update_value_condition' => null, // no updating
                    'update_value_condition_extra' => 'index_array', //
                    'index_array' => 1,
                ],
                'business_attribute_id' => [
                    'operator' => '=',
                    'update_value_condition' => null,
                    'update_value_condition_extra' => $this->fieldTakeFromAttributeArray,
                    // take ID of attribute by name
                ],
                'is_actual' => [
                    'operator' => '=',
                    'update_value_condition' => 1,
                    'update_value_condition_extra' => $this->fieldChangeValue,
                ],
            ]
        ];

        //
        $queryTypes['businessFilesAvailable'] = [
            'table' => 'business_files',
            'fields' => [
                'is_actual' => [
                    'operator' => '=',
                    'update_value_condition' => null, // no updating
                    'update_value_condition_extra' => null,
                ],
                'item_type' => [
                    'operator' => '=',
                    'update_value_condition' => null,
                    'update_value_condition_extra' => $this->fieldTakeFromFileTypeArray, // take ID of attribute by name
                ],
            ]
        ];

        return $queryTypes;
    }

    /**
     * collect full info about franchisees from the list
     * return prepared to json convert array
     * @param Business[]|Collection|Paginator $business
     * @param Request|null $request
     * @param bool $isOwner
     * @param int $userId
     * @param int $showVersionID
     * @return array
     */
    private function collectBusinessInfo(
        $business,
        Request $request,
        $isOwner = false,
        $userId = null,
        $showVersionID = null
    ): array {
        // list of franchisees with full data
        $businessItemList = [];

        // counter to show number of franchise
        $counter = 0;
        $onlyActual = true;
        $items = collect($business->items());
        $franchiseIds = $items->pluck('franchise_id');

        $userActions = [];
        $userFlowStatuses = [];
        $totalActions = UserAction::getCountUserActions($franchiseIds);

        /** @var User $user */
        $user = $request->user();
        if ($user && $user->isFranchisee()) {
            $userActions = UserAction::getCountUserActions($franchiseIds, $user->id);
            $userFlowStatuses = BusinessClientStep::getFlowStatuses($franchiseIds, $user->id);
        }

        $attributesVersionId = 'latest';
        if (count($items) === 1 && $showVersionID) {
            $attributesVersionId = $showVersionID;
        }

        // collect full info about each franchise in the list
        foreach ($items as $businessItemID) {
            $counter++;
            $franchiseID = $businessItemID->franchise_id;
            $versionID = null;
            $version = null;

            if (!$attributesVersionId) {
                $attributesVersionId = 'latest';
            }

            if ($isOwner) {
                $onlyActual = false;

                if (!$userId) {
                    $userId = $this->userID;
                }

                if ($attributesVersionId === 'latest') {
                    $version = BusinessAttributeVersion::getLastVersion($franchiseID, $userId);
                    $versionID = $version->version_id;
                } elseif ($user->isFranchisor() || $user->isMasterAdmin()) {
                    $query = BusinessAttributeVersion::query()
                        ->select(['version_id'])
                        ->where('franchise_id', $franchiseID);

                    if ($user->isFranchisor()) {
                        $query->where('user_id', $user->id);
                    }

                    $version = $query->findOrFail($attributesVersionId);
                    $versionID = $version->version_id;
                }
            } else {
                if ($attributesVersionId == 'latest') {
                    $versionID = DB::table('business_attributes')
                        ->where('franchise_id', $franchiseID)
                        ->where('is_actual', 1)
                        ->max('version_id');
                } else {
                    throw new NotFoundHttpException('Version Not Found');
                }
            }

            /** @var Business $businessItem */
            $businessItem = Business::query()
                ->with(
                    [
                        'files' => function (HasMany $query) use ($versionID) {
                            $query->select(
                                [
                                    'id',
                                    'franchise_id',
                                    'filename',
                                    'updated_at',
                                    'item_type',
                                    'is_actual'
                                ]
                            )->where('version_id', $versionID)
                                ->where('filename', '!=', '')
                                ->whereNotNull('filename');
                        },
                        'states' => function (BelongsToMany $query) use ($versionID) {
                            $query
                                ->select(['states.id', 'states.code', 'states.name'])
                                ->wherePivot('version_id', $versionID);
                        },
                        'tags' => function (BelongsToMany $query) use ($versionID) {
                            $query->select(['business_tags.id', 'business_tags.name'])
                                ->wherePivot('version_id', $versionID);
                        },
                        'founders' => function (HasManySync $query) use ($versionID) {
                            $query->select(
                                ['id', 'first_name', 'middle_name', 'last_name', 'franchise_id', 'description']
                            )
                                ->with(
                                    [
                                        'avatar' => function (MorphOne $query) {
                                            $query->select(['id', 'avatarable_id']);
                                        }
                                    ]
                                )
                                ->where('version_id', $versionID);
                        },
                        'externalRatings' => function (HasManySync $query) use ($versionID) {
                            $query->where('version_id', $versionID);
                        }
                    ]
                )
                ->findOrFail($franchiseID);

            // franchisees with parent_id = 0 are businesses, we dont show them on the site
            if ($businessItem->parent_id == 0) {
                continue;
            }

            // get list of attributes
            $attributeListQuery = DB::table('business_attributes')
                ->join(
                    'business_attribute_types',
                    'business_attributes.business_attribute_id',
                    '=',
                    'business_attribute_types.attribute_type_id'
                )
                ->select('business_attribute_types.*', 'business_attributes.*')
                ->where('business_attributes.franchise_id', $franchiseID)
                ->where('business_attributes.version_id', $versionID)
                ->whereIn('business_attribute_id', $this->attributeAliasList);

            if ($onlyActual) {
                $attributeListQuery->where('is_actual', 1);
            }

            $attributeList = $attributeListQuery->get()
                ->toArray();

            // list of attributes showing for franchisees
            $attributeListArray = [
                'parent_id' => $businessItem->parent_id,
                'franchise_id' => $franchiseID,
                'slug' => $businessItem->slug,
                'number' => ($business->currentPage() - 1) * $business->perPage() + $counter,
                'like' => $userActions['like'][$franchiseID] ?? 0,
                'in_comparison' => $userActions['compare'][$franchiseID] ?? 0,
                'flow_status' => $userFlowStatuses[$franchiseID] ?? BusinessClientStep::FLOW_STATUS_NOT_STARTED,
                'total_like' => $totalActions['like'][$franchiseID] ?? 0,
                'total_in_comparison' => $totalActions['compare'][$franchiseID] ?? 0,
                'states' => $businessItem->states,
                'founders' => $businessItem->founders,
                'tags' => $businessItem->tags,
                'external_ratings' => $businessItem->groupRatingsByCategory()
            ];

            // check for attribute duplicates
            // todo: check if it's still need to check
            $prevID = 0;
            foreach ($attributeList as $attribute) {
                if ($attribute->attribute_type_id != $prevID) {
                    $attributeValueString = $attribute->attribute_value_string;
                    $attributeListArray[$attribute->attribute_name_alias] = [
                        'business_attribute_id' => $attribute->business_attribute_id,
                        'attribute_name_alias' => $attribute->attribute_name_alias,
                        'attribute_name_user_friendly' => $attribute->attribute_name_user_friendly,
                        'attribute_description_friendly' => $attribute->attribute_description_friendly,
                        'attribute_value_string' => $attributeValueString,
                        'attribute_value_numeric_low' => (string)(int)$attribute->attribute_value_numeric_low,
                        'attribute_value_numeric_high' => (string)(int)$attribute->attribute_value_numeric_high,
                        'attribute_value_numeric_midpoint' => (string)(int)$attribute->attribute_value_numeric_midpoint,
                    ];
                    $prevID = $attribute->attribute_type_id;
                }
            }

            // add lost attributes to make frontend stable
            foreach ($this->attributeAliasList as $attribute => $attributeID) {
                if (!key_exists($attribute, $attributeListArray)) {
                    $attributeListArray[$attribute] = [
                        'business_attribute_id' => $attributeID,
                        'attribute_value_string' => '',
                        'attribute_name_user_friendly' => $attribute,
                        'attribute_value_numeric_low' => 0,
                        'attribute_value_numeric_high' => 0,
                        'attribute_value_numeric_midpoint' => 0,
                    ];
                }
            }

            /* todo rewrite
             *  fake attributes { */
            if (!key_exists('number_of_locations', $attributeListArray)) {
                $attributeListArray['number_of_locations'] = [
                    'attribute_value_string' => '0',
                    'attribute_value_numeric_low' => 0,
                    'attribute_value_numeric_high' => 0,
                    'attribute_value_numeric_midpoint' => 0,
                ];
            }
            if (!key_exists('landing_phone', $attributeListArray) ||
                $attributeListArray['landing_phone']['attribute_value_string'] == '') {
                $attributeListArray['landing_phone'] = [
                    'attribute_value_string' => BusinessAttribute::getDefaultLandingPhone(),
                    'attribute_value_numeric_low' => 0,
                    'attribute_value_numeric_high' => 0,
                    'attribute_value_numeric_midpoint' => 0,
                ];
            }

            $franchisePermalink =
                $attributeListArray['public_website_business_permalink']['attribute_value_string']
                ?? '';
            $attributeListArray['public_website_business_permalink'] = [
                'attribute_value_string' => strpos($franchisePermalink, 'http') === 0 || strlen(
                    $franchisePermalink
                ) == 0 ? '' . $franchisePermalink : 'https://' . $franchisePermalink,
                'attribute_value_numeric_low' => 0,
                'attribute_value_numeric_high' => 0,
                'attribute_value_numeric_midpoint' => 0,
            ];

            $attributeListArray['public_website_business_permalink_title'] = [
                'attribute_value_string' => str_replace(
                    ['https://', 'http://', 'www.'],
                    ['', '', '', ''],
                    $franchisePermalink
                ),
                'attribute_value_numeric_low' => 0,
                'attribute_value_numeric_high' => 0,
                'attribute_value_numeric_midpoint' => 0,
            ];

            $attributeListArray['slug_industry'] = [
                'attribute_value_string' => Str::slug($attributeListArray['industry']['attribute_value_string'], '-'),
                'attribute_value_numeric_low' => 0,
                'attribute_value_numeric_high' => 0,
                'attribute_value_numeric_midpoint' => 0,
            ];

            // convert url to html iframe embed
            $attributeListArray['video_embed']['attribute_value_string'] = '';
            if (key_exists('video', $attributeListArray)) {
                $videoUrl = $attributeListArray['video']['attribute_value_string'];
                $mediaEmbed = new MediaEmbed();
                $mediaObject = $mediaEmbed->parseUrl($videoUrl);
                if ($mediaObject) {
                    $mediaObject->setAttribute(
                        [
                            'width' => '100%',
                            'height' => '420px'
                        ]
                    );
                    $attributeListArray['video_embed']['attribute_value_string'] = $mediaObject->getEmbedCode();
                }
            }

            $fileTypes = BusinessFile::FILE_TYPES;
            /** @var Collection|BusinessFile[] $files */
            $files = $businessItem->files->keyBy('item_type');

            /** @var BusinessFile $logoFile */
            $logoFile = $files->firstWhere('item_type', $fileTypes['logo']);
            if ($logoFile) {
                $files->forget($fileTypes['logo']);
                $logo = [
                    'id' => $logoFile->id
                ];
            } else {
                $logo = [
                    'id' => null
                ];
            }

            /** @var BusinessFile $logoFile */
            $logoLandingFile = $files->firstWhere('item_type', $fileTypes['logo_landing']);
            if ($logoLandingFile) {
                $files->forget($fileTypes['logo_landing']);
                $logoLanding = [
                    'id' => $logoLandingFile->id
                ];
            } else {
                $logoLanding = [
                    'id' => null
                ];
            }

            foreach (BusinessFile::LOGO_SIZES as $size) {
                $logo[$size['name']] = $logoFile
                    ? $logoFile->downloadImageUrl($size['name'], $versionID)
                    : BusinessFile::downloadDefaultImageUrl($size['name']);
            }

            foreach (BusinessFile::LOGO_SIZES as $size) {
                $logoLanding[$size['name']] = $logoLandingFile
                    ? $logoLandingFile->downloadImageUrl($size['name'], $versionID)
                    : BusinessFile::downloadDefaultImageUrl($size['name']);
            }

            $files->map(
                function ($item) use ($onlyActual) {
                    /** @var BusinessFile $item */
                    $item->url = $item->downloadUrl($onlyActual);
                    unset($item['franchise_id'], $item['item_type'], $item['is_actual']);

                    return $item;
                }
            );

            $attributeListArray['logo'] = $logo;
            $attributeListArray['logo_landing'] = $logoLanding;
            $attributeListArray['files'] = [
                'fdd' => $files[$fileTypes['fdd']] ?? null,
                'item_7' => $files[$fileTypes['item_7']] ?? null,
                'item_19' => $files[$fileTypes['item_19']] ?? null,
                'franchisees' => $files[$fileTypes['franchisees']] ?? null,
                'presentation' => $files[$fileTypes['presentation']] ?? null,
            ];

            /* todo rewrite
            fake attributes } */
            $businessItemList[] = array_merge(
                $attributeListArray,
                [
                    'version_id' => $versionID,
                    'type' => 'franchise'
                ]
            );
        }

        // collect data
        return [
            'data' => $businessItemList,
            'first' => $business->url(1),
            'last' => $business->url($business->lastPage()),
            'prev' => $business->previousPageUrl(),
            'next' => $business->nextPageUrl(),
            'current_page' => $business->currentPage(),
            'from' => ($business->currentPage() - 1) * $business->perPage() + 1,
            'last_page' => $business->lastPage(),
            'path' => $business->path(),
            'per_page' => $business->perPage(),
            'to' => ($business->currentPage() - 1) * $business->perPage() + count($business->items()),
            'total' => $business->total(),
        ];
    }

    /**
     * @OA\Get(
     *   path="/v1/info/get/{action_alias}",
     *   operationId="getUserAction",
     *   tags={"UserAction"},
     *   description="Get User Action like preferenses.",
     *   @OA\Parameter(
     *     name="action_alias",
     *     in="query",
     *     @OA\Schema(
     *       type="object",
     *       @OA\Items(
     *           type="object",
     *        )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *       )
     *     )
     *   ),
     *   @OA\Response(response="400", description="Error"
     *   ),
     * )
     * @param Request $request
     * @param string $action
     * @return JsonResponse
     * @throws NotFoundHttpException
     */
    public function getUserAction(Request $request, string $action): JsonResponse
    {
        // get data for the API method from DB
        $business = $this->apiMainRequest('getUserAction', ['action' => $action], $request);

        // pagination limit
        $perPage = 50;

        // paginate request
        $business = $business->paginate($perPage);

        // collect business info
        $businessArray = $this->collectBusinessInfo($business, $request);

        // return converted to JSON data
        return response()->json($businessArray);
    }

    // TODO rewrite

    /**
     * @OA\Get(
     *   path="/v1/business/{franchiseId}/data/{versionId}",
     *   operationId="getIndividualFranchise",
     *   tags={"IndividualFranchise"},
     *   description="Use for individual franchise page.",
     *   @OA\Parameter(
     *     name="franchiseId}",
     *     in="query",
     *     @OA\Schema(
     *       type="object",
     *       @OA\Items(
     *         type="object",
     *       )
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="versionId",
     *     in="query",
     *     @OA\Schema(
     *       type="object",
     *       @OA\Items(
     *         type="object",
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Successfully gets franchise page",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *          type="object",
     *       )
     *     )
     *   ),
     *
     *     @OA\Response(
     *     response="400",
     *     description="Error"
     *     ),
     * )
     * @param Request $request
     * @param string $franchiseId
     * @param string $versionId
     * @return JsonResponse
     * @throws NotFoundHttpException
     */
    public function show(Request $request, string $franchiseId, $versionId = 'latest'): JsonResponse
    {
        $isOwner = false;
        $accountId = null;

        /** @var User $user */
        $user = $request->user();

        if ($user) {
            if (
                $request->get('owner')
                && $user->isFranchisor() && $this->userFranchiseID == $franchiseId
            ) {
                $isOwner = true;

                if ($this->userFranchiseID != $franchiseId) {
                    return $this->buildSimpleJsonError('Incorrect user franchise ID');
                }
            } elseif ($user->isMasterAdmin()) {
                $accountId = $request->get('accountId');

                if ($accountId || ($versionId && $versionId !== 'latest')) {
                    $isOwner = true;
                }
            }
        }

        // get data for the API method from DB
        $business = $this->apiMainRequest(
            'show',
            [
                'id' => $franchiseId,
                'versionID' => $versionId,
                'isOwner' => $isOwner
            ]
        );

        // pagination limit
        $perPage = 1;

        // paginate request
        $business = $business->paginate($perPage);

        // collect business info
        $businessArray = $this->collectBusinessInfo($business, $request, $isOwner, $accountId, $versionId);

        // return converted to JSON data
        return response()->json($businessArray);
    }

    /**
     * @OA\Get(
     *   path="/v1/search",
     *   operationId="serachFranchises",
     *   tags={"Search"},
     *   @OA\Parameter(
     *   name="limit",
     *   in="query",
     *   @OA\Schema(
     *     type="object",
     *     @OA\Items(
     *       type="object"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *          type="object",
     *       )
     *     )
     *   ),
     *   @OA\Response(response="400", description="Error"
     *   ),
     * )
     * @param Request $request
     * @return JsonResponse
     * @throws NotFoundHttpException
     */
    public function search(Request $request): JsonResponse
    {
        // get data for the API method from DB
        $business = $this->apiMainRequest('search', null, $request);

        // pagination limit
        $limit = (int)$request->get('perPage', 12);

        // paginate request
        $business = $business->paginate($limit);

        // collect business info
        $businessArray = $this->collectBusinessInfo($business, $request);

        if ($request->get('autocomplete')) {
            $autocompleteData = [];
            foreach ($businessArray['data'] as $businessItem) {
                $autocompleteData[] = [
                    'id' => $businessItem['franchise_id'],
                    'name' => $businessItem['business_name']['attribute_value_string']
                ];
            }

            return $this->buildSimpleJsonSuccess($autocompleteData);
        }

        // return converted to JSON data
        return response()->json($businessArray);
    }

    /**
     * @OA\Get(
     *    path="/v1/count",
     *    operationId="getCount",
     *    tags={"Count"},
     *    description="Get nuber of Franchisees.",
     *     @OA\Schema(
     *       type="object",
     *       @OA\Items(
     *         type="object",
     *      )
     *    ),
     *    @OA\Response(
     *      response="200",
     *      description="Success",
     *      @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *           type="object",
     *        )
     *      )
     *    ),
     *    @OA\Response(response="400", description="Error"
     *    ),
     * )
     * @param Request $request
     * @return JsonResponse
     */
    public function count(Request $request): JsonResponse
    {
        $query = Business::query()
            ->where('businesses.parent_id', '<>', '0') // parent_id == business, not franchises
            ->where('businesses.status', '=', '0') // 0 - active, 1 - hidden
        ;
        $industries = $request->input('industries');
        $totalAmount = $request->input('totalAmount');
        $stateIds = $request->input('stateIds');
        $groupBy = $request->input('groupBy');

        if (!$industries && !$totalAmount && !$stateIds) {
            $query->join('business_attributes as ba', 'businesses.franchise_id', 'ba.franchise_id');
        }

        if ($industries) {
            $attributeId = $this->attributeAliasList['industry'];
            $query->join(
                'business_attributes as industry',
                function (JoinClause $join) use ($attributeId, $industries) {
                    $join->on('industry.franchise_id', 'businesses.franchise_id')
                        ->where('industry.business_attribute_id', $attributeId)
                        ->where('industry.is_actual', 1);

                    $industries = explode(',', $industries);
                    $join->where(
                        function ($queryWhere) use ($industries) {
                            foreach ($industries as $industry) {
                                /** @var Builder $queryWhere */
                                $queryWhere->orWhere('industry.attribute_value_string', $industry);
                            }
                        }
                    );
                }
            );
        }

        if ($totalAmount) {
            $investTypes = UserPreferences::INVEST_TYPES;

            if (!in_array($totalAmount, array_keys($investTypes))) {
                return $this->buildSimpleJsonError('Incorrect total amount');
            }

            $attributeId = $this->attributeAliasList['investment'];
            $min = (float)$investTypes[$totalAmount]['min'];
            $max = (float)$investTypes[$totalAmount]['max'];

            $query->join(
                'business_attributes as investment',
                function (JoinClause $join) use ($attributeId, $min, $max) {
                    $join->on('investment.franchise_id', 'businesses.franchise_id')
                        ->where('investment.business_attribute_id', $attributeId)
                        ->where('investment.is_actual', 1);

                    $max > 0
                        ? $join->whereBetween('investment.attribute_value_numeric_low', [$min, $max])
                        : $join->where('investment.attribute_value_numeric_low', '>=', $min);
                }
            );
        }

        if ($stateIds) {
            $query->join(
                'business_state',
                'business_state.franchise_id',
                'businesses.franchise_id'
            )
                ->whereIn('business_state.state_id', explode(',', $stateIds));
        }

        if ($groupBy === 'states') {
            $subQuery = $query->select(['business_state.state_id', DB::raw('COUNT(*) as cnt')])
                ->groupBy('business_state.id');

            $data = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
                ->select(['state_id as id', DB::raw('CAST(SUM(cnt) as UNSIGNED) as count')])
                ->mergeBindings($subQuery->getQuery())
                ->groupBy('state_id')
                ->get();
        } else {
            $data = $query->distinct('businesses.franchise_id')->count();
        }

        return $this->buildSimpleJsonSuccess($data);
    }
}
