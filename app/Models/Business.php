<?php

namespace App\Models;

use App\Helpers\CachedDirectoriesHelper;
use App\ModelQueryScopes\BusinessScope;
use App\Models\Eloquent\HasManySync;
use App\Services\AvatarUploader;
use App\Traits\HasManySyncTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * @property integer $franchise_id
 * @property integer $parent_id
 * @property string $slug
 * @property integer $status
 * @property BusinessAttribute[]|Collection $businessAttributes
 * @property BusinessRating[]|Collection $businessRatings
 * @property BusinessGjsTemplate[]|Collection $businessGjsTemplates
 * @property BusinessFile $item7
 * @property BusinessFile $item19
 * @property BusinessFile $itemFranchises
 * @property BusinessFile $fdd
 * @property BusinessExternalRating[]|Collection $externalRatings
 * @property BusinessFounder[]|Collection $founders
 * @property State[]|Collection $states
 * @property BusinessTag[]|Collection $tags
 *
 * @method Builder franchiseActive()
 * @method Builder withAllRelationships(int $versionId)
 * @method Builder joinBusinessAttributes(int $attributeId, string $alias)
 * @method Builder joinRelatedTable(string $table, string $alias, $joinFirstColumn = 'franchise_id', $joinSecondColumn = 'franchise_id')
 */
class Business extends Model
{
    use BusinessScope, HasManySyncTrait;

    /**
     * @var int[]
     */
    const VISIBILITY_STATUS = [
        'visible' => 0,
        'hidden' => 1
    ];

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'franchise_id';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = [
        'franchise_id',
        'parent_id',
        'status',
        'slug'
    ];

    /**
     * @var string
     */
    private $company_page_url;

    /**
     * @var string[]
     */
    protected $appends = [
        'company_page_url',
    ];

    /**
     * @var mixed
     */

    public static function boot()
    {
        parent::boot();

        self::created(
            function ($model) {
                if ($model->parent_id != 0) {
                    BusinessStep::createBusinessSteps($model->franchise_id);
                }
            }
        );
    }

    /**
     * @return HasMany
     */
    public function businessAttributes(): HasMany
    {
        return $this->hasMany(BusinessAttribute::class, 'franchise_id', 'franchise_id');
    }

    /**
     * @return HasMany
     */
    public function businessRatings(): HasMany
    {
        return $this->hasMany('App\Models\BusinessRating', 'franchise_id', 'franchise_id');
    }

    /**
     * @return HasMany
     */
    public function businessSteps(): HasMany
    {
        return $this->hasMany('App\Models\BusinessStep', 'business_id', 'franchise_id');
    }

    /**
     * @return HasMany
     */
    public function businessClientSteps(): HasMany
    {
        return $this->hasMany(BusinessClientStep::class, 'franchise_id', 'franchise_id');
    }

    /**
     * @return HasOne
     */
    public function item7(): HasOne
    {
        return $this->hasOne(BusinessFile::class, 'franchise_id', 'franchise_id')
            ->where('item_type', BusinessFile::FILE_TYPES['item_7']);
    }

    /**
     * @return HasOne
     */
    public function item19(): HasOne
    {
        return $this->hasOne(BusinessFile::class, 'franchise_id', 'franchise_id')
            ->where('item_type', BusinessFile::FILE_TYPES['item_19']);
    }

    /**
     * @return HasOne
     */
    public function itemFranchises(): HasOne
    {
        return $this->hasOne(BusinessFile::class, 'franchise_id', 'franchise_id')
            ->where('item_type', BusinessFile::FILE_TYPES['franchises']);
    }

    /**
     * @return HasOne
     */
    public function fdd(): HasOne
    {
        return $this->hasOne(BusinessFile::class, 'franchise_id', 'franchise_id')
            ->where('item_type', BusinessFile::FILE_TYPES['fdd']);
    }

    /**
     * @return HasOne
     */
    public function logo(): HasOne
    {
        return $this->hasOne(BusinessFile::class, 'franchise_id', 'franchise_id')
            ->where('item_type', BusinessFile::FILE_TYPES['logo']);
    }

