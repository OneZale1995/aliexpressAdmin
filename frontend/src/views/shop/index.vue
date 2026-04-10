<template>
  <div class="app-container">
    <!-- 搜索 -->
    <div class="filter-container">
      <el-input v-model="listQuery.name" placeholder="店铺名称" style="width: 200px;" class="filter-item" @keyup.enter.native="handleFilter" />
      <el-select v-model="listQuery.team_id" placeholder="选择团队" clearable class="filter-item" style="width: 200px;">
        <el-option v-for="t in teamOptions" :key="t.id" :label="t.name" :value="t.id" />
      </el-select>
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索</el-button>
      <el-button class="filter-item" type="primary" icon="el-icon-plus" @click="handleCreate">新增店铺</el-button>
    </div>

    <!-- 表格 -->
    <el-table v-loading="listLoading" :data="list" border fit highlight-current-row style="width: 100%">
      <el-table-column label="序号" type="index" :index="i => (listQuery.page - 1) * listQuery.limit + i + 1" align="center" width="80" />
      <el-table-column label="店铺名称" prop="name" align="center" min-width="180" />
      <el-table-column label="状态" align="center" width="80">
        <template slot-scope="{row}">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">{{ row.status === 1 ? '正常' : '禁用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="订单更新" align="center" width="200">
        <template slot-scope="{row}">
          <div v-if="row.order_updated_at">
            {{ row.order_updated_at }}
            <div><el-button type="text" size="mini" icon="el-icon-refresh" @click="handleRefreshOrder(row)">手动更新</el-button></div>
          </div>
          <span v-else>-</span>
        </template>
      </el-table-column>
      <el-table-column label="默认设置" align="center" min-width="200">
        <template slot-scope="{row}">
          <div v-if="row.logistics_template_id">默认物流模板ID：<el-link type="primary">{{ row.logistics_template_id }}</el-link></div>
          <div v-if="row.logistics_route">默认物流线路：{{ row.logistics_route }}</div>
          <span v-if="!row.logistics_template_id && !row.logistics_route">-</span>
        </template>
      </el-table-column>
      <el-table-column label="所属团队" align="center" width="120">
        <template slot-scope="{row}">
          {{ row.team ? row.team.name : '-' }}
        </template>
      </el-table-column>
      <el-table-column label="创建日期" prop="created_at" align="center" width="160" />
      <el-table-column label="修改日期" prop="updated_at" align="center" width="160" />
      <el-table-column label="创建人员" align="center" width="100">
        <template slot-scope="{row}">
          {{ row.creator ? (row.creator.nickname || row.creator.username) : '-' }}
        </template>
      </el-table-column>
      <el-table-column label="修改人员" align="center" width="100">
        <template slot-scope="{row}">
          {{ row.updater ? (row.updater.nickname || row.updater.username) : '-' }}
        </template>
      </el-table-column>
      <el-table-column label="操作" align="center" width="160">
        <template slot-scope="{row}">
          <el-button type="primary" size="mini" @click="handleUpdate(row)">编辑</el-button>
          <el-button type="danger" size="mini" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <pagination v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit" @pagination="getList" />

    <!-- 新增/编辑对话框 -->
    <el-dialog :title="dialogStatus === 'create' ? '添加店铺' : '编辑店铺'" :visible.sync="dialogFormVisible" width="650px">
      <el-form ref="dataForm" :rules="computedRules" :model="temp" label-position="left" label-width="100px" style="width: 500px; margin-left:30px;">
        <el-form-item label="店铺名称" prop="name">
          <el-input v-model="temp.name" />
        </el-form-item>
        <el-form-item label="邮箱地址">
          <el-input v-model="temp.email" placeholder="请输入邮箱地址" />
        </el-form-item>
        <el-form-item label="是否启用">
          <el-switch v-model="temp.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="访问令牌">
          <el-input v-model="temp.access_token" type="textarea" :rows="4" />
        </el-form-item>
        <el-form-item label="默认运费">
          <el-input v-model="temp.default_shipping_fee" type="number" />
        </el-form-item>
        <el-form-item label="物流模板">
          <div style="display: flex; align-items: center;">
            <el-select v-model="temp.logistics_template_id" placeholder="请选择" style="flex: 1;" clearable>
              <el-option label="暂无模板" value="" />
            </el-select>
            <el-button type="primary" size="small" style="margin-left: 10px;">更新</el-button>
          </div>
        </el-form-item>
        <el-form-item label="物流线路">
          <el-select v-model="temp.logistics_route" placeholder="请选择" style="width: 100%;" clearable>
            <el-option label="KZCIS专线(HF)" value="KZCIS专线(HF)" />
            <el-option label="官方邮政" value="官方邮政" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="isSuperAdmin" label="所属团队" prop="team_id">
          <el-select v-model="temp.team_id" filterable placeholder="请选择团队" style="width: 100%;">
            <el-option v-for="t in teamOptions" :key="t.id" :label="t.name" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="isSuperAdmin || isTeamAdmin" label="所属采购">
          <el-select v-model="temp.user_id" filterable placeholder="请选择采购人员" style="width: 100%;">
            <el-option v-for="u in memberOptions" :key="u.id" :label="u.nickname || u.username" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="temp.updated_at" label="更新时间">
          <span><i class="el-icon-time" /> {{ temp.updated_at }}</span>
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button @click="resetTemp(); dialogFormVisible && (dialogFormVisible = true)">重置</el-button>
        <el-button @click="dialogFormVisible = false">取消</el-button>
        <el-button type="primary" @click="dialogStatus === 'create' ? submitCreate() : submitUpdate()">保存</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import { fetchShopList, fetchShopDetail, createShop, updateShop, deleteShop, fetchAllTeams } from '@/api/shop'
