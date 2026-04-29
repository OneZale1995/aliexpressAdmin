<template>
  <div class="app-container">
    <el-tabs v-model="activeGroup" @tab-click="handleTabClick">
      <el-tab-pane v-for="group in groups" :key="group" :label="groupLabels[group] || group" :name="group" />
    </el-tabs>

    <div style="margin-bottom: 15px;">
      <el-button type="primary" size="small" icon="el-icon-plus" @click="handleCreate">添加配置</el-button>
      <el-button type="success" size="small" icon="el-icon-check" @click="handleBatchSave">保存修改</el-button>
    </div>

    <el-table :data="filteredList" border fit>
      <el-table-column label="配置名称" prop="name" width="180" />
      <el-table-column label="配置键" prop="key" width="200" />
      <el-table-column label="配置值" min-width="300">
        <template slot-scope="{row}">
          <el-input v-if="row.type === 'text'" v-model="row.value" size="small" />
          <el-input v-else-if="row.type === 'textarea'" v-model="row.value" type="textarea" :rows="2" size="small" />
          <el-input-number v-else-if="row.type === 'number'" v-model="row.value" size="small" />
          <el-switch
            v-else-if="row.type === 'switch'"
            v-model="row.value"
            :active-value="parseSwitchOptions(row).activeValue"
            :inactive-value="parseSwitchOptions(row).inactiveValue"
            :active-text="parseSwitchOptions(row).activeText"
            :inactive-text="parseSwitchOptions(row).inactiveText"
          />
          <div v-else-if="row.type === 'image'" style="display: flex; align-items: center; gap: 8px;">
            <el-image v-if="row.value" :src="row.value" style="width: 60px; height: 60px;" fit="cover" />
            <el-input v-model="row.value" size="small" placeholder="图片URL" />
          </div>
          <el-input v-else v-model="row.value" size="small" />
        </template>
      </el-table-column>
      <el-table-column label="说明" prop="description" width="200" show-overflow-tooltip />
      <el-table-column label="操作" align="center" width="120">
        <template slot-scope="{row}">
          <el-button type="text" size="mini" @click="handleEdit(row)">编辑</el-button>
          <el-button type="text" size="mini" style="color: #f56c6c;" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog :title="dialogStatus === 'create' ? '添加配置' : '编辑配置'" :visible.sync="dialogVisible" width="500px">
      <el-form ref="dataForm" :model="temp" :rules="rules" label-width="100px">
        <el-form-item label="分组">
          <el-input v-model="temp.group" placeholder="如: site, upload" />
        </el-form-item>
        <el-form-item label="配置键" prop="key">
          <el-input v-model="temp.key" placeholder="如: site_name" />
        </el-form-item>
        <el-form-item label="配置名称" prop="name">
          <el-input v-model="temp.name" />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="temp.type" style="width: 100%;">
            <el-option label="文本" value="text" />
            <el-option label="多行文本" value="textarea" />
            <el-option label="数字" value="number" />
            <el-option label="开关" value="switch" />
            <el-option label="图片" value="image" />
          </el-select>
        </el-form-item>
        <el-form-item label="配置值">
          <el-input v-model="temp.value" />
        </el-form-item>
        <el-form-item label="说明">
          <el-input v-model="temp.description" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="temp.sort" :min="0" />
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitForm">确认</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import { fetchConfigList, createConfig, updateConfig, deleteConfig, batchSaveConfig } from '@/api/system'

export default {
  name: 'SystemConfig',
  data() {
    return {
      list: [],
      activeGroup: 'site',
      groups: ['site', 'upload'],
      groupLabels: { site: '网站设置', upload: '上传设置', finance: '财务设置', chinapost: '中国邮政' },
      dialogVisible: false,
      dialogStatus: '',
      temp: { id: undefined, group: 'default', key: '', name: '', value: '', type: 'text', options: '', description: '', sort: 0 },
      rules: {
        key: [{ required: true, message: '请输入配置键', trigger: 'blur' }],
        name: [{ required: true, message: '请输入配置名称', trigger: 'blur' }]
      }
    }
  },
  computed: {
    filteredList() {
      return this.list.filter(item => item.group === this.activeGroup)
    }
  },
  created() {
    this.getList()
  },
  methods: {
    getList() {
      fetchConfigList().then(response => {
        this.list = response.data
        // 动态收集分组
        const groupSet = new Set(this.list.map(item => item.group))
        this.groups = [...groupSet]
        if (!this.groups.includes(this.activeGroup) && this.groups.length > 0) {
          this.activeGroup = this.groups[0]
        }
      })
    },
    handleTabClick() {},
    handleCreate() {
      this.temp = { id: undefined, group: this.activeGroup, key: '', name: '', value: '', type: 'text', options: '', description: '', sort: 0 }
      this.dialogStatus = 'create'
      this.dialogVisible = true
      this.$nextTick(() => this.$refs['dataForm'].clearValidate())
    },
    handleEdit(row) {
      this.temp = Object.assign({}, row)
      this.dialogStatus = 'update'
      this.dialogVisible = true
      this.$nextTick(() => this.$refs['dataForm'].clearValidate())
    },
    submitForm() {
      this.$refs['dataForm'].validate(valid => {
        if (valid) {
          if (this.dialogStatus === 'create') {
            createConfig(this.temp).then(() => {
              this.dialogVisible = false
              this.getList()
              this.$message.success('创建成功')
            })
          } else {
            updateConfig(this.temp.id, this.temp).then(() => {
              this.dialogVisible = false
              this.getList()
              this.$message.success('更新成功')
            })
          }
        }
      })
    },
    handleDelete(row) {
      this.$confirm('确定删除该配置?', '提示', { type: 'warning' }).then(() => {
        deleteConfig(row.id).then(() => {
          this.getList()
          this.$message.success('删除成功')
        })
      })
    },
    handleBatchSave() {
      const configs = this.filteredList.map(item => ({ key: item.key, value: item.value }))
      batchSaveConfig(configs).then(() => {
        this.$message.success('保存成功')
      })
    },
    parseSwitchOptions(row) {
      const defaults = { activeValue: '1', inactiveValue: '0', activeText: '', inactiveText: '' }
      if (!row.options) return defaults
      try {
        return { ...defaults, ...JSON.parse(row.options) }
      } catch {
        return defaults
      }
    }
  }
}
</script>
