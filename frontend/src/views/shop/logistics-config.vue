<template>
  <div class="app-container">
    <el-alert
      title="配置优先级：用户 > 团队 > 系统"
      type="info"
      :closable="false"
      style="margin-bottom: 16px;"
    />

    <el-card shadow="never" style="margin-bottom: 16px;">
      <div slot="header">功能开关状态</div>
      <div>团队物流配置：{{ switches.enable_team_logistics_config ? '已开启' : '未开启' }}</div>
      <div style="margin-top: 8px;">用户物流配置：{{ switches.enable_user_logistics_config ? '已开启' : '未开启' }}</div>
      <div style="margin-top: 8px; color: #909399;">
        提示：开关在“系统管理 / 系统配置 / 物流插件开关”里维护。
      </div>
    </el-card>

    <el-row :gutter="16">
      <el-col v-if="teamScope.available" :xs="24" :md="12">
        <el-card shadow="never" style="margin-bottom: 16px;">
          <div slot="header">团队物流配置</div>
          <div style="margin-bottom: 12px; color: #909399;">仅团队管理员可编辑；保存后覆盖系统配置。</div>

          <div class="provider-block">
            <div class="provider-title">中国邮政</div>
            <el-switch
              v-model="teamScope.providers.chinapost.enabled"
              :disabled="!canEditTeam || !switches.enable_team_logistics_config"
              active-text="启用团队中国邮政配置"
            />
            <el-form label-width="160px" size="small" style="margin-top: 10px;">
              <el-form-item label="测试授权码">
                <el-input v-model="teamScope.providers.chinapost.config.test_authorization" :disabled="!canEditTeam" />
              </el-form-item>
              <el-form-item label="测试SM4密钥">
                <el-input v-model="teamScope.providers.chinapost.config.test_digest_key" :disabled="!canEditTeam" />
              </el-form-item>
              <el-form-item label="正式授权码">
                <el-input v-model="teamScope.providers.chinapost.config.prod_authorization" :disabled="!canEditTeam" />
              </el-form-item>
              <el-form-item label="正式SM4密钥">
                <el-input v-model="teamScope.providers.chinapost.config.prod_digest_key" :disabled="!canEditTeam" />
              </el-form-item>
              <el-form-item label="协议大客户号">
                <el-input v-model="teamScope.providers.chinapost.config.agreement_code" :disabled="!canEditTeam" />
              </el-form-item>
              <el-form-item label="揽收机构编号">
                <el-input v-model="teamScope.providers.chinapost.config.pickup_org_code" :disabled="!canEditTeam" />
              </el-form-item>
              <el-form-item label="面单AK">
                <el-input v-model="teamScope.providers.chinapost.config.label_ak" :disabled="!canEditTeam" />
              </el-form-item>
            </el-form>
            <el-button
              type="primary"
              size="small"
              :disabled="!canEditTeam || !switches.enable_team_logistics_config"
              @click="saveScopeProvider('team', 'chinapost')"
            >保存团队中国邮政配置</el-button>
          </div>

          <div class="provider-block">
            <div class="provider-title">雷翼 SZ56T</div>
            <el-switch
              v-model="teamScope.providers.sz56t.enabled"
              :disabled="!canEditTeam || !switches.enable_team_logistics_config"
              active-text="启用团队雷翼配置"
            />
            <el-form label-width="160px" size="small" style="margin-top: 10px;">
              <el-form-item label="SZ56T_USERNAME">
                <el-input v-model="teamScope.providers.sz56t.config.username" :disabled="!canEditTeam" />
              </el-form-item>
              <el-form-item label="SZ56T_PASSWORD">
                <el-input v-model="teamScope.providers.sz56t.config.password" :disabled="!canEditTeam" show-password />
              </el-form-item>
            </el-form>
            <el-button
              type="primary"
              size="small"
              :disabled="!canEditTeam || !switches.enable_team_logistics_config"
              @click="saveScopeProvider('team', 'sz56t')"
            >保存团队雷翼配置</el-button>
          </div>
        </el-card>
      </el-col>

      <el-col v-if="userScope.available" :xs="24" :md="12">
        <el-card shadow="never" style="margin-bottom: 16px;">
          <div slot="header">个人物流配置</div>
          <div style="margin-bottom: 12px; color: #909399;">个人配置启用后优先于团队配置。</div>

          <div class="provider-block">
            <div class="provider-title">中国邮政</div>
            <el-switch
              v-model="userScope.providers.chinapost.enabled"
              :disabled="!switches.enable_user_logistics_config"
              active-text="启用个人中国邮政配置"
            />
            <el-form label-width="160px" size="small" style="margin-top: 10px;">
              <el-form-item label="测试授权码">
                <el-input v-model="userScope.providers.chinapost.config.test_authorization" />
              </el-form-item>
              <el-form-item label="测试SM4密钥">
                <el-input v-model="userScope.providers.chinapost.config.test_digest_key" />
              </el-form-item>
              <el-form-item label="正式授权码">
                <el-input v-model="userScope.providers.chinapost.config.prod_authorization" />
              </el-form-item>
              <el-form-item label="正式SM4密钥">
                <el-input v-model="userScope.providers.chinapost.config.prod_digest_key" />
              </el-form-item>
              <el-form-item label="协议大客户号">
                <el-input v-model="userScope.providers.chinapost.config.agreement_code" />
              </el-form-item>
              <el-form-item label="揽收机构编号">
                <el-input v-model="userScope.providers.chinapost.config.pickup_org_code" />
              </el-form-item>
              <el-form-item label="面单AK">
                <el-input v-model="userScope.providers.chinapost.config.label_ak" />
              </el-form-item>
            </el-form>
            <el-button
              type="primary"
              size="small"
              :disabled="!switches.enable_user_logistics_config"
              @click="saveScopeProvider('user', 'chinapost')"
            >保存个人中国邮政配置</el-button>
          </div>

          <div class="provider-block">
            <div class="provider-title">雷翼 SZ56T</div>
            <el-switch
              v-model="userScope.providers.sz56t.enabled"
              :disabled="!switches.enable_user_logistics_config"
              active-text="启用个人雷翼配置"
            />
            <el-form label-width="160px" size="small" style="margin-top: 10px;">
              <el-form-item label="SZ56T_USERNAME">
                <el-input v-model="userScope.providers.sz56t.config.username" />
              </el-form-item>
              <el-form-item label="SZ56T_PASSWORD">
                <el-input v-model="userScope.providers.sz56t.config.password" show-password />
              </el-form-item>
            </el-form>
            <el-button
              type="primary"
              size="small"
              :disabled="!switches.enable_user_logistics_config"
              @click="saveScopeProvider('user', 'sz56t')"
            >保存个人雷翼配置</el-button>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import { fetchLogisticsConfigCurrent, saveLogisticsConfig } from '@/api/system'
