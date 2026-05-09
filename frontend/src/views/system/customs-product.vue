<template>
  <div class="app-container">
    <el-card>
      <div slot="header" style="display: flex; justify-content: space-between; align-items: center;">
        <span>报关商品管理</span>
        <el-button type="primary" size="mini" icon="el-icon-plus" @click="handleCreate">新增</el-button>
      </div>
      <el-table :data="list" v-loading="loading" border size="small">
        <el-table-column label="中文名" prop="name_cn" />
        <el-table-column label="英文名" prop="name_en" />
        <el-table-column label="排序" prop="sort" width="80" align="center" />
        <el-table-column label="操作" width="120" align="center">
          <template slot-scope="{row}">
            <el-button type="text" size="mini" @click="handleEdit(row)">编辑</el-button>
            <el-button type="text" size="mini" style="color: #f56c6c;" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog :title="dialog.status === 'create' ? '新增报关商品' : '编辑报关商品'" :visible.sync="dialog.visible" width="500px">
      <el-form ref="form" :model="dialog.temp" :rules="rules" label-width="80px">
        <el-form-item label="中文名" prop="name_cn">
          <el-input v-model="dialog.temp.name_cn" placeholder="报关中文品名" />
        </el-form-item>
        <el-form-item label="英文名" prop="name_en">
          <el-input v-model="dialog.temp.name_en" placeholder="报关英文品名" />
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="dialog.temp.sort" :min="0" />
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="dialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="dialog.loading" @click="submitForm">确定</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import { fetchCustomsProductList, createCustomsProduct, updateCustomsProduct, deleteCustomsProduct } from '@/api/system'

export default {
  name: 'CustomsProduct',
  data() {
    return {
      loading: false,
      list: [],
      dialog: {
        visible: false,
        status: 'create',
        loading: false,
        temp: { name_cn: '', name_en: '', sort: 0 }
      },
      rules: {
        name_cn: [{ required: true, message: '请输入中文名', trigger: 'blur' }],
        name_en: [{ required: true, message: '请输入英文名', trigger: 'blur' }]
      }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    async getList() {
      this.loading = true
      try {
        const res = await fetchCustomsProductList()
        this.list = res.data.items || []
      } finally {
        this.loading = false
      }
    },
    handleCreate() {
      this.dialog.status = 'create'
      this.dialog.temp = { name_cn: '', name_en: '', sort: 0 }
      this.dialog.visible = true
      this.$nextTick(() => this.$refs.form && this.$refs.form.clearValidate())
    },
    handleEdit(row) {
      this.dialog.status = 'edit'
      this.dialog.temp = { id: row.id, name_cn: row.name_cn, name_en: row.name_en, sort: row.sort }
      this.dialog.visible = true
      this.$nextTick(() => this.$refs.form && this.$refs.form.clearValidate())
    },
    submitForm() {
      this.$refs.form.validate(async valid => {
        if (!valid) return
        this.dialog.loading = true
        try {
          if (this.dialog.status === 'create') {
            await createCustomsProduct(this.dialog.temp)
            this.$message.success('创建成功')
          } else {
            await updateCustomsProduct(this.dialog.temp)
            this.$message.success('更新成功')
          }
          this.dialog.visible = false
          this.getList()
        } finally {
          this.dialog.loading = false
        }
      })
    },
    handleDelete(row) {
      this.$confirm('确定删除该报关商品？', '提示', { type: 'warning' }).then(async () => {
        await deleteCustomsProduct({ id: row.id })
        this.$message.success('删除成功')
        this.getList()
      }).catch(() => {})
    }
  }
}
</script>
