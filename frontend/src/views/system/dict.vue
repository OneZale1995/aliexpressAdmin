<template>
  <div class="app-container">
    <el-row :gutter="20">
      <!-- 左侧：字典类型 -->
      <el-col :span="10">
        <el-card>
          <div slot="header" style="display: flex; justify-content: space-between; align-items: center;">
            <span>字典类型</span>
            <el-button type="primary" size="mini" icon="el-icon-plus" @click="handleCreateType">新增</el-button>
          </div>
          <div style="margin-bottom: 10px;">
            <el-input v-model="typeQuery.name" placeholder="搜索字典名称" size="small" clearable style="width: 200px;" @keyup.enter.native="getTypeList">
              <el-button slot="append" icon="el-icon-search" @click="getTypeList" />
            </el-input>
          </div>
          <el-table :data="typeList" v-loading="typeLoading" border size="small" highlight-current-row @current-change="handleTypeSelect">
            <el-table-column label="字典名称" prop="name" />
            <el-table-column label="字典编码" prop="code" width="140" />
            <el-table-column label="状态" width="70" align="center">
              <template slot-scope="{row}">
                <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="mini">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="120" align="center">
              <template slot-scope="{row}">
                <el-button type="text" size="mini" @click="handleEditType(row)">编辑</el-button>
                <el-button type="text" size="mini" style="color: #f56c6c;" @click="handleDeleteType(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
          <pagination v-show="typeTotal > 0" :total="typeTotal" :page.sync="typeQuery.page" :limit.sync="typeQuery.limit" @pagination="getTypeList" />
        </el-card>
      </el-col>

      <!-- 右侧：字典数据 -->
      <el-col :span="14">
        <el-card>
          <div slot="header" style="display: flex; justify-content: space-between; align-items: center;">
            <span>字典数据 {{ currentType ? '- ' + currentType.name : '' }}</span>
            <el-button type="primary" size="mini" icon="el-icon-plus" :disabled="!currentType" @click="handleCreateData">新增</el-button>
          </div>
          <el-table :data="dataList" v-loading="dataLoading" border size="small">
            <el-table-column label="标签" prop="label" />
            <el-table-column label="值" prop="value" width="120" />
            <el-table-column label="排序" prop="sort" width="70" align="center" />
            <el-table-column label="状态" width="70" align="center">
              <template slot-scope="{row}">
                <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="mini">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="说明" prop="description" show-overflow-tooltip />
            <el-table-column label="操作" width="120" align="center">
              <template slot-scope="{row}">
                <el-button type="text" size="mini" @click="handleEditData(row)">编辑</el-button>
                <el-button type="text" size="mini" style="color: #f56c6c;" @click="handleDeleteData(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <!-- 字典类型弹窗 -->
    <el-dialog :title="typeDialog.status === 'create' ? '新增字典类型' : '编辑字典类型'" :visible.sync="typeDialog.visible" width="500px">
      <el-form ref="typeForm" :model="typeDialog.temp" :rules="typeRules" label-width="100px">
        <el-form-item label="字典名称" prop="name">
          <el-input v-model="typeDialog.temp.name" />
        </el-form-item>
        <el-form-item label="字典编码" prop="code">
          <el-input v-model="typeDialog.temp.code" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="typeDialog.temp.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="typeDialog.temp.description" type="textarea" />
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="typeDialog.visible = false">取消</el-button>
        <el-button type="primary" @click="submitType">确认</el-button>
      </div>
    </el-dialog>

    <!-- 字典数据弹窗 -->
    <el-dialog :title="dataDialog.status === 'create' ? '新增字典数据' : '编辑字典数据'" :visible.sync="dataDialog.visible" width="500px">
      <el-form ref="dataForm" :model="dataDialog.temp" :rules="dataRules" label-width="100px">
        <el-form-item label="标签" prop="label">
          <el-input v-model="dataDialog.temp.label" />
        </el-form-item>
        <el-form-item label="值" prop="value">
          <el-input v-model="dataDialog.temp.value" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="dataDialog.temp.sort" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="dataDialog.temp.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="dataDialog.temp.description" />
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="dataDialog.visible = false">取消</el-button>
        <el-button type="primary" @click="submitData">确认</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {
  fetchDictTypeList, createDictType, updateDictType, deleteDictType,
  fetchDictDataList, createDictData, updateDictData, deleteDictData
} from '@/api/system'
import Pagination from '@/components/Pagination'

