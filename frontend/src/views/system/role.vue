<template>
  <div class="app-container">
    <div class="filter-container">
      <el-input v-model="listQuery.display_name" placeholder="角色名称" style="width: 200px;" class="filter-item" @keyup.enter.native="handleFilter" />
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索</el-button>
      <el-button class="filter-item" type="primary" icon="el-icon-plus" @click="handleCreate">新增</el-button>
    </div>

    <el-table v-loading="listLoading" :data="list" border fit highlight-current-row style="width: 100%">
      <el-table-column label="序号" type="index" :index="i => (listQuery.page - 1) * listQuery.limit + i + 1" align="center" width="80" />
      <el-table-column label="角色标识" prop="name" align="center" />
      <el-table-column label="显示名称" prop="display_name" align="center" />
      <el-table-column label="描述" prop="description" align="center" />
      <el-table-column label="状态" align="center" width="100">
        <template slot-scope="{row}">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" align="center" width="200">
        <template slot-scope="{row}">
          <el-button type="primary" size="mini" @click="handleUpdate(row)">编辑</el-button>
          <el-button type="danger" size="mini" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <pagination v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit" @pagination="getList" />

    <el-dialog :title="dialogStatus === 'create' ? '新增角色' : '编辑角色'" :visible.sync="dialogFormVisible" width="600px">
      <el-form ref="dataForm" :rules="rules" :model="temp" label-position="left" label-width="100px" style="padding: 0 20px;">
        <el-form-item label="角色标识" prop="name">
          <el-input v-model="temp.name" placeholder="如: editor" />
        </el-form-item>
        <el-form-item label="显示名称" prop="display_name">
          <el-input v-model="temp.display_name" placeholder="如: 编辑员" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="temp.description" type="textarea" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="temp.sort" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="temp.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="权限">
          <el-tree
            ref="permTree"
            :data="permissionTree"
            show-checkbox
            node-key="id"
            :default-checked-keys="temp.permission_ids"
            :props="{ children: 'children', label: 'display_name' }"
          />
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
import { fetchRoleList, createRole, updateRole, deleteRole, fetchPermissionTree } from '@/api/system'
import Pagination from '@/components/Pagination'

export default {
  name: 'RoleManage',
  components: { Pagination },
  data() {
    return {
      list: [],
      total: 0,
      listLoading: true,
      listQuery: { page: 1, limit: 20, display_name: '' },
      permissionTree: [],
      temp: { id: undefined, name: '', display_name: '', description: '', status: 1, sort: 0, permission_ids: [] },
      dialogFormVisible: false,
      dialogStatus: '',
      rules: {
        name: [{ required: true, message: '请输入角色标识', trigger: 'blur' }],
        display_name: [{ required: true, message: '请输入显示名称', trigger: 'blur' }]
      }
    }
  },
  created() {
    this.getList()
    this.getPermissionTree()
  },
  methods: {
    getList() {
      this.listLoading = true
      fetchRoleList(this.listQuery).then(response => {
        this.list = response.data.items
        this.total = response.data.total
        this.listLoading = false
      })
    },
    getPermissionTree() {
      fetchPermissionTree().then(response => {
        this.permissionTree = response.data
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
    },
    resetTemp() {
      this.temp = { id: undefined, name: '', display_name: '', description: '', status: 1, sort: 0, permission_ids: [] }
    },
    handleCreate() {
      this.resetTemp()
      this.dialogStatus = 'create'
      this.dialogFormVisible = true
      this.$nextTick(() => {
        this.$refs['dataForm'].clearValidate()
        this.$refs.permTree && this.$refs.permTree.setCheckedKeys([])
      })
    },
    submitCreate() {
      this.$refs['dataForm'].validate((valid) => {
        if (valid) {
          const data = { ...this.temp }
          data.permission_ids = this.$refs.permTree.getCheckedKeys().concat(this.$refs.permTree.getHalfCheckedKeys())
          createRole(data).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({ title: '成功', message: '创建成功', type: 'success', duration: 2000 })
          })
        }
      })
    },
    handleUpdate(row) {
      this.temp = Object.assign({}, row)
      this.temp.permission_ids = (row.permissions || []).map(p => p.id)
      this.dialogStatus = 'update'
      this.dialogFormVisible = true
      this.$nextTick(() => {
        this.$refs['dataForm'].clearValidate()
        // 只勾选叶子节点，el-tree 会自动处理半选
        const checkedIds = this.getLeafIds(this.permissionTree, this.temp.permission_ids)
        this.$refs.permTree && this.$refs.permTree.setCheckedKeys(checkedIds)
      })
    },
    getLeafIds(tree, ids) {
      const leaves = []
      const walk = (nodes) => {
        nodes.forEach(node => {
          if (node.children && node.children.length) {
            walk(node.children)
          } else if (ids.includes(node.id)) {
            leaves.push(node.id)
          }
        })
      }
      walk(tree)
      return leaves
    },
    submitUpdate() {
      this.$refs['dataForm'].validate((valid) => {
        if (valid) {
          const data = { name: this.temp.name, display_name: this.temp.display_name, description: this.temp.description, status: this.temp.status, sort: this.temp.sort }
          data.permission_ids = this.$refs.permTree.getCheckedKeys().concat(this.$refs.permTree.getHalfCheckedKeys())
          updateRole(this.temp.id, data).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({ title: '成功', message: '更新成功', type: 'success', duration: 2000 })
          })
        }
      })
    },
    handleDelete(row) {
      this.$confirm('确定删除该角色?', '提示', { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }).then(() => {
        deleteRole(row.id).then(() => {
          this.getList()
          this.$notify({ title: '成功', message: '删除成功', type: 'success', duration: 2000 })
        })
      })
    }
  }
}
</script>
