<template>
  <div class="app-container">
    <!-- 搜索 -->
    <div class="filter-container">
      <el-input v-model="listQuery.username" placeholder="用户名" style="width: 200px;" class="filter-item" @keyup.enter.native="handleFilter" />
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索</el-button>
      <el-button class="filter-item" type="primary" icon="el-icon-plus" @click="handleCreate">新增</el-button>
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
    <el-dialog :title="dialogStatus === 'create' ? '新增用户' : '编辑用户'" :visible.sync="dialogFormVisible">
      <el-form ref="dataForm" :rules="rules" :model="temp" label-position="left" label-width="80px" style="width: 400px; margin-left:50px;">
        <el-form-item label="用户名" prop="username">
          <el-input v-model="temp.username" />
        </el-form-item>
        <el-form-item label="昵称">
          <el-input v-model="temp.nickname" />
        </el-form-item>
        <el-form-item label="密码" prop="password">
          <el-input v-model="temp.password" type="password" autocomplete="new-password" :placeholder="dialogStatus === 'update' ? '留空则不修改' : ''" />
        </el-form-item>
        <el-form-item label="角色">
          <el-select v-model="temp.role_ids" multiple placeholder="请选择角色" style="width: 100%">
            <el-option v-for="role in roleOptions" :key="role.id" :label="role.display_name" :value="role.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
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
import { fetchUserList, createUser, updateUser, deleteUser, fetchAllRoles } from '@/api/system'
import Pagination from '@/components/Pagination'

export default {
  name: 'UserManage',
  components: { Pagination },
  data() {
    return {
      list: [],
      total: 0,
      listLoading: true,
      listQuery: { page: 1, limit: 20, username: '' },
      roleOptions: [],
      temp: { id: undefined, username: '', nickname: '', password: '', role_ids: [], status: 1 },
      dialogFormVisible: false,
      dialogStatus: ''
    }
  },
  computed: {
    rules() {
      const passwordRules = this.dialogStatus === 'create'
        ? [{ required: true, message: '请输入密码', trigger: 'blur' }, { min: 6, message: '密码至少6位', trigger: 'blur' }]
        : [{ min: 6, message: '密码至少6位', trigger: 'blur' }]
      return {
        username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
        password: passwordRules
      }
    }
  },
  created() {
    this.getList()
    this.getRoles()
  },
  methods: {
    getList() {
      this.listLoading = true
      fetchUserList(this.listQuery).then(response => {
        this.list = response.data.items
        this.total = response.data.total
        this.listLoading = false
      })
    },
    getRoles() {
      fetchAllRoles().then(response => {
        this.roleOptions = response.data
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
    },
    resetTemp() {
      this.temp = { id: undefined, username: '', nickname: '', password: '', role_ids: [], status: 1 }
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
          createUser(this.temp).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({ title: '成功', message: '创建成功', type: 'success', duration: 2000 })
          })
        }
      })
    },
    handleUpdate(row) {
      this.temp = Object.assign({}, row, { password: '', role_ids: (row.roles || []).map(r => r.id) })
      this.dialogStatus = 'update'
      this.dialogFormVisible = true
      this.$nextTick(() => { this.$refs['dataForm'].clearValidate() })
    },
    submitUpdate() {
      this.$refs['dataForm'].validate((valid) => {
        if (valid) {
          const data = { username: this.temp.username, nickname: this.temp.nickname, status: this.temp.status, role_ids: this.temp.role_ids }
          if (this.temp.password) { data.password = this.temp.password }
          updateUser(this.temp.id, data).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({ title: '成功', message: '更新成功', type: 'success', duration: 2000 })
          })
        }
      })
    },
    handleDelete(row) {
      this.$confirm('确定删除该用户?', '提示', { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }).then(() => {
        deleteUser(row.id).then(() => {
          this.getList()
          this.$notify({ title: '成功', message: '删除成功', type: 'success', duration: 2000 })
        })
      })
    }
  }
}
</script>
