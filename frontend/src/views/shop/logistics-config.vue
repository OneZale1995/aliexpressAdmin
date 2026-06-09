<template>
  <div class="app-container">
    <el-alert
      title="配置优先级：用户 > 团队 > 系统"
      type="info"
      :closable="false"
      style="margin-bottom: 16px;"
    />

    <el-row :gutter="16">
      <el-col v-if="teamScope.available" :xs="24" :md="12">
        <el-card shadow="never" style="margin-bottom: 16px;">
          <div slot="header">
            <span>团队物流配置</span>
            <el-select
              v-if="isSuperAdmin"
              v-model="selectedTeamId"
              size="small"
              filterable
              placeholder="选择团队"
              style="margin-left: 12px; width: 200px;"
              @change="onTeamChange"
            >
              <el-option
                v-for="t in teamOptions"
                :key="t.id"
                :label="t.name"
                :value="t.id"
              />
            </el-select>
          </div>
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
                <el-input v-model="teamScope.providers.chinapost.config.test_authorization" :disabled="!canEditTeam" placeholder="仅用于配置验证" />
              </el-form-item>
              <el-form-item label="测试签名密钥">
                <el-input v-model="teamScope.providers.chinapost.config.test_digest_key" :disabled="!canEditTeam" placeholder="仅用于配置验证" />
              </el-form-item>
              <el-form-item label="正式授权码">
                <el-input v-model="teamScope.providers.chinapost.config.prod_authorization" :disabled="!canEditTeam" placeholder="实际业务使用" />
              </el-form-item>
              <el-form-item label="正式签名密钥">
                <el-input v-model="teamScope.providers.chinapost.config.prod_digest_key" :disabled="!canEditTeam" placeholder="实际业务使用" />
              </el-form-item>
              <el-form-item label="协议大客户号">
                <el-input v-model="teamScope.providers.chinapost.config.agreement_code" :disabled="!canEditTeam" />
              </el-form-item>
              <el-form-item label="揽收机构编号">
                <el-input v-model="teamScope.providers.chinapost.config.pickup_org_code" :disabled="!canEditTeam" />
              </el-form-item>
            </el-form>
            <div style="display: flex; gap: 8px;">
              <el-button
                type="primary"
                size="small"
                :disabled="!canEditTeam || !switches.enable_team_logistics_config"
                @click="saveScopeProvider('team', 'chinapost')"
              >保存团队中国邮政配置</el-button>
              <el-button
                type="success"
                size="small"
                :disabled="!canEditTeam || !switches.enable_team_logistics_config || !teamScope.providers.chinapost.config.test_authorization"
                :loading="testing.team.chinapostOrder"
                @click="testChinaPostCreateOrder('team')"
              >测试下单</el-button>
              <el-button
                type="warning"
                size="small"
                :disabled="!canEditTeam || !switches.enable_team_logistics_config || !teamWaybillNo"
                :loading="testing.team.chinapostLabel"
                @click="testChinaPostLabel('team')"
              >测试面单</el-button>
            </div>
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
                <el-input v-model="teamScope.providers.sz56t.config.username" :disabled="!canEditTeam" @input="onSz56tInputChange('team')" />
              </el-form-item>
              <el-form-item label="SZ56T_PASSWORD">
                <el-input v-model="teamScope.providers.sz56t.config.password" :disabled="!canEditTeam" show-password />
              </el-form-item>
            </el-form>
            <el-button
              type="primary"
              size="small"
              :disabled="!canEditTeam || !switches.enable_team_logistics_config"
              :loading="saving.team.sz56t"
              @click="saveScopeProvider('team', 'sz56t')"
            >保存团队雷翼配置</el-button>
          </div>
        </el-card>
      </el-col>

      <el-col v-if="userScope.available && switches.enable_user_logistics_config" :xs="24" :md="12">
        <el-card shadow="never" style="margin-bottom: 16px;">
          <div slot="header">
            <span>个人物流配置</span>
            <el-select
              v-if="isSuperAdmin"
              v-model="selectedUserId"
              size="small"
              filterable
              placeholder="选择用户"
              style="margin-left: 12px; width: 200px;"
              @change="onUserChange"
            >
              <el-option
                v-for="u in userOptions"
                :key="u.id"
                :label="u.nickname || u.username"
                :value="u.id"
              />
            </el-select>
          </div>
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
                <el-input v-model="userScope.providers.chinapost.config.test_authorization" placeholder="仅用于配置验证" />
              </el-form-item>
              <el-form-item label="测试签名密钥">
                <el-input v-model="userScope.providers.chinapost.config.test_digest_key" placeholder="仅用于配置验证" />
              </el-form-item>
              <el-form-item label="正式授权码">
                <el-input v-model="userScope.providers.chinapost.config.prod_authorization" placeholder="实际业务使用" />
              </el-form-item>
              <el-form-item label="正式签名密钥">
                <el-input v-model="userScope.providers.chinapost.config.prod_digest_key" placeholder="实际业务使用" />
              </el-form-item>
              <el-form-item label="协议大客户号">
                <el-input v-model="userScope.providers.chinapost.config.agreement_code" />
              </el-form-item>
              <el-form-item label="揽收机构编号">
                <el-input v-model="userScope.providers.chinapost.config.pickup_org_code" />
              </el-form-item>
            </el-form>
            <div style="display: flex; gap: 8px;">
              <el-button
                type="primary"
                size="small"
                :disabled="!switches.enable_user_logistics_config"
                @click="saveScopeProvider('user', 'chinapost')"
              >保存个人中国邮政配置</el-button>
              <el-button
                type="success"
                size="small"
                :disabled="!switches.enable_user_logistics_config || !userScope.providers.chinapost.config.test_authorization"
                :loading="testing.user.chinapostOrder"
                @click="testChinaPostCreateOrder('user')"
              >测试下单</el-button>
              <el-button
                type="warning"
                size="small"
                :disabled="!switches.enable_user_logistics_config || !userWaybillNo"
                :loading="testing.user.chinapostLabel"
                @click="testChinaPostLabel('user')"
              >测试面单</el-button>
            </div>
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
              :loading="saving.user.sz56t"
              @click="saveScopeProvider('user', 'sz56t')"
            >保存个人雷翼配置</el-button>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import { fetchLogisticsConfigCurrent, saveLogisticsConfig, fetchUserList, testSz56tConnect, testChinaPostCreateOrder, testChinaPostLabel } from '@/api/system'
