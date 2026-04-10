<template>
  <div class="app-container">
    <div class="filter-container">
      <el-input v-model="listQuery.user_name" placeholder="用户名" style="width: 150px;" class="filter-item" clearable />
      <el-input v-model="listQuery.ip" placeholder="IP地址" style="width: 150px;" class="filter-item" clearable />
      <el-select v-model="listQuery.status" placeholder="状态" clearable class="filter-item" style="width: 120px;">
        <el-option label="成功" :value="1" />
        <el-option label="失败" :value="0" />
      </el-select>
      <el-date-picker
        v-model="dateRange"
        type="daterange"
        range-separator="至"
        start-placeholder="开始日期"
        end-placeholder="结束日期"
        value-format="yyyy-MM-dd"
        class="filter-item"
        style="width: 300px;"
        @change="handleDateChange"
      />
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索</el-button>
      <el-button class="filter-item" type="danger" icon="el-icon-delete" @click="handleClear">清理日志</el-button>
    </div>

    <el-table v-loading="listLoading" :data="list" border fit>
      <el-table-column label="ID" prop="id" width="80" align="center" />
      <el-table-column label="用户名" prop="user_name" width="120" />
      <el-table-column label="IP地址" prop="ip" width="140" />
      <el-table-column label="状态" width="80" align="center">
        <template slot-scope="{row}">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="mini">{{ row.status === 1 ? '成功' : '失败' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="提示信息" prop="message" show-overflow-tooltip />
      <el-table-column label="浏览器/设备" prop="user_agent" show-overflow-tooltip />
      <el-table-column label="登录时间" prop="created_at" width="170" />
      <el-table-column label="操作" align="center" width="80">
        <template slot-scope="{row}">
          <el-button type="text" size="mini" style="color: #f56c6c;" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <pagination v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit" @pagination="getList" />
  </div>
</template>

<script>
import { fetchLoginLogList, deleteLoginLog, clearLoginLogs } from '@/api/system'
import Pagination from '@/components/Pagination'

export default {
  name: 'LoginLog',
  components: { Pagination },
  data() {
    return {
      list: [],
      total: 0,
      listLoading: true,
      listQuery: { page: 1, limit: 20, user_name: '', ip: '', status: '', start_date: '', end_date: '' },
      dateRange: null
    }
  },
  created() {
    this.getList()
  },
  methods: {
    getList() {
      this.listLoading = true
      fetchLoginLogList(this.listQuery).then(response => {
        this.list = response.data.items
        this.total = response.data.total
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
    },
    handleDateChange(val) {
      if (val) {
        this.listQuery.start_date = val[0]
        this.listQuery.end_date = val[1]
      } else {
        this.listQuery.start_date = ''
        this.listQuery.end_date = ''
      }
    },
    handleDelete(row) {
      this.$confirm('确定删除?', '提示', { type: 'warning' }).then(() => {
        deleteLoginLog(row.id).then(() => {
          this.getList()
          this.$message.success('删除成功')
        })
      })
    },
    handleClear() {
      this.$prompt('请输入要清理多少天前的日志', '清理登录日志', {
        inputValue: '90',
        inputPattern: /^\d+$/,
        inputErrorMessage: '请输入正整数'
      }).then(({ value }) => {
        clearLoginLogs(parseInt(value)).then(() => {
          this.getList()
          this.$message.success('清理成功')
        })
      })
    }
  }
}
</script>
