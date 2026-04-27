<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChinaPostSystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            [
                'group' => 'chinapost',
                'key' => 'eub_product_code',
                'value' => '019',
                'name' => 'e邮宝产品代码',
                'type' => 'string',
                'description' => '中国邮政e邮宝产品代码',
                'sort' => 1,
            ],
            [
                'group' => 'chinapost',
                'key' => 'agreement_code',
                'value' => '1100243316988',
                'name' => '协议客户代码',
                'type' => 'string',
                'description' => '中国邮政协议客户代码',
                'sort' => 2,
            ],
            [
                'group' => 'chinapost',
                'key' => 'ecommerce_flag',
                'value' => '速卖通店群管理',
                'name' => '电商标识',
                'type' => 'string',
                'description' => '中国邮政电商标识',
                'sort' => 3,
            ],
            [
                'group' => 'chinapost',
                'key' => 'pickup_org_code',
                'value' => '36110023',
                'name' => '揽收机构编号',
                'type' => 'string',
                'description' => '中国邮政揽收机构编号',
                'sort' => 4,
            ],
        ];

        foreach ($configs as $config) {
            DB::table('admin_system_configs')->updateOrInsert(
                [
                    'group' => $config['group'],
                    'key' => $config['key'],
                ],
                $config
            );
        }
    }
}