import { fetchAllTeams } from '@/api/shop'
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
          pickup_org_code: ''
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
      userScope: createEmptyScope(),
      teamOptions: [],
      userOptions: [],
      selectedTeamId: null,
      selectedUserId: null,
      saving: {
        team: { sz56t: false },
        user: { sz56t: false }
      },
      testing: {
        team: { chinapostOrder: false, chinapostLabel: false },
        user: { chinapostOrder: false, chinapostLabel: false }
      },
      teamWaybillNo: '',
      userWaybillNo: ''
    }
  },
  computed: {
    ...mapGetters(['roles']),
    isSuperAdmin() {
      return (this.roles || []).includes('super-admin')
    },
    canEditTeam() {
      return this.isSuperAdmin || (this.roles || []).includes('team-admin')
    }
  },
  created() {
    this.init()
  },
  methods: {
    init() {
      if (this.isSuperAdmin) {
        Promise.all([this.fetchTeams(), this.fetchUsers()]).then(() => {
          this.fetchCurrent()
        })
      } else {
        this.fetchCurrent()
      }
    },
    fetchTeams() {
      return fetchAllTeams().then((res) => {
        this.teamOptions = (res.data || []).map(t => ({ id: t.id, name: t.name }))
      })
    },
    fetchUsers() {
      return fetchUserList({ all: 1 }).then((res) => {
        this.userOptions = (res.data || []).map(u => ({ id: u.id, username: u.username, nickname: u.nickname }))
      })
    },
    fetchCurrent() {
      this.loading = true
      const params = {}
      if (this.isSuperAdmin && this.selectedTeamId) {
        params.team_id = this.selectedTeamId
      }
      if (this.isSuperAdmin && this.selectedUserId) {
        params.user_id = this.selectedUserId
      }
      fetchLogisticsConfigCurrent(params).then((res) => {
        const data = res.data || {}
        this.switches = Object.assign({}, this.switches, data.switches || {})
        this.teamScope = Object.assign(createEmptyScope(), data.scopes && data.scopes.team ? data.scopes.team : {})
        this.userScope = Object.assign(createEmptyScope(), data.scopes && data.scopes.user ? data.scopes.user : {})
        // 同步选中值到后端返回的 scope_id
        if (this.isSuperAdmin && !this.selectedTeamId && data.scopes && data.scopes.team) {
          this.selectedTeamId = data.scopes.team.scope_id
        }
        if (this.isSuperAdmin && !this.selectedUserId && data.scopes && data.scopes.user) {
          this.selectedUserId = data.scopes.user.scope_id
        }
      }).finally(() => {
        this.loading = false
      })
    },
    onTeamChange(teamId) {
      this.selectedTeamId = teamId
      this.fetchCurrent()
    },
    onUserChange(userId) {
      this.selectedUserId = userId
      this.fetchCurrent()
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
      if (this.isSuperAdmin && scopeType === 'team' && this.selectedTeamId) {
        payload.team_id = this.selectedTeamId
      }
      if (this.isSuperAdmin && scopeType === 'user' && this.selectedUserId) {
        payload.user_id = this.selectedUserId
      }

      // 雷翼保存前先测试连接，通过才允许保存
      if (provider === 'sz56t') {
        this.saving[scopeType].sz56t = true
        const testPayload = {
          scope_type: scopeType,
          scope_id: scope.scope_id
        }
        if (this.isSuperAdmin && scopeType === 'team' && this.selectedTeamId) {
          testPayload.team_id = this.selectedTeamId
        }
        if (this.isSuperAdmin && scopeType === 'user' && this.selectedUserId) {
          testPayload.user_id = this.selectedUserId
        }
        testSz56tConnect(testPayload).then((res) => {
          this.$message.success('配置验证通过，正在保存…')
          this.doSave(payload)
        }).catch((err) => {
          if (!err.alreadyNotified) {
            this.$message.error((err && err.message) || '雷翼认证失败，请检查账号密码配置')
          }
        }).finally(() => {
          this.saving[scopeType].sz56t = false
        })
        return
      }

      this.doSave(payload)
    },
    doSave(payload) {
      saveLogisticsConfig(payload).then(() => {
        this.$message.success('保存成功')
        this.fetchCurrent()
      })
    },
    testChinaPostCreateOrder(scopeType) {
      const scope = scopeType === 'team' ? this.teamScope : this.userScope
      const payload = {
        scope_type: scopeType,
        scope_id: scope.scope_id
      }
      if (this.isSuperAdmin && scopeType === 'team' && this.selectedTeamId) {
        payload.team_id = this.selectedTeamId
      }
      if (this.isSuperAdmin && scopeType === 'user' && this.selectedUserId) {
        payload.user_id = this.selectedUserId
      }

      this.testing[scopeType].chinapostOrder = true
      testChinaPostCreateOrder(payload).then((res) => {
        const data = res.data || {}
        const waybillNo = data.waybill_no || ''
        if (scopeType === 'team') {
          this.teamWaybillNo = waybillNo
        } else {
          this.userWaybillNo = waybillNo
        }
        const msg = (res && res.message) || ('测试下单成功，运单号: ' + waybillNo)
        this.$alert(msg + '\n\n请前往中国邮政平台点击【上线】使配置生效。', '测试下单结果', {
          confirmButtonText: '知道了',
          type: 'success'
        })
      }).catch((err) => {
        if (!err.alreadyNotified) {
          this.$message.error((err && err.message) || '测试下单失败')
        }
      }).finally(() => {
        this.testing[scopeType].chinapostOrder = false
      })
    },
    testChinaPostLabel(scopeType) {
      const scope = scopeType === 'team' ? this.teamScope : this.userScope
      const waybillNo = scopeType === 'team' ? this.teamWaybillNo : this.userWaybillNo
      const payload = {
        scope_type: scopeType,
        scope_id: scope.scope_id,
        waybill_no: waybillNo
      }
      if (this.isSuperAdmin && scopeType === 'team' && this.selectedTeamId) {
        payload.team_id = this.selectedTeamId
      }
      if (this.isSuperAdmin && scopeType === 'user' && this.selectedUserId) {
        payload.user_id = this.selectedUserId
      }

      this.testing[scopeType].chinapostLabel = true
      testChinaPostLabel(payload).then((res) => {
        const msg = (res && res.message) || '测试获取面单成功'
        this.$alert(msg, '测试面单结果', {
          confirmButtonText: '知道了',
          type: 'success'
        })
      }).catch((err) => {
        if (!err.alreadyNotified) {
          this.$message.error((err && err.message) || '测试获取面单失败')
        }
      }).finally(() => {
        this.testing[scopeType].chinapostLabel = false
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
