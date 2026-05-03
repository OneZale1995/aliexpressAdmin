<template>
  <div class="app-container">
    <div class="filter-container">
      <el-input v-model="listQuery.user_name" placeholder="操作用户" style="width: 150px;" class="filter-item" />
      <el-input v-model="listQuery.path" placeholder="请求路径" style="width: 200px;" class="filter-item" />
      <el-select v-model="listQuery.is_success" placeholder="响应状态" clearable style="width: 120px" class="filter-item">
        <el-option label="成功" :value="1" />
        <el-option label="失败" :value="0" />
      </el-select>
      <el-select v-model="listQuery.min_duration" placeholder="慢请求" clearable style="width: 130px" class="filter-item">
        <el-option label=">500ms" :value="500" />
        <el-option label=">1s" :value="1000" />
        <el-option label=">3s" :value="3000" />
        <el-option label=">5s" :value="5000" />
      </el-select>
      <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" class="filter-item" value-format="yyyy-MM-dd" style="width: 280px;" />
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索</el-button>
      <el-button class="filter-item" type="danger" icon="el-icon-delete" @click="handleClear">清除日志</el-button>
    </div>

    <el-table v-loading="listLoading" :data="list" border fit highlight-current-row style="width: 100%">
      <el-table-column label="序号" type="index" :index="i => (listQuery.page - 1) * listQuery.limit + i + 1" align="center" width="80" />
      <el-table-column label="操作用户" prop="user_name" align="center" width="120" />
      <el-table-column label="请求路径" prop="path" />
      <el-table-column label="状态" align="center" width="80">
        <template slot-scope="{row}">
          <el-tag :type="row.is_success ? 'success' : 'danger'" size="mini">{{ row.is_success ? '成功' : '失败' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="业务码" prop="business_code" align="center" width="90" />
      <el-table-column label="耗时" align="center" width="100">
        <template slot-scope="{row}">
          <span :style="{ color: row.duration > 3000 ? '#F56C6C' : row.duration > 1000 ? '#E6A23C' : '' }">{{ row.duration }}ms</span>
        </template>
      </el-table-column>
      <el-table-column label="IP" prop="ip" width="140" align="center" />
      <el-table-column label="操作时间" prop="created_at" width="180" align="center" />
      <el-table-column label="操作" align="center" width="120">
        <template slot-scope="{row}">
          <el-button type="primary" size="mini" @click="handleDetail(row)">详情</el-button>
        </template>
      </el-table-column>
    </el-table>

    <pagination v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit" @pagination="getList" />

    <!-- 详情对话框 -->
    <el-dialog title="日志详情" :visible.sync="detailVisible" width="1100px" top="5vh">
      <el-descriptions :column="2" border size="small">
        <el-descriptions-item label="操作用户">{{ detail.user_name }}</el-descriptions-item>
        <el-descriptions-item label="IP地址">{{ detail.ip }}</el-descriptions-item>
        <el-descriptions-item label="请求路径">{{ detail.path }}</el-descriptions-item>
        <el-descriptions-item label="耗时">
          <span :style="{ color: detail.duration > 3000 ? '#F56C6C' : detail.duration > 1000 ? '#E6A23C' : '' }">{{ detail.duration }}ms</span>
        </el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="detail.is_success ? 'success' : 'danger'" size="mini">{{ detail.is_success ? '成功' : '失败' }}</el-tag>
          <span v-if="detail.business_code" style="margin-left:6px;color:#909399;">{{ detail.business_code }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="操作时间">{{ detail.created_at }}</el-descriptions-item>
      </el-descriptions>
      <div style="display:flex;gap:12px;margin-top:14px;">
        <div style="flex:1;min-width:0;">
          <div style="font-weight:bold;margin-bottom:6px;font-size:13px;">请求参数</div>
          <pre class="log-json-block">{{ formatJson(detail.input) }}</pre>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:bold;margin-bottom:6px;font-size:13px;">响应内容</div>
          <pre class="log-json-block">{{ formatJson(detail.response) }}</pre>
        </div>
      </div>
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
      listQuery: { page: 1, limit: 20, user_name: '', path: '', is_success: '', min_duration: '', start_date: '', end_date: '' },
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

<style scoped>
.log-json-block {
  margin: 0;
  padding: 10px;
  background: #f5f7fa;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  font-size: 12px;
  line-height: 1.6;
  white-space: pre-wrap;
  word-break: break-all;
  max-height: 500px;
  overflow: auto;
}
</style>
