<template>
  <div class="app-container">
    <!-- 超管视角：列表 -->
    <template v-if="isSuperAdmin">
      <div class="filter-container">
        <el-input v-model="listQuery.name" placeholder="团队名称" style="width: 200px;" class="filter-item" @keyup.enter.native="handleFilter" />
        <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索</el-button>
        <el-button class="filter-item" type="primary" icon="el-icon-plus" @click="handleCreate">新增团队</el-button>
      </div>

      <el-table v-loading="listLoading" :data="list" border fit highlight-current-row style="width: 100%">
        <el-table-column label="序号" type="index" :index="i => (listQuery.page - 1) * listQuery.limit + i + 1" align="center" width="80" />
        <el-table-column label="团队名称" prop="name" align="center" />
        <el-table-column label="管理员" align="center">
          <template slot-scope="{row}">
            {{ row.admin ? (row.admin.nickname || row.admin.username) : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="采购用户数量" align="center" width="120">
          <template slot-scope="{row}">
            {{ row.members ? row.members.length : 0 }}
          </template>
        </el-table-column>
        <el-table-column label="店铺数" align="center" width="100">
          <template slot-scope="{row}">
            {{ row.shops_count || 0 }}
          </template>
        </el-table-column>
        <el-table-column label="状态" align="center" width="100">
          <template slot-scope="{row}">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" prop="created_at" align="center" width="180" />
        <el-table-column label="操作" align="center" width="200">
          <template slot-scope="{row}">
            <el-button type="primary" size="mini" @click="handleUpdate(row)">编辑</el-button>
            <el-button type="danger" size="mini" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <pagination v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit" @pagination="getList" />
    </template>

    <!-- 团队管理员视角：我的团队 -->
    <template v-else>
      <div v-if="!myTeam" style="text-align: center; padding: 60px 0;">
        <p style="font-size: 16px; color: #999; margin-bottom: 20px;">您还没有创建团队</p>
        <el-button type="primary" icon="el-icon-plus" @click="handleCreate">创建我的团队</el-button>
      </div>

      <div v-else>
        <div style="margin-bottom: 16px; text-align: right;">
          <el-button type="primary" size="small" icon="el-icon-edit" @click="handleUpdate(myTeam)">修改团队名称</el-button>
        </div>
        <el-table :data="[myTeam]" border fit highlight-current-row style="width: 100%">
          <el-table-column label="序号" type="index" align="center" width="80" />
          <el-table-column label="团队名称" prop="name" align="center" />
          <el-table-column label="团队管理员" align="center">
            <template slot-scope="{row}">
              {{ row.admin ? (row.admin.nickname || row.admin.username) : '-' }}
            </template>
          </el-table-column>
          <el-table-column label="采购用户数量" align="center" width="120">
            <template slot-scope="{row}">
              {{ row.members ? row.members.length : 0 }}
            </template>
          </el-table-column>
          <el-table-column label="店铺数" align="center" width="100">
            <template slot-scope="{row}">
              {{ row.shops_count || 0 }}
            </template>
          </el-table-column>
          <el-table-column label="创建时间" prop="created_at" align="center" width="180" />
        </el-table>
      </div>
    </template>

    <!-- 新增/编辑对话框 -->
    <el-dialog :title="dialogTitle" :visible.sync="dialogFormVisible" width="500px">
      <el-form ref="dataForm" :rules="rules" :model="temp" label-position="left" label-width="100px" style="width: 360px; margin-left:30px;">
        <el-form-item label="团队名称" prop="name">
          <el-input v-model="temp.name" />
        </el-form-item>
        <template v-if="isSuperAdmin">
          <el-form-item label="管理员" prop="admin_user_id">
            <el-select v-model="temp.admin_user_id" filterable placeholder="请选择管理员" style="width: 100%">
              <el-option v-for="u in userOptions" :key="u.id" :label="u.nickname || u.username" :value="u.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="采购成员">
            <el-select v-model="temp.member_ids" multiple filterable placeholder="请选择采购成员" style="width: 100%">
              <el-option v-for="u in userOptions" :key="u.id" :label="u.nickname || u.username" :value="u.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="描述">
            <el-input v-model="temp.description" type="textarea" :rows="3" />
          </el-form-item>
          <el-form-item label="状态">
            <el-switch v-model="temp.status" :active-value="1" :inactive-value="0" />
          </el-form-item>
        </template>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button @click="dialogFormVisible = false">取消</el-button>
        <el-button type="primary" @click="dialogStatus === 'create' ? submitCreate() : submitUpdate()">确认</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {fetchTeamList, createTeam, updateTeam, deleteTeam} from '@/api/shop'
import {fetchUserList} from '@/api/system'
import Pagination from '@/components/Pagination'
import {mapGetters} from 'vuex'

export default {
  name: 'TeamManage',
  components: {Pagination},
  data() {
    return {
      list: [],
      total: 0,
      listLoading: true,
      listQuery: {page: 1, limit: 20, name: ''},
      myTeam: null,
      userOptions: [],
      temp: {id: undefined, name: '', admin_user_id: undefined, member_ids: [], description: '', status: 1},
      dialogFormVisible: false,
      dialogStatus: '',
      rules: {
        name: [{required: true, message: '请输入团队名称', trigger: 'blur'}],
        admin_user_id: [{required: true, message: '请选择管理员', trigger: 'change'}]
      }
    }
  },
  computed: {
    ...mapGetters(['roles']),
    isSuperAdmin() {
      return this.roles && this.roles.includes('super-admin')
    },
    dialogTitle() {
      if (this.dialogStatus === 'create') {
        return this.isSuperAdmin ? '新增团队' : '创建我的团队'
      }
      return this.isSuperAdmin ? '编辑团队' : '修改团队名称'
    }
  },
  created() {
    this.loadData()
  },
  methods: {
    loadData() {
      if (this.isSuperAdmin) {
        this.getList()
        this.getUsers()
      } else {
        this.getMyTeam()
      }
    },
    getList() {
      this.listLoading = true
      fetchTeamList(this.listQuery).then(response => {
        this.list = response.data.items
        this.total = response.data.total
        this.listLoading = false
      })
    },
    getMyTeam() {
      this.listLoading = true
      fetchTeamList({page: 1, limit: 1}).then(response => {
        const items = response.data.items || response.data
        this.myTeam = items.length > 0 ? items[0] : null
        this.listLoading = false
      })
    },
    getUsers() {
      fetchUserList({all: 1}).then(response => {
        this.userOptions = response.data.items || response.data
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
    },
    resetTemp() {
      this.temp = {id: undefined, name: '', admin_user_id: undefined, member_ids: [], description: '', status: 1}
    },
    handleCreate() {
      this.resetTemp()
      this.dialogStatus = 'create'
      this.dialogFormVisible = true
      this.$nextTick(() => { this.$refs['dataForm'].clearValidate() })
    },
    submitCreate() {
      this.$refs['dataForm'].validate((valid) => {
        if (valid) {
          createTeam(this.temp).then(() => {
            this.dialogFormVisible = false
            this.loadData()
            this.$notify({title: '成功', message: '创建成功', type: 'success', duration: 2000})
          })
        }
      })
    },
    handleUpdate(row) {
      this.temp = Object.assign({}, row)
      this.temp.member_ids = (row.members || []).map(m => m.id)
      this.dialogStatus = 'update'
      this.dialogFormVisible = true
      this.$nextTick(() => { this.$refs['dataForm'].clearValidate() })
    },
    submitUpdate() {
      this.$refs['dataForm'].validate((valid) => {
        if (valid) {
          const data = this.isSuperAdmin
            ? {name: this.temp.name, admin_user_id: this.temp.admin_user_id, member_ids: this.temp.member_ids, description: this.temp.description, status: this.temp.status}
            : {name: this.temp.name}
          updateTeam(this.temp.id, data).then(() => {
            this.dialogFormVisible = false
            this.loadData()
            this.$notify({title: '成功', message: '更新成功', type: 'success', duration: 2000})
          })
        }
      })
    },
    handleDelete(row) {
      this.$confirm('确定删除该团队?', '提示', {confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning'}).then(() => {
        deleteTeam(row.id).then(() => {
          this.getList()
          this.$notify({title: '成功', message: '删除成功', type: 'success', duration: 2000})
        })
      })
    }
  }
}
</script>
