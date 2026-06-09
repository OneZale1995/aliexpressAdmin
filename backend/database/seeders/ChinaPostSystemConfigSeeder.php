<?php

namespace Database\Seeders;

use App\Models\SystemConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChinaPostSystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            [
                'group' => 'chinapost',
                'key' => 'test_authorization',
                'value' => 'SYn9hom1OUWZp83T',
                'name' => '测试授权码',
                'type' => 'string',
                'description' => '测试环境协议客户授权码（仅用于配置验证）',
                'sort' => 0,
            ],
            [
                'group' => 'chinapost',
                'key' => 'test_digest_key',
                'value' => 'UVlTV25DZnZVd0RmRDJCWg==',
                'name' => '测试签名密钥',
                'type' => 'string',
                'description' => '测试环境SM4签名密钥(Base64)（仅用于配置验证）',
                'sort' => 1,
            ],
            [
                'group' => 'chinapost',
                'key' => 'prod_authorization',
                'value' => 'SYn9hom1OUWZp83T',
                'name' => '正式授权码',
                'type' => 'string',
                'description' => '正式环境协议客户授权码（实际业务使用）',
                'sort' => 2,
            ],
            [
                'group' => 'chinapost',
                'key' => 'prod_digest_key',
                'value' => 'UVlTV25DZnZVd0RmRDJCWg==',
                'name' => '正式签名密钥',
                'type' => 'string',
                'description' => '正式环境SM4签名密钥(Base64)（实际业务使用）',
                'sort' => 3,
            ],
            [
                'group' => 'chinapost',
                'key' => 'test_api_url',
                'value' => 'https://api.ems.com.cn/amp-prod-api/f/amp/api/test',
                'name' => '测试环境地址',
                'type' => 'string',
                'description' => '沙箱测试接口完整地址',
                'sort' => 4,
            ],
            [
                'group' => 'chinapost',
                'key' => 'api_url',
                'value' => 'https://api.ems.com.cn/amp-prod-api/f/amp/api/open',
                'name' => '正式环境地址',
                'type' => 'string',
                'description' => '生产接口完整地址',
                'sort' => 5,
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
                'value' => '8nVV209U16ml9q63',
                'name' => '面单密钥(AK)',
                'type' => 'string',
                'description' => '中国邮政获取面单接口的密钥，由邮政提供（系统级统一配置）',
                'sort' => 14,
            ],
        ];

        // 清理废弃的环境切换及旧URL参数
        DB::table('admin_system_configs')
            ->where('group', 'chinapost')
            ->whereIn('key', [
                'env',
                'test_base_url',
                'test_api_path',
                'prod_base_url',
                'prod_api_path',
            ])
            ->delete();

        foreach ($configs as $config) {
            DB::table('admin_system_configs')->updateOrInsert(
                [
                    'group' => $config['group'],
                    'key' => $config['key'],
                ],
                $config
            );
        }

        SystemConfig::clearCache('chinapost');
    }
}
