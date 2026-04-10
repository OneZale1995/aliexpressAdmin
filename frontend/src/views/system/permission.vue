<template>
  <div class="app-container">
    <div class="filter-container">
      <el-button class="filter-item" type="primary" icon="el-icon-plus" @click="handleCreate">新增权限</el-button>
    </div>

    <el-table v-loading="listLoading" :data="list" row-key="id" border default-expand-all :tree-props="{children: 'children'}">
      <el-table-column label="权限名称" prop="display_name" />
      <el-table-column label="权限标识" prop="name" width="200" />
      <el-table-column label="描述" prop="description" />
      <el-table-column label="排序" prop="sort" width="80" align="center" />
      <el-table-column label="操作" align="center" width="200">
        <template slot-scope="{row}">
          <el-button type="primary" size="mini" @click="handleUpdate(row)">编辑</el-button>
          <el-button type="danger" size="mini" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog :title="dialogStatus === 'create' ? '新增权限' : '编辑权限'" :visible.sync="dialogFormVisible">
      <el-form ref="dataForm" :rules="rules" :model="temp" label-position="left" label-width="100px" style="width: 400px; margin-left:50px;">
        <el-form-item label="上级权限">
          <el-cascader
            v-model="temp.parent_id_arr"
            :options="parentOptions"
            :props="{ checkStrictly: true, value: 'id', label: 'display_name', children: 'children', emitPath: false }"
            clearable
            placeholder="顶级权限"
            style="width: 100%"
            @change="val => temp.parent_id = val || 0"
          />
        </el-form-item>
        <el-form-item label="权限名称" prop="display_name">
          <el-input v-model="temp.display_name" />
        </el-form-item>
        <el-form-item label="权限标识" prop="name">
          <el-input v-model="temp.name" placeholder="如: system.user.create" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="temp.description" type="textarea" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="temp.sort" :min="0" />
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
import { fetchPermissionTree, createPermission, updatePermission, deletePermission } from '@/api/system'

export default {
  name: 'PermissionManage',
  data() {
    return {
      list: [],
      listLoading: true,
      parentOptions: [],
      temp: { id: undefined, name: '', display_name: '', parent_id: 0, parent_id_arr: null, description: '', sort: 0 },
      dialogFormVisible: false,
      dialogStatus: '',
      rules: {
        name: [{ required: true, message: '请输入权限标识', trigger: 'blur' }],
        display_name: [{ required: true, message: '请输入权限名称', trigger: 'blur' }]
      }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    getList() {
      this.listLoading = true
      fetchPermissionTree().then(response => {
        this.list = response.data
        this.parentOptions = response.data
        this.listLoading = false
      })
    },
    resetTemp() {
      this.temp = { id: undefined, name: '', display_name: '', parent_id: 0, parent_id_arr: null, description: '', sort: 0 }
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
          createPermission(this.temp).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({ title: '成功', message: '创建成功', type: 'success', duration: 2000 })
          })
        }
      })
    },
    handleUpdate(row) {
      this.temp = Object.assign({}, row)
      this.temp.parent_id_arr = row.parent_id || null
      this.dialogStatus = 'update'
      this.dialogFormVisible = true
      this.$nextTick(() => { this.$refs['dataForm'].clearValidate() })
    },
    submitUpdate() {
      this.$refs['dataForm'].validate((valid) => {
        if (valid) {
          updatePermission(this.temp.id, this.temp).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({ title: '成功', message: '更新成功', type: 'success', duration: 2000 })
          })
        }
      })
    },
    handleDelete(row) {
      this.$confirm('确定删除该权限?', '提示', { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }).then(() => {
        deletePermission(row.id).then(() => {
          this.getList()
          this.$notify({ title: '成功', message: '删除成功', type: 'success', duration: 2000 })
        })
      })
    }
  }
}
</script>
