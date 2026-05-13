<template>
  <div class="app-container">
    <div class="filter-container">
      <el-select
        v-model="listQuery.shop_id"
        placeholder="选择店铺"
        clearable
        filterable
        class="filter-item"
        style="width: 200px;"
        @change="handleFilter"
      >
        <el-option v-for="s in shopOptions" :key="s.id" :label="s.name" :value="s.id" />
      </el-select>
      <el-input
        v-model="listQuery.keyword"
        placeholder="商品ID / 标题"
        style="width: 220px;"
        class="filter-item"
        @keyup.enter.native="handleFilter"
      />
      <el-input
        v-model="listQuery.category_id"
        placeholder="类目ID"
        style="width: 140px;"
        class="filter-item"
        @keyup.enter.native="handleFilter"
      />
      <el-select
        v-model="listQuery.status_type"
        placeholder="状态筛选"
        clearable
        class="filter-item"
        style="width: 140px;"
      >
        <el-option label="上架 (onSelling)" value="onSelling" />
        <el-option label="下架 (offline)" value="offline" />
        <el-option label="编辑中 (editing)" value="editing" />
      </el-select>
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索</el-button>
      <el-button class="filter-item" type="success" icon="el-icon-download" :loading="exporting" @click="handleExport">导出商品</el-button>
    </div>

    <el-alert
      v-if="exportTip"
      :title="exportTip"
      type="info"
      show-icon
      :closable="true"
      style="margin-bottom: 12px;"
      @close="exportTip = ''"
    />

    <el-card shadow="never" style="margin-bottom: 16px;">
      <div slot="header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <span>导出历史任务</span>
        <div style="font-size: 12px; color: #909399;">
          当前筛选商品数：{{ exportHistoryCurrentTotal }}
        </div>
      </div>

      <el-table v-loading="exportHistoryLoading" :data="exportHistoryList" border size="mini" empty-text="暂无导出历史">
        <el-table-column label="导出条数" align="center" width="110">
          <template slot-scope="{row}">
            {{ row.exported_rows || 0 }}
          </template>
        </el-table-column>
        <el-table-column label="导出日期" align="center" width="170">
          <template slot-scope="{row}">
            {{ row.exported_at || row.created_at || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="导出范围" min-width="220" show-overflow-tooltip>
          <template slot-scope="{row}">
            {{ buildExportScopeLabel(row.options) }}
          </template>
        </el-table-column>
        <el-table-column label="状态" align="center" width="120">
          <template slot-scope="{row}">
            <el-tag :type="exportStatusTagType(row.status)" size="mini">{{ exportStatusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="当前数据" align="center" width="120">
          <template slot-scope="{row}">
            <el-tag v-if="row.can_reuse" type="success" size="mini">可直接复用</el-tag>
            <el-tag v-else-if="row.matches_current_scope" type="info" size="mini">数量已变化</el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="文件" min-width="220" show-overflow-tooltip>
          <template slot-scope="{row}">
            {{ row.file_name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="操作" align="center" width="180" fixed="right">
          <template slot-scope="{row}">
            <el-button
              type="text"
              size="mini"
              :disabled="row.status !== 'completed' || !row.file_exists"
              @click="downloadExportFile(row)"
            >下载</el-button>
            <el-button
              type="text"
              size="mini"
              style="color:#F56C6C;"
              :disabled="row.status === 'pending' || row.status === 'running'"
              @click="handleDeleteExport(row)"
            >删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script>
import { fetchShopList } from '@/api/shop'
import {
  startProductExport,
  fetchProductExportProgress,
  getProductExportHistory,
  downloadProductExport,
  deleteProductExport
} from '@/api/product'

export default {
  name: 'ProductExportManage',
  data() {
    return {
      exportHistoryLoading: false,
      exporting: false,
      exportTip: '',
      exportTaskId: null,
      exportPollingTimer: null,
      exportHistoryList: [],
      exportHistoryCurrentTotal: 0,
      shopOptions: [],
      listQuery: {
        shop_id: undefined,
        keyword: '',
        category_id: '',
        status_type: undefined
      }
    }
  },
  created() {
    this.loadShopOptions()
    this.getExportHistory()
  },
  beforeDestroy() {
    this.stopExportPolling()
  },
  methods: {
    loadShopOptions() {
      fetchShopList({ page: 1, limit: 200 }).then(res => {
        this.shopOptions = (res.data && res.data.items) || []
      }).catch(() => {})
    },
    getExportHistory() {
      this.exportHistoryLoading = true
      getProductExportHistory({
        ...this.buildExportQuery(),
        page: 1,
        limit: 10
      }).then(res => {
        this.exportHistoryList = (res.data && res.data.items) || []
        this.exportHistoryCurrentTotal = Number((res.data && res.data.current_total_rows) || 0)
      }).finally(() => {
        this.exportHistoryLoading = false
      })
    },
    handleFilter() {
      this.getExportHistory()
    },
    buildExportQuery() {
      const query = {}
      if (this.listQuery.shop_id) query.shop_id = this.listQuery.shop_id
      if (this.listQuery.keyword) query.keyword = this.listQuery.keyword
      if (this.listQuery.category_id) query.category_id = this.listQuery.category_id
      if (this.listQuery.status_type) query.status_type = this.listQuery.status_type
      return query
    },
    handleExport() {
      this.exporting = true
      startProductExport(this.buildExportQuery()).then(res => {
        this.exportTaskId = res.data && res.data.id ? res.data.id : (res.data && res.data.task_id ? res.data.task_id : null)
        this.exportTip = res.message || '导出任务已启动'
        this.getExportHistory()
        this.pollExportProgress()
        this.startExportPolling()
      }).catch(() => {
        this.$message.error('导出任务启动失败')
        this.exporting = false
      })
    },
    handleDeleteExport(row) {
      this.$confirm('删除后该导出文件和记录都会移除，之后才会重新生成新的导出任务。是否继续？', '删除导出记录', {
        confirmButtonText: '确定删除',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        return deleteProductExport({ task_id: row.id })
      }).then(res => {
        if (this.exportTaskId === row.id) {
          this.exportTaskId = null
          this.exportTip = ''
          this.stopExportPolling()
        }
        this.$message.success((res && res.message) || '导出记录已删除')
        this.getExportHistory()
      }).catch(err => {
        if (err === 'cancel' || err === 'close') return
        this.$message.error((err && err.message) || '删除导出记录失败')
      })
    },
    startExportPolling() {
      this.stopExportPolling()
      this.exportPollingTimer = setInterval(() => {
        this.pollExportProgress()
      }, 3000)
    },
    stopExportPolling() {
      if (this.exportPollingTimer) {
        clearInterval(this.exportPollingTimer)
        this.exportPollingTimer = null
      }
    },
    pollExportProgress() {
      if (!this.exportTaskId) return

      fetchProductExportProgress({ task_id: this.exportTaskId }).then(res => {
        const task = res.data || {}
        const progress = Number(task.progress || 0)
        const processedRows = Number(task.processed_rows || 0)
        const totalRows = Number(task.total_rows || 0)

        this.exportTip = task.message || `导出中，已处理 ${processedRows} / ${totalRows} 条商品（${progress}%）`

        if (task.status === 'completed') {
          this.stopExportPolling()
          this.exportTip = task.file_name ? `导出完成，正在下载 ${task.file_name}` : '导出完成，正在下载文件'
          this.downloadExportFile(task)
          return
        }

        if (task.status === 'failed') {
          this.stopExportPolling()
          this.exporting = false
          this.$message.error(task.message || '导出失败')
        }
      }).catch(() => {
        this.exportTip = '导出进度查询失败，正在重试...'
      })
    },
    downloadExportFile(task) {
      downloadProductExport({ task_id: task.id || this.exportTaskId }).then(response => {
        const contentType = response.headers && response.headers['content-type'] ? response.headers['content-type'] : 'application/octet-stream'
        const blob = new Blob([response.data], { type: contentType })
        const url = URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = task.file_name || ('AliExpress_products_' + new Date().toISOString().slice(0, 10) + '.csv')
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        URL.revokeObjectURL(url)
        this.$message.success('导出完成')
        this.getExportHistory()
      }).catch(() => {
        this.$message.error('导出文件下载失败')
      }).finally(() => {
        this.exporting = false
      })
    },
    buildExportScopeLabel(options) {
      const exportOptions = options || {}
      const parts = []
      if (exportOptions.shop_id) parts.push(`店铺#${exportOptions.shop_id}`)
      if (exportOptions.keyword) parts.push(`关键词:${exportOptions.keyword}`)
      if (exportOptions.category_id) parts.push(`类目:${exportOptions.category_id}`)
      if (exportOptions.status_type) parts.push(`状态:${this.statusLabel(exportOptions.status_type)}`)
      return parts.length ? parts.join(' / ') : '全部商品'
    },
    exportStatusTagType(status) {
      const map = { completed: 'success', failed: 'danger', running: 'warning', pending: 'info' }
      return map[status] || 'info'
    },
    exportStatusLabel(status) {
      const map = { completed: '已完成', failed: '失败', running: '导出中', pending: '待执行' }
      return map[status] || status || '-'
    },
    statusLabel(status) {
      const map = { onSelling: '上架', offline: '下架', editing: '编辑中' }
      return map[status] || status || '-'
    }
  }
}
</script>
