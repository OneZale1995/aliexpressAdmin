<template>
  <div class="app-container">
    <!-- 搜索 -->
    <div class="filter-container">
      <el-input v-model="listQuery.username" placeholder="用户名" style="width: 200px;" class="filter-item" @keyup.enter.native="handleFilter" />
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索</el-button>
      <el-button class="filter-item" type="primary" icon="el-icon-plus" @click="handleCreate">添加采购用户</el-button>
    </div>

    <!-- 表格 -->
    <el-table v-loading="listLoading" :data="list" border fit highlight-current-row style="width: 100%">
      <el-table-column label="序号" type="index" :index="i => (listQuery.page - 1) * listQuery.limit + i + 1" align="center" width="80" />
      <el-table-column label="用户名" prop="username" align="center" />
      <el-table-column label="昵称" prop="nickname" align="center" />
      <el-table-column label="角色" align="center">
        <template slot-scope="{row}">
          <el-tag v-for="role in row.roles" :key="role.id" size="mini" style="margin-right: 4px;">{{ role.display_name }}</el-tag>
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

    <!-- 新增/编辑对话框 -->
    <el-dialog :title="dialogStatus === 'create' ? '添加采购用户' : '编辑采购用户'" :visible.sync="dialogFormVisible" width="500px">
      <el-form ref="dataForm" :rules="computedRules" :model="temp" label-position="left" label-width="100px" style="width: 360px; margin-left:30px;">
        <el-form-item label="用户名" prop="username">
          <el-input v-model="temp.username" />
        </el-form-item>
        <el-form-item label="昵称">
          <el-input v-model="temp.nickname" />
        </el-form-item>
        <el-form-item label="密码" :prop="dialogStatus === 'create' ? 'password' : ''">
          <el-input v-model="temp.password" type="password" :placeholder="dialogStatus === 'update' ? '留空则不修改' : ''" />
        </el-form-item>
        <!-- 超管才显示团队选择 -->
        <el-form-item v-if="isSuperAdmin && dialogStatus === 'create'" label="所属团队" prop="team_id">
          <el-select v-model="temp.team_id" filterable placeholder="请选择团队" style="width: 100%;">
            <el-option v-for="t in teamOptions" :key="t.id" :label="t.name" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="dialogStatus === 'update'" label="状态">
          <el-switch v-model="temp.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button @click="dialogFormVisible = false">取消</el-button>
        <el-button type="primary" @click="dialogStatus === 'create' ? submitCreate() : submitUpdate()">确认</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {fetchTeamUserList, createTeamUser, updateTeamUser, deleteTeamUser, fetchAllTeams} from '@/api/shop'
import Pagination from '@/components/Pagination'
import {mapGetters} from 'vuex'

export default {
  name: 'TeamUserManage',
  components: {Pagination},
  data() {
    return {
      list: [],
      total: 0,
      listLoading: true,
      listQuery: {page: 1, limit: 20, username: ''},
      teamOptions: [],
      temp: {id: undefined, username: '', nickname: '', password: '', team_id: undefined, status: 1},
      dialogFormVisible: false,
      dialogStatus: '',
      rules: {
        username: [{required: true, message: '请输入用户名', trigger: 'blur'}],
        password: [{required: true, message: '请输入密码', trigger: 'blur'}, {min: 6, message: '密码至少6位', trigger: 'blur'}],
        team_id: [{required: true, message: '请选择团队', trigger: 'change'}]
      }
    }
  },
  computed: {
    ...mapGetters(['roles']),
    isSuperAdmin() {
      return this.roles && this.roles.includes('super-admin')
    },
    computedRules() {
      const r = {
        username: this.rules.username,
        password: this.rules.password
      }
      // 只有超管创建时需要选团队
      if (this.isSuperAdmin) {
        r.team_id = this.rules.team_id
      }
      return r
    }
  },
  created() {
    this.getList()
    if (this.isSuperAdmin) {
      this.getTeams()
    }
  },
  methods: {
    getList() {
      this.listLoading = true
      fetchTeamUserList(this.listQuery).then(response => {
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
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
    },
    resetTemp() {
      this.temp = {id: undefined, username: '', nickname: '', password: '', team_id: undefined, status: 1}
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
          createTeamUser(this.temp).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({title: '成功', message: '创建成功', type: 'success', duration: 2000})
          })
        }
      })
    },
    handleUpdate(row) {
      this.temp = Object.assign({}, row)
      this.temp.password = ''
      this.dialogStatus = 'update'
      this.dialogFormVisible = true
      this.$nextTick(() => { this.$refs['dataForm'].clearValidate() })
    },
    submitUpdate() {
      this.$refs['dataForm'].validate((valid) => {
        if (valid) {
          const data = {username: this.temp.username, nickname: this.temp.nickname, status: this.temp.status}
          if (this.temp.password) { data.password = this.temp.password }
          updateTeamUser(this.temp.id, data).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({title: '成功', message: '更新成功', type: 'success', duration: 2000})
          })
        }
      })
    },
    handleDelete(row) {
      this.$confirm('确定删除该用户?', '提示', {confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning'}).then(() => {
        deleteTeamUser(row.id).then(() => {
          this.getList()
          this.$notify({title: '成功', message: '删除成功', type: 'success', duration: 2000})
        })
      })
    }
  }
}
</script>
