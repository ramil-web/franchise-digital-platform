<?php

namespace App\Console\Commands;

use App\Helpers\CachedDirectoriesHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class UpdateMembershipUnClaimedFranchisees
 * @package App\Console\Commands
 */
class UpdateMembershipUnClaimedFranchisees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update-membership-un-claimed-franchisees';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set "Abusiness" membership for unClaimed  franchisees with business';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle(): int
    {
        $businessAttributeIds = $this->getBusinessAttributeId();
        $claimed = $this->getClaimed();

        return DB::table('business_attributes')
            ->select(['franchise_id', 'attribute_value_string'])
            ->where('business_attribute_id', $businessAttributeIds['membership'])
            ->whereIn('attribute_value_string', ['Business', 'business', null, ''])
            ->whereNotIn('franchise_id', $claimed)
            ->update(['attribute_value_string' => 'Abusiness']);
    }

    /**
     * @return Collection
     */
    protected function getBusinessAttributeId(): Collection
    {
        return CachedDirectoriesHelper::businessAttributeTypes()
            ->whereIn('alias', ['membership'])
            ->pluck('id', 'alias');
    }

    /**
     * @return array
     */
    protected function getClaimed(): array
    {
        $claimed = [];
        $claimedFranchises = DB::table('user_business')
            ->select(['franchise_id'])
            ->where('option', 1)
            ->get()
            ->toArray();
        foreach ($claimedFranchises as $item) {
            $claimed[] = (array)$item->franchise_id;
        }
        return $claimed;
    }
}