    /**
     * @return HasMany
     */
    public function files(): HasMany
    {
        return $this->hasMany(BusinessFile::class, 'franchise_id', 'franchise_id');
    }

    /**
     * @return HasMany
     */
    public function businessGjsTemplates(): HasMany
    {
        return $this->hasMany(BusinessGjsTemplate::class, 'business_id', 'franchise_id');
    }

    /**
     * @return BelongsToMany
     */
    public function states(): BelongsToMany
    {
        return $this->belongsToMany(
            State::class,
            'business_state',
            'franchise_id',
            'state_id'
        );
    }

    /**
     * @return BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            BusinessTag::class,
            'business_tag',
            'franchise_id',
            'tag_id',
            null,
            null,
            'business_tags'
        );
    }

    /**
     * @return HasManySync
     */
    public function founders(): HasManySync
    {
        return $this->hasManySync(BusinessFounder::class, 'franchise_id', 'franchise_id');
    }

    /**
     * @return HasMany
     */
    public function externalRatings()
    {
        return $this->hasManySync(
            BusinessExternalRating::class,
            'franchise_id',
            'franchise_id'
        );
    }

    /**
     * TODO: move into BusinessAttributeVersion model
     * @param int $userId
     * @throws \Exception
     */
    public function createFranchiseAttributesTemplate($userId)
    {
        if ($this->parent_id === 0) {
            throw new \Exception();
        }

        $versionId = BusinessAttributeVersion::createNewVersion($this->franchise_id, $userId);
        BusinessAttribute::createBusinessAttributesTemplate($this->franchise_id, $versionId);
    }

    /**
     * TODO: move into BusinessAttributeVersion model
     * @param array $attributes
     * @param int $versionId
     * @param int $isActual
     * @throws \Exception
     */
    public function updateBusinessAttributes(array $attributes, int $versionId, int $isActual = 0)
    {
        if ($this->parent_id === 0) {
            throw new \Exception();
        }

        $attributeTypes = CachedDirectoriesHelper::businessAttributeTypes()->pluck('id', 'alias');
        $attributeDataTypes = BusinessAttributeTypes::ATTRIBUTE_DATA_TYPES;

        $data = [];
        $template = BusinessAttribute::getAttributeValuesTemplate($this->franchise_id, $versionId);
        $deleteAttributeIds = [];

        foreach ($attributes as $attributeName => $attributeValue) {
            $template['business_attribute_id'] = $attributeTypes[$attributeName];
            $dataType = $attributeDataTypes[$attributeName] ?? 'string';

            if ($dataType === 'numeric_range') {
                $template['attribute_value_numeric_low'] = (float)$attributeValue['low'];
                $template['attribute_value_numeric_high'] = (float)$attributeValue['high'];
                $template['attribute_value_numeric_midpoint'] = (float)$attributeValue['midpoint'];
            } else {
                $template['attribute_value_string'] = $dataType === 'boolean'
                    ? (int)$attributeValue
                    : (string)$attributeValue;
                $template['attribute_value_numeric_low'] = 0.0;
                $template['attribute_value_numeric_high'] = 0.0;
                $template['attribute_value_numeric_midpoint'] = 0.0;
            }

            if ($isActual) {
                $template['is_actual'] = $isActual;
            }

            $data[] = $template;
            $deleteAttributeIds[] = $template['business_attribute_id'];
        }

        BusinessAttribute::query()
            ->where('franchise_id', $this->franchise_id)
            ->where('version_id', $versionId)
            ->whereIn('business_attribute_id', $deleteAttributeIds)
            ->delete();

        BusinessAttribute::query()->insert($data);
    }