import { fetchUserList } from '@/api/system'
import Pagination from '@/components/Pagination'
import { mapGetters } from 'vuex'

export default {
  name: 'ShopManage',
  components: { Pagination },
  data() {
    return {
      list: [],
      total: 0,
      listLoading: true,
      listQuery: { page: 1, limit: 50, name: '', team_id: '' },
      teamOptions: [],
      memberOptions: [],
      temp: {
        id: undefined,
        name: '',
        email: '',
        status: 1,
        access_token: '',
        default_shipping_fee: 0,
        logistics_template_id: '',
        logistics_template_name: '',
        logistics_route: '',
        team_id: undefined,
        user_id: undefined,
        updated_at: ''
      },
      dialogFormVisible: false,
      dialogStatus: '',
      rules: {
        name: [{ required: true, message: '请输入店铺名称', trigger: 'blur' }]
      }
    }
  },
  computed: {
    ...mapGetters(['roles']),
    isSuperAdmin() {
      return this.roles && this.roles.includes('super-admin')
    },
    isTeamAdmin() {
      return this.roles && this.roles.includes('team-admin')
    },
    computedRules() {
      const r = { name: this.rules.name }
      if (this.isSuperAdmin) {
        r.team_id = [{ required: true, message: '请选择所属团队', trigger: 'change' }]
      }
      return r
    }
  },
  created() {
    this.getList()
    this.getTeams()
  },
  methods: {
    getList() {
      this.listLoading = true
      fetchShopList(this.listQuery).then(response => {
        this.list = response.data.items
        this.total = response.data.total
        this.listLoading = false
      })
    },
    getTeams() {
      fetchAllTeams().then(response => {
        this.teamOptions = response.data
      })
    },
    getMembers() {
      fetchUserList({ all: 1 }).then(response => {
        this.memberOptions = response.data.items || response.data
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
    },
    resetTemp() {
      this.temp = {
        id: undefined,
        name: '',
        email: '',
        status: 1,
        access_token: '',
        default_shipping_fee: 0,
        logistics_template_id: '',
        logistics_template_name: '',
        logistics_route: '',
        team_id: undefined,
        user_id: undefined,
        updated_at: ''
      }
    },
    handleCreate() {
      this.resetTemp()
      this.dialogStatus = 'create'
      this.dialogFormVisible = true
      if (this.isSuperAdmin || this.isTeamAdmin) {
        this.getMembers()
      }
      this.$nextTick(() => { this.$refs['dataForm'].clearValidate() })
    },
    submitCreate() {
      this.$refs['dataForm'].validate((valid) => {
        if (valid) {
          createShop(this.temp).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({ title: '成功', message: '创建成功', type: 'success', duration: 2000 })
          })
        }
      })
    },
    handleUpdate(row) {
      // 获取详情含access_token
      fetchShopDetail(row.id).then(response => {
        const detail = response.data
        this.temp = Object.assign({}, detail)
        this.dialogStatus = 'update'
        this.dialogFormVisible = true
        if (this.isSuperAdmin || this.isTeamAdmin) {
          this.getMembers()
        }
        this.$nextTick(() => { this.$refs['dataForm'].clearValidate() })
      })
    },
    submitUpdate() {
      this.$refs['dataForm'].validate((valid) => {
        if (valid) {
          const data = {
            name: this.temp.name,
            email: this.temp.email,
            status: this.temp.status,
            access_token: this.temp.access_token,
            default_shipping_fee: this.temp.default_shipping_fee,
            logistics_template_id: this.temp.logistics_template_id,
            logistics_template_name: this.temp.logistics_template_name,
            logistics_route: this.temp.logistics_route,
            team_id: this.temp.team_id,
            user_id: this.temp.user_id
          }
          updateShop(this.temp.id, data).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({ title: '成功', message: '更新成功', type: 'success', duration: 2000 })
          })
        }
      })
    },
    handleDelete(row) {
      this.$confirm('确定删除该店铺?', '提示', { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }).then(() => {
        deleteShop(row.id).then(() => {
          this.getList()
          this.$notify({ title: '成功', message: '删除成功', type: 'success', duration: 2000 })
        })
      })
    },
    handleRefreshOrder(row) {
      this.$notify({ title: '提示', message: '订单更新功能待实现', type: 'info', duration: 2000 })
    }
  }
}
</script>
