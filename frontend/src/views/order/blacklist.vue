<template>
  <div class="app-container">
    <div class="filter-container">
      <el-select v-if="isSuperAdmin" v-model="listQuery.team_id" placeholder="选择团队" clearable class="filter-item" style="width: 180px;" @change="handleFilter">
        <el-option v-for="t in teamOptions" :key="t.id" :label="t.name" :value="t.id" />
      </el-select>
      <el-input v-model="listQuery.name" placeholder="客户姓名" style="width: 200px;" class="filter-item" clearable @keyup.enter.native="handleFilter" @clear="handleFilter" />
      <el-input v-model="listQuery.phone" placeholder="客户电话" style="width: 200px;" class="filter-item" clearable @keyup.enter.native="handleFilter" @clear="handleFilter" />
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索</el-button>
      <el-button class="filter-item" type="primary" icon="el-icon-plus" @click="handleCreate">添加黑名单</el-button>
    </div>

    <el-table v-loading="listLoading" :data="list" border fit highlight-current-row style="width: 100%">
      <el-table-column label="序号" type="index" :index="i => (listQuery.page - 1) * listQuery.limit + i + 1" align="center" width="80" />
      <el-table-column v-if="isSuperAdmin" label="团队" align="center" width="120">
        <template slot-scope="{row}">
          {{ row.team ? row.team.name : '-' }}
        </template>
      </el-table-column>
      <el-table-column label="客户姓名" align="center" width="150">
        <template slot-scope="{row}">
          {{ row.name || '-' }}
        </template>
      </el-table-column>
      <el-table-column label="客户电话" align="center" width="150">
        <template slot-scope="{row}">
          {{ row.phone || '-' }}
        </template>
      </el-table-column>
      <el-table-column label="备注" align="center" min-width="200">
        <template slot-scope="{row}">
          {{ row.remark || '-' }}
        </template>
      </el-table-column>
      <el-table-column label="创建人" align="center" width="120">
        <template slot-scope="{row}">
          {{ row.creator ? (row.creator.nickname || row.creator.username) : '-' }}
        </template>
      </el-table-column>
      <el-table-column label="修改人" align="center" width="120">
        <template slot-scope="{row}">
          {{ row.updater ? (row.updater.nickname || row.updater.username) : '-' }}
        </template>
      </el-table-column>
      <el-table-column label="创建时间" prop="created_at" align="center" width="180" />
      <el-table-column label="操作" align="center" width="160">
        <template slot-scope="{row}">
          <el-button type="primary" size="mini" @click="handleUpdate(row)">编辑</el-button>
          <el-button type="danger" size="mini" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <pagination v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit" @pagination="getList" />

    <el-dialog :title="dialogStatus === 'create' ? '添加黑名单' : '编辑黑名单'" :visible.sync="dialogFormVisible" width="500px">
      <el-form ref="dataForm" :rules="rules" :model="temp" label-position="left" label-width="100px" style="width: 360px; margin-left:30px;">
        <el-form-item v-if="showTeamSelect" label="所属团队" prop="team_id">
          <el-select v-model="temp.team_id" placeholder="请选择团队" style="width: 100%">
            <el-option v-for="t in teamOptions" :key="t.id" :label="t.name" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="客户姓名" prop="name">
          <el-input v-model="temp.name" placeholder="姓名和电话至少填写一项" />
        </el-form-item>
        <el-form-item label="客户电话" prop="phone">
          <el-input v-model="temp.phone" placeholder="姓名和电话至少填写一项" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="temp.remark" type="textarea" :rows="3" />
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
import { fetchBlacklist, createBlacklist, updateBlacklist, deleteBlacklist } from '@/api/order'
import { fetchTeamList } from '@/api/shop'
import Pagination from '@/components/Pagination'
import { mapGetters } from 'vuex'

export default {
  name: 'OrderBlacklist',
  components: { Pagination },
  data() {
    const atLeastOneRequired = (rule, value, callback) => {
      if (!this.temp.name && !this.temp.phone) {
        callback(new Error('姓名和电话至少填写一项'))
      } else {
        callback()
      }
    }
    return {
      list: [],
      total: 0,
      listLoading: true,
      listQuery: { page: 1, limit: 20, name: '', phone: '', team_id: undefined },
      teamOptions: [],
      temp: { id: undefined, team_id: undefined, name: '', phone: '', remark: '' },
      dialogFormVisible: false,
      dialogStatus: '',
      rules: {
        name: [{ validator: atLeastOneRequired, trigger: 'blur' }],
        phone: [{ validator: atLeastOneRequired, trigger: 'blur' }]
      }
    }
  },
  computed: {
    ...mapGetters(['roles']),
    isSuperAdmin() {
      return this.roles && this.roles.includes('super-admin')
    },
    showTeamSelect() {
      return this.dialogStatus === 'create' && this.teamOptions.length > 1
    }
  },
  created() {
    this.loadTeams().then(() => this.getList())
  },
  methods: {
    loadTeams() {
      return fetchTeamList({ all: 1 }).then(response => {
        this.teamOptions = response.data.items || response.data || []
      })
    },
    getList() {
      this.listLoading = true
      const params = { ...this.listQuery }
      if (!this.isSuperAdmin) {
        delete params.team_id
      }
      fetchBlacklist(params).then(response => {
        this.list = response.data.items
        this.total = response.data.total
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
    },
    resetTemp() {
      this.temp = { id: undefined, team_id: undefined, name: '', phone: '', remark: '' }
      // 只有一个团队时自动填充
      if (this.teamOptions.length === 1) {
        this.temp.team_id = this.teamOptions[0].id
      }
    },
    handleCreate() {
      this.resetTemp()
      this.dialogStatus = 'create'
      this.dialogFormVisible = true
      this.$nextTick(() => {
        this.$refs['dataForm'].clearValidate()
        // 动态添加 team_id 校验规则
        if (this.showTeamSelect) {
          this.rules.team_id = [{ required: true, message: '请选择团队', trigger: 'change' }]
        }
      })
    },
    submitCreate() {
      this.$refs['dataForm'].validate((valid) => {
        if (valid) {
          const data = { ...this.temp }
          if (!this.showTeamSelect) {
            delete data.team_id
          }
          createBlacklist(data).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({ title: '成功', message: '创建成功', type: 'success', duration: 2000 })
          })
        }
      })
    },
    handleUpdate(row) {
      this.temp = Object.assign({}, row)
      this.dialogStatus = 'update'
      this.dialogFormVisible = true
      this.$nextTick(() => { this.$refs['dataForm'].clearValidate() })
    },
    submitUpdate() {
      this.$refs['dataForm'].validate((valid) => {
        if (valid) {
          updateBlacklist(this.temp).then(() => {
            this.dialogFormVisible = false
            this.getList()
            this.$notify({ title: '成功', message: '更新成功', type: 'success', duration: 2000 })
          })
        }
      })
    },
    handleDelete(row) {
      this.$confirm('确定删除该黑名单条目?', '提示', { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }).then(() => {
        deleteBlacklist({ id: row.id }).then(() => {
          this.getList()
          this.$notify({ title: '成功', message: '删除成功', type: 'success', duration: 2000 })
        })
      })
    }
  }
}
</script>
