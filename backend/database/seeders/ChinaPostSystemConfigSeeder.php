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
                'key' => 'env',
                'value' => 'test',
                'name' => '接口环境',
                'type' => 'switch',
                'options' => '{"activeValue":"production","inactiveValue":"test","activeText":"生产","inactiveText":"测试"}',
                'description' => '切换中国邮政测试/生产环境',
                'sort' => 0,
            ],
            [
                'group' => 'chinapost',
                'key' => 'test_base_url',
                'value' => 'https://211.156.197.248:443',
                'name' => '测试环境地址',
                'type' => 'string',
                'description' => '中国邮政测试环境接口地址',
                'sort' => 1,
            ],
            [
                'group' => 'chinapost',
                'key' => 'test_api_path',
                'value' => '/amp-prod-api/f/amp/api/test',
                'name' => '测试环境路径',
                'type' => 'string',
                'description' => '测试环境接口路径',
                'sort' => 2,
            ],
            [
                'group' => 'chinapost',
                'key' => 'test_authorization',
                'value' => 'UERzNcp0sdrNbVpA',
                'name' => '测试授权码',
                'type' => 'string',
                'description' => '测试环境协议客户授权码',
                'sort' => 3,
            ],
            [
                'group' => 'chinapost',
                'key' => 'test_digest_key',
                'value' => 'b2twaDl2ZUlCcDA3aE9CYw==',
                'name' => '测试签名钥匙',
                'type' => 'string',
                'description' => '测试环境SM4签名钥匙(Base64)',
                'sort' => 4,
            ],
            [
                'group' => 'chinapost',
                'key' => 'prod_base_url',
                'value' => 'https://api.ems.com.cn',
                'name' => '生产环境地址',
                'type' => 'string',
                'description' => '中国邮政生产环境接口地址',
                'sort' => 5,
            ],
            [
                'group' => 'chinapost',
                'key' => 'prod_api_path',
                'value' => '/amp-prod-api/f/amp/api/open',
                'name' => '生产环境路径',
                'type' => 'string',
                'description' => '生产环境接口路径',
                'sort' => 6,
            ],
            [
                'group' => 'chinapost',
                'key' => 'prod_authorization',
                'value' => 'SYn9hom1OUWZp83T',
                'name' => '生产授权码',
                'type' => 'string',
                'description' => '生产环境协议客户授权码',
                'sort' => 7,
            ],
            [
                'group' => 'chinapost',
                'key' => 'prod_digest_key',
                'value' => 'UVlTV25DZnZVd0RmRDJCWg==',
                'name' => '生产签名钥匙',
                'type' => 'string',
                'description' => '生产环境SM4签名钥匙(Base64)',
                'sort' => 8,
            ],
            [
                'group' => 'chinapost',
                'key' => 'eub_product_code',
                'value' => '019',
                'name' => 'e邮宝产品代码',
                'type' => 'string',
                'description' => '中国邮政e邮宝产品代码',
                'sort' => 10,
            ],
            [
                'group' => 'chinapost',
                'key' => 'agreement_code',
                'value' => '1100243316988',
                'name' => '协议客户代码',
                'type' => 'string',
                'description' => '中国邮政协议客户代码(大客户号)',
                'sort' => 11,
            ],
            [
                'group' => 'chinapost',
                'key' => 'ecommerce_flag',
                'value' => '速卖通店群管理',
                'name' => '电商标识',
                'type' => 'string',
                'description' => '中国邮政电商标识',
                'sort' => 12,
            ],
            [
                'group' => 'chinapost',
                'key' => 'pickup_org_code',
                'value' => '36110023',
                'name' => '揽收机构编号',
                'type' => 'string',
                'description' => '中国邮政揽收机构编号',
                'sort' => 13,
            ],
            [
                'group' => 'chinapost',
                'key' => 'label_ak',
                'value' => '',
                'name' => '面单密钥(AK)',
                'type' => 'string',
                'description' => '中国邮政获取面单接口的密钥，由邮政提供',
                'sort' => 14,
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