export default {
  name: 'DictManage',
  components: { Pagination },
  data() {
    return {
      typeList: [],
      typeTotal: 0,
      typeLoading: false,
      typeQuery: { page: 1, limit: 20, name: '' },
      currentType: null,
      dataList: [],
      dataLoading: false,
      typeDialog: {
        visible: false,
        status: '',
        temp: { id: undefined, name: '', code: '', status: 1, description: '' }
      },
      dataDialog: {
        visible: false,
        status: '',
        temp: { id: undefined, dict_type_id: null, label: '', value: '', sort: 0, status: 1, description: '' }
      },
      typeRules: {
        name: [{ required: true, message: '请输入字典名称', trigger: 'blur' }],
        code: [{ required: true, message: '请输入字典编码', trigger: 'blur' }]
      },
      dataRules: {
        label: [{ required: true, message: '请输入标签', trigger: 'blur' }],
        value: [{ required: true, message: '请输入值', trigger: 'blur' }]
      }
    }
  },
  created() {
    this.getTypeList()
  },
  methods: {
    getTypeList() {
      this.typeLoading = true
      fetchDictTypeList(this.typeQuery).then(response => {
        this.typeList = response.data.items
        this.typeTotal = response.data.total
        this.typeLoading = false
      })
    },
    handleTypeSelect(row) {
      this.currentType = row
      if (row) this.getDataList()
    },
    getDataList() {
      if (!this.currentType) return
      this.dataLoading = true
      fetchDictDataList({ dict_type_id: this.currentType.id }).then(response => {
        this.dataList = response.data
        this.dataLoading = false
      })
    },
    handleCreateType() {
      this.typeDialog.temp = { id: undefined, name: '', code: '', status: 1, description: '' }
      this.typeDialog.status = 'create'
      this.typeDialog.visible = true
      this.$nextTick(() => this.$refs['typeForm'].clearValidate())
    },
    handleEditType(row) {
      this.typeDialog.temp = Object.assign({}, row)
      this.typeDialog.status = 'update'
      this.typeDialog.visible = true
      this.$nextTick(() => this.$refs['typeForm'].clearValidate())
    },
    submitType() {
      this.$refs['typeForm'].validate(valid => {
        if (valid) {
          const fn = this.typeDialog.status === 'create'
            ? createDictType(this.typeDialog.temp)
            : updateDictType(this.typeDialog.temp.id, this.typeDialog.temp)
          fn.then(() => {
            this.typeDialog.visible = false
            this.getTypeList()
            this.$message.success(this.typeDialog.status === 'create' ? '创建成功' : '更新成功')
          })
        }
      })
    },
    handleDeleteType(row) {
      this.$confirm('确定删除该字典类型?', '提示', { type: 'warning' }).then(() => {
        deleteDictType(row.id).then(() => {
          this.getTypeList()
          this.currentType = null
          this.dataList = []
          this.$message.success('删除成功')
        })
      })
    },
    handleCreateData() {
      this.dataDialog.temp = { id: undefined, dict_type_id: this.currentType.id, label: '', value: '', sort: 0, status: 1, description: '' }
      this.dataDialog.status = 'create'
      this.dataDialog.visible = true
      this.$nextTick(() => this.$refs['dataForm'].clearValidate())
    },
    handleEditData(row) {
      this.dataDialog.temp = Object.assign({}, row)
      this.dataDialog.status = 'update'
      this.dataDialog.visible = true
      this.$nextTick(() => this.$refs['dataForm'].clearValidate())
    },
    submitData() {
      this.$refs['dataForm'].validate(valid => {
        if (valid) {
          const fn = this.dataDialog.status === 'create'
            ? createDictData(this.dataDialog.temp)
            : updateDictData(this.dataDialog.temp.id, this.dataDialog.temp)
          fn.then(() => {
            this.dataDialog.visible = false
            this.getDataList()
            this.$message.success(this.dataDialog.status === 'create' ? '创建成功' : '更新成功')
          })
        }
      })
    },
    handleDeleteData(row) {
      this.$confirm('确定删除该字典数据?', '提示', { type: 'warning' }).then(() => {
        deleteDictData(row.id).then(() => {
          this.getDataList()
          this.$message.success('删除成功')
        })
      })
    }
  }
}
</script>