    /**
     * TODO: move into BusinessAttributeVersion model
     * @param int $versionId
     * @param array $relations
     * @throws \Exception
     */
    public function updateRelations(int $versionId, array $relations)
    {
        if ($this->parent_id === 0) {
            throw new \Exception();
        }

        foreach ($relations as $relationName => $relationData) {
            $businessRelation = $this->{$relationName}();

            if ($businessRelation->getRelated() instanceof BusinessFounder) {
                $this->updateFounders($versionId, $relationData);
            } elseif ($businessRelation->getRelated() instanceof BusinessTag) {
                $this->updateTags($businessRelation, $relationData);
            } elseif ($businessRelation instanceof BelongsToMany) {
                $businessRelation->wherePivot('version_id', $versionId)->sync($relationData);
            } elseif ($businessRelation instanceof HasManySync) {
                $businessRelation->where('version_id', $versionId)->sync($relationData);
            }
        }
    }

    /**
     * TODO: move into BusinessAttributeVersion model
     * @param BelongsToMany $relation
     * @param array $data
     */
    public function updateTags(BelongsToMany $relation, array $data)
    {
        $pivot = $data['pivot'];
        $relationData = [];

        foreach ($data['data'] as $tag) {
            if (!$tag['id']) {
                $tagCreated = BusinessTag::query()
                    ->where('name', $tag['name'])
                    ->first();

                if (!$tagCreated) {
                    $tag = (new BusinessTag())->fill($tag);
                    $tag->save();
                } else {
                    $tag = $tagCreated;
                }

                $pivot['tag_id'] = $tag->id;
                $relationData[] = $pivot;
            } else {
                $pivot['tag_id'] = $tag['id'];
                $relationData[] = $pivot;
            }
        }

        // clear tags to avoid duplicates
        $relation->sync(null);

        // update tags
        $relation->sync($relationData, false);
    }

    /**
     * TODO: move into BusinessAttributeVersion model
     * @param int $versionId
     * @param array $data
     */
    public function updateFounders(int $versionId, array $data)
    {
        /** @var BusinessFounder[]|Collection $founders */
        $founders = BusinessFounder::query()
            ->where('version_id', $versionId)
            ->where('franchise_id', $this->franchise_id)
            ->with('avatar')
            ->get()
            ->keyBy('id');

        foreach ($data as $item) {
            $avatar = $item['avatar'] ?? null;
            unset($item['avatar']);

            if (!empty($item['id']) && $founder = $founders->where('id', $item['id'])->first()) {
                $founders->forget($item['id']);
            } else {
                $founder = new BusinessFounder();
            }

            $founder->fill($item)->save();

            if ($avatar) {
                (new AvatarUploader($founder, $avatar))->upload();
            }
        }

        $founders->each(
            function ($founder) {
                $founder->delete();
            }
        );
    }

    /**
     * @param int $franchiseId
     * @param int $versionId
     * @param int $userId
     * @return string
     */
    public static function encodeApproveUrl(int $franchiseId, int $versionId, int $userId): string
    {
        $params = [
            'franchise_id' => $franchiseId,
            'version_id' => $versionId,
            'user_id' => $userId,
        ];

        return Crypt::encryptString(json_encode($params));
    }

    /**
     * @param string $encoded
     * @return array
     */
    public static function decodeApproveUrl($encoded): array
    {
        return json_decode(Crypt::decryptString($encoded), true);
    }

    /**
     * @param $userId
     * @return int
     */
    public static function isUserApproved($userId): int
    {
        $approved = 1;
        $approvedAttributeId = UserAttributeTypes::query()
            ->where('attribute_name_alias', 'is_approved')
            ->value('attribute_type_id');

        $isApproved = UserAttributes::query()
            ->where('user_id', $userId)
            ->where('user_attribute_id', $approvedAttributeId)
            ->where('attribute_value_string', $approved)
            ->first();

        return $isApproved ? $isApproved->attribute_value_string : 0;
    }

    /**
     * @param string $name
     * @param int $versionId
     * @return string
     */
    public static function generateSlug($name, int $versionId): string
    {
        return Str::slug("{$name}-franchise-{$versionId}");
    }

