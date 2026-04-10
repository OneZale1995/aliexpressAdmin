<template>
  <div class="app-container">
    <div class="filter-container">
      <el-input v-model="listQuery.user_name" placeholder="操作用户" style="width: 150px;" class="filter-item" />
      <el-input v-model="listQuery.path" placeholder="请求路径" style="width: 200px;" class="filter-item" />
      <el-select v-model="listQuery.method" placeholder="请求方法" clearable style="width: 120px" class="filter-item">
        <el-option label="POST" value="POST" />
        <el-option label="PUT" value="PUT" />
        <el-option label="DELETE" value="DELETE" />
        <el-option label="PATCH" value="PATCH" />
      </el-select>
      <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" class="filter-item" value-format="yyyy-MM-dd" style="width: 280px;" />
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索</el-button>
      <el-button class="filter-item" type="danger" icon="el-icon-delete" @click="handleClear">清除日志</el-button>
    </div>

    <el-table v-loading="listLoading" :data="list" border fit highlight-current-row style="width: 100%">
      <el-table-column label="序号" type="index" :index="i => (listQuery.page - 1) * listQuery.limit + i + 1" align="center" width="80" />
      <el-table-column label="操作用户" prop="user_name" align="center" width="120" />
      <el-table-column label="请求方法" align="center" width="100">
        <template slot-scope="{row}">
          <el-tag :type="methodTagType(row.method)" size="mini">{{ row.method }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="请求路径" prop="path" />
      <el-table-column label="IP" prop="ip" width="140" align="center" />
      <el-table-column label="耗时(ms)" prop="duration" width="100" align="center" />
      <el-table-column label="操作时间" prop="created_at" width="180" align="center" />
      <el-table-column label="操作" align="center" width="120">
        <template slot-scope="{row}">
          <el-button type="primary" size="mini" @click="handleDetail(row)">详情</el-button>
        </template>
      </el-table-column>
    </el-table>

    <pagination v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit" @pagination="getList" />

    <!-- 详情对话框 -->
    <el-dialog title="日志详情" :visible.sync="detailVisible" width="700px">
      <el-descriptions :column="2" border>
        <el-descriptions-item label="操作用户">{{ detail.user_name }}</el-descriptions-item>
        <el-descriptions-item label="IP地址">{{ detail.ip }}</el-descriptions-item>
        <el-descriptions-item label="请求方法">{{ detail.method }}</el-descriptions-item>
        <el-descriptions-item label="耗时">{{ detail.duration }}ms</el-descriptions-item>
        <el-descriptions-item label="请求路径" :span="2">{{ detail.path }}</el-descriptions-item>
        <el-descriptions-item label="请求参数" :span="2">
          <pre style="margin:0;white-space:pre-wrap;word-break: break-all;">{{ formatJson(detail.input) }}</pre>
        </el-descriptions-item>
        <el-descriptions-item label="响应内容" :span="2">
          <pre style="margin:0;white-space:pre-wrap;word-break: break-all;max-height:300px;overflow:auto;">{{ formatJson(detail.response) }}</pre>
        </el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script>
import { fetchLogList, clearLogs } from '@/api/system'
import Pagination from '@/components/Pagination'

export default {
  name: 'OperationLog',
  components: { Pagination },
  data() {
    return {
      list: [],
      total: 0,
      listLoading: true,
      listQuery: { page: 1, limit: 20, user_name: '', path: '', method: '', start_date: '', end_date: '' },
      dateRange: [],
      detail: {},
      detailVisible: false
    }
  },
  created() {
    this.getList()
  },
  methods: {
    getList() {
      this.listLoading = true
      if (this.dateRange && this.dateRange.length === 2) {
        this.listQuery.start_date = this.dateRange[0]
        this.listQuery.end_date = this.dateRange[1]
      } else {
        this.listQuery.start_date = ''
        this.listQuery.end_date = ''
      }
      fetchLogList(this.listQuery).then(response => {
        this.list = response.data.items
        this.total = response.data.total
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
    },
    handleDetail(row) {
      this.detail = row
      this.detailVisible = true
    },
    handleClear() {
      this.$prompt('请输入要清除多少天前的日志', '清除日志', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        inputValue: '30',
        inputPattern: /^\d+$/,
        inputErrorMessage: '请输入正整数'
      }).then(({ value }) => {
        clearLogs(value).then(response => {
          this.$notify({ title: '成功', message: response.message || '清除成功', type: 'success', duration: 2000 })
          this.getList()
        })
      })
    },
    methodTagType(method) {
      const map = { POST: 'success', PUT: 'warning', DELETE: 'danger', PATCH: '' }
      return map[method] || ''
    },
    formatJson(str) {
      if (!str) return ''
      try {
        return JSON.stringify(JSON.parse(str), null, 2)
      } catch (e) {
        return str
      }
    }
  }
}
</script>