import { mapGetters } from 'vuex'

function createEmptyScope() {
  return {
    available: false,
    editable: false,
    scope_id: null,
    providers: {
      chinapost: {
        enabled: false,
        config: {
          test_authorization: '',
          test_digest_key: '',
          prod_authorization: '',
          prod_digest_key: '',
          agreement_code: '',
          pickup_org_code: '',
          label_ak: ''
        }
      },
      sz56t: {
        enabled: false,
        config: {
          username: '',
          password: ''
        }
      }
    }
  }
}

export default {
  name: 'ShopLogisticsConfig',
  data() {
    return {
      loading: false,
      switches: {
        enable_team_logistics_config: false,
        enable_user_logistics_config: false
      },
      teamScope: createEmptyScope(),
      userScope: createEmptyScope()
    }
  },
  computed: {
    ...mapGetters(['roles']),
    canEditTeam() {
      return (this.roles || []).includes('super-admin') || (this.roles || []).includes('team-admin')
    }
  },
  created() {
    this.fetchCurrent()
  },
  methods: {
    fetchCurrent() {
      this.loading = true
      fetchLogisticsConfigCurrent().then((res) => {
        const data = res.data || {}
        this.switches = Object.assign({}, this.switches, data.switches || {})
        this.teamScope = Object.assign(createEmptyScope(), data.scopes && data.scopes.team ? data.scopes.team : {})
        this.userScope = Object.assign(createEmptyScope(), data.scopes && data.scopes.user ? data.scopes.user : {})
      }).finally(() => {
        this.loading = false
      })
    },
    saveScopeProvider(scopeType, provider) {
      const scope = scopeType === 'team' ? this.teamScope : this.userScope
      const payload = {
        scope_type: scopeType,
        scope_id: scope.scope_id,
        provider,
        enabled: !!scope.providers[provider].enabled,
        config: scope.providers[provider].config
      }

      saveLogisticsConfig(payload).then(() => {
        this.$message.success('保存成功')
        this.fetchCurrent()
      })
    }
  }
}
</script>

<style scoped>
.provider-block {
  border: 1px solid #ebeef5;
  border-radius: 6px;
  padding: 12px;
  margin-bottom: 12px;
}

.provider-title {
  font-weight: 600;
  margin-bottom: 8px;
}
</style>