    /**
     * @return HasManyThrough
     */
    public function franchisors(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            UserBusiness::class,
            'franchise_id',
            'id',
            'franchise_id',
            'user_id'
        );
    }

    /**
     * @param int
     * @return HasOne
     */
    public function userBusiness($userId): HasOne
    {
        return $this->hasOne(UserBusiness::class, 'franchise_id', 'franchise_id')
            ->where('user_id', $userId);
    }

    /**
     * @return HasMany
     */
    public function actualAttributes(): HasMany
    {
        return $this
            ->hasMany(BusinessAttribute::class, 'franchise_id', 'franchise_id')
            ->where('is_actual', 1);
    }

    /**
     * @return HasOne
     */
    public function actualAttributeIndustry(): HasOne
    {
        $franchiseAttributeTypes = CachedDirectoriesHelper::businessAttributeTypes()->pluck('id', 'alias');
        return $this
            ->hasOne(BusinessAttribute::class, 'franchise_id', 'franchise_id')
            ->where('is_actual', 1)
            ->where('business_attribute_id', $franchiseAttributeTypes['industry']);
    }

    /**
     * @param string $attributeNameAlias
     * @param string $attributeKey
     * @param null|int $actualVersionId
     * @return array|mixed|null
     */
    public function getAttributeValueByAttributeName(
        string $attributeNameAlias,
        $attributeKey = 'attribute_value_string',
        $actualVersionId = null
    ): ?array {
        $businessAttribute = new BusinessAttribute();
        $businessAttributeColumns = Schema::getColumnListing($businessAttribute->getTable());

        $attributeId = CachedDirectoriesHelper::businessAttributeTypes()
            ->where('alias', $attributeNameAlias)
            ->pluck('id');

        // validate
        if (in_array($attributeKey, $businessAttributeColumns) && $attributeId->count()) {
            if (!$actualVersionId) {
                $actualVersionId = BusinessAttributeVersion::getActualVersion($this->franchise_id);
            }
            if ($actualVersionId) {
                $businessAttributes = BusinessAttribute::query()
                    ->select($attributeKey)
                    ->where('version_id', $actualVersionId)
                    ->where('franchise_id', $this->franchise_id)
                    ->where('business_attribute_id', $attributeId)
                    ->get();

                if ($businessAttributes->count() === 1) {
                    return $businessAttributes->first()[$attributeKey];
                } elseif ($businessAttributes->count() > 1) {
                    $businessAttributesArray = [];

                    foreach ($businessAttributes as $businessAttribute) {
                        $businessAttributesArray[] = $businessAttribute[$attributeKey];
                    }
                    return $businessAttributesArray;
                }
            }
        }

        return null;
    }

    /**
     * @return string
     */
    public function getCompanyPageUrlAttribute(): string
    {
        return config('app.url') . "/franchise-directory/industries/{$this->slug_industry}/{$this->slug}/";
    }

    /**
     * @param int $actualVersionId
     * @return string
     */
    public function getActualFranchiseName($actualVersionId = null): string
    {
        $attributeId = CachedDirectoriesHelper::businessAttributeTypes()
            ->where('alias', 'business_name')
            ->pluck('id');

        if (!$actualVersionId) {
            $actualVersionId = BusinessAttributeVersion::getActualVersion($this->franchise_id);
        }

        $businessName = $actualVersionId
            ? BusinessAttribute::query()
                ->select('attribute_value_string')
                ->where('version_id', $actualVersionId)
                ->where('franchise_id', $this->franchise_id)
                ->where('business_attribute_id', $attributeId)
                ->first()
            : null;

        return $businessName && trim($businessName->attribute_value_string)
            ? $businessName->attribute_value_string
            : 'Name not set';
    }

    /**
     * @return array
     */
    public function groupRatingsByCategory(): array
    {
        $ratings = $this->externalRatings->keyBy('category_id');
        $categories = BusinessExternalRatingsCategory::query()->select(['id', 'name'])->get();

        $grouped = [];
        foreach ($categories as $category) {
            $rate = $ratings[$category->id] ?? null;

            $grouped[] = [
                'id' => $rate ? $rate->id : null,
                'category_id' => $category->id,
                'category' => $category->name,
                'rating_value' => $rate ? $rate->rating_value : 0
            ];
        }

        return $grouped;
    }
}
