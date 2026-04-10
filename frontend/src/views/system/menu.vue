<template>
  <div class="app-container">
    <div class="filter-container">
      <el-button class="filter-item" type="primary" icon="el-icon-plus" @click="handleCreate">新增菜单</el-button>
    </div>

    <el-table v-loading="listLoading" :data="list" row-key="id" border default-expand-all :tree-props="{children: 'children'}">
      <el-table-column label="菜单标题" prop="title" />
      <el-table-column label="图标" width="80" align="center">
        <template slot-scope="{row}">
          <svg-icon v-if="row.icon && !row.icon.startsWith('el-icon-')" :icon-class="row.icon" />
          <i v-else-if="row.icon" :class="row.icon" />
        </template>
      </el-table-column>
      <el-table-column label="路由路径" prop="path" width="150" />
      <el-table-column label="组件路径" prop="component" width="150" />
      <el-table-column label="权限标识" prop="permission" width="150" />
      <el-table-column label="类型" width="80" align="center">
        <template slot-scope="{row}">
          <el-tag v-if="row.type === 1" size="mini">目录</el-tag>
          <el-tag v-else-if="row.type === 2" size="mini" type="success">菜单</el-tag>
          <el-tag v-else size="mini" type="warning">按钮</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="排序" prop="sort" width="80" align="center" />
      <el-table-column label="状态" width="80" align="center">
        <template slot-scope="{row}">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="mini">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" align="center" width="200">
        <template slot-scope="{row}">
          <el-button type="primary" size="mini" @click="handleUpdate(row)">编辑</el-button>
          <el-button type="danger" size="mini" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog :title="dialogStatus === 'create' ? '新增菜单' : '编辑菜单'" :visible.sync="dialogFormVisible" width="600px">
      <el-form ref="dataForm" :rules="rules" :model="temp" label-position="left" label-width="100px" style="padding: 0 20px;">
        <el-form-item label="上级菜单">
          <el-cascader
            v-model="temp.parent_id_arr"
            :options="parentOptions"
            :props="{ checkStrictly: true, value: 'id', label: 'title', children: 'children', emitPath: false }"
            clearable
            placeholder="顶级菜单"
            style="width: 100%"
            @change="val => temp.parent_id = val || 0"
          />
        </el-form-item>
        <el-form-item label="菜单类型">
          <el-radio-group v-model="temp.type">
            <el-radio :label="1">目录</el-radio>
            <el-radio :label="2">菜单</el-radio>
            <el-radio :label="3">按钮</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="菜单标题" prop="title">
          <el-input v-model="temp.title" />
        </el-form-item>
        <el-form-item label="图标">
          <icon-picker v-model="temp.icon" />
        </el-form-item>
        <el-form-item v-if="temp.type !== 3" label="路由路径">
          <el-input v-model="temp.path" />
        </el-form-item>
        <el-form-item v-if="temp.type === 2" label="组件路径">
          <el-input v-model="temp.component" placeholder="如: system/user" />
        </el-form-item>
        <el-form-item label="权限标识">
          <el-input v-model="temp.permission" placeholder="如: system.user" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="temp.sort" :min="0" />
        </el-form-item>
        <el-form-item label="隐藏">
          <el-switch v-model="temp.hidden" :active-value="1" :inactive-value="0" />
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
import { fetchMenuList, createMenu, updateMenu, deleteMenu } from '@/api/system'
import IconPicker from '@/components/IconPicker'

export default {
  name: 'MenuManage',
  components: { IconPicker },
  data() {
    return {
      list: [],
      listLoading: true,
      parentOptions: [],
      temp: {
        id: undefined, parent_id: 0, parent_id_arr: null, title: '', icon: '', path: '',
        component: '', permission: '', type: 1, hidden: 0, sort: 0, status: 1
      },
      dialogFormVisible: false,
      dialogStatus: '',
      rules: {
        title: [{ required: true, message: '请输入菜单标题', trigger: 'blur' }]
      }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    getList() {
      this.listLoading = true
      fetchMenuList().then(response => {
        this.list = response.data
        this.parentOptions = response.data
        this.listLoading = false
      })
    },
    resetTemp() {
      this.temp = {
        id: undefined, parent_id: 0, parent_id_arr: null, title: '', icon: '', path: '',
        component: '', permission: '', type: 1, hidden: 0, sort: 0, status: 1
      }
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
          createMenu(this.temp).then(() => {
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
          updateMenu(this.temp.id, this.temp).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({ title: '成功', message: '更新成功', type: 'success', duration: 2000 })
          })
        }
      })
    },
    handleDelete(row) {
      this.$confirm('确定删除该菜单?', '提示', { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }).then(() => {
        deleteMenu(row.id).then(() => {
          this.getList()
          this.$notify({ title: '成功', message: '删除成功', type: 'success', duration: 2000 })
        })
      })
    }
  }
}
</script>
