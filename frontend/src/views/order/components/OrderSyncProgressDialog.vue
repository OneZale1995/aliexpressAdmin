<template>
  <el-dialog title="同步进度" :visible.sync="dialogVisible" width="620px" :close-on-click-modal="false">
    <el-progress :percentage="syncProgress.progress" :status="syncProgress.status === 'failed' ? 'exception' : 'success'" />
    <div style="margin-top: 12px; line-height: 1.8; color: #666; font-size: 13px;">
      <div>状态：{{ getSyncStatusLabel(syncProgress.status) }}</div>
      <div>店铺进度：{{ syncProgress.processed_shops }}/{{ syncProgress.total_shops }}</div>
      <div>已同步订单：{{ syncProgress.synced_orders }}</div>
      <div>失败店铺：{{ syncProgress.failed_shops }}</div>
      <div v-if="syncProgress.current_shop_name">当前店铺：{{ syncProgress.current_shop_name }}</div>
    </div>

    <el-table :data="syncProgress.details || []" size="mini" border style="margin-top: 14px;">
      <el-table-column label="店铺" prop="shop_name" min-width="120" />
      <el-table-column label="同步条数" prop="synced" width="100" align="center" />
      <el-table-column label="结果" min-width="220">
        <template slot-scope="{row}">
          <span v-if="row.status === 'failed'" style="color: #f56c6c;">{{ row.error || '失败' }}</span>
          <span v-else-if="row.status === 'skipped'" style="color: #e6a23c;">{{ row.error || '已跳过' }}</span>
          <span v-else style="color: #67c23a;">成功</span>
        </template>
      </el-table-column>
    </el-table>

    <div slot="footer">
      <el-button @click="dialogVisible = false">关闭</el-button>
    </div>
  </el-dialog>
</template>

<script>
import { getSyncStatusLabel } from '../utils'

export default {
  name: 'OrderSyncProgressDialog',
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    syncProgress: {
      type: Object,
      required: true
    },
    dictLabelMap: {
      type: Object,
      default: () => ({})
    }
  },
  computed: {
    dialogVisible: {
      get() {
        return this.visible
      },
      set(value) {
        this.$emit('update:visible', value)
      }
    }
  },
  methods: {
    getSyncStatusLabel(status) {
      return getSyncStatusLabel(status, this.dictLabelMap)
    }
  }
}
</script>
