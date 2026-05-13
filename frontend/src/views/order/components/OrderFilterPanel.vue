<template>
  <div class="order-filter-panel">
    <div class="status-tabs">
      <span
        v-for="tab in backendStatusTabs"
        :key="tab.key"
        :class="['status-tab', { active: listQuery.backend_status === tab.key }]"
        @click="$emit('switch-backend-status', tab.key)"
      >
        {{ tab.label }}（{{ backendStatusCounts[tab.countKey] || 0 }}）
      </span>
    </div>

    <div class="status-tabs backend-tabs">
      <span
        v-for="tab in statusTabs"
        :key="tab.key"
        :class="['status-tab', { active: listQuery.display_status === tab.key }]"
        @click="$emit('switch-status', tab.key)"
      >
        {{ tab.label }}（{{ statusCounts[tab.countKey] || 0 }}）
      </span>
    </div>

    <el-collapse v-model="filterCollapse">
      <el-collapse-item name="filter">
        <template slot="title"><i class="el-icon-search" /> 筛选搜索</template>
        <el-form :model="listQuery" inline class="filter-container" style="padding: 10px 0;">
          <el-form-item label="店铺">
            <el-select v-model="listQuery.shop_id" placeholder="选择店铺" clearable filterable style="width: 180px;">
              <el-option v-for="shop in shopOptions" :key="shop.id" :label="shop.name" :value="shop.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="店铺关键词">
            <el-input v-model="listQuery.shop_keyword" placeholder="店铺名/邮箱" style="width: 180px;" />
          </el-form-item>
          <el-form-item label="国际单号">
            <el-input v-model="listQuery.tracking_number" placeholder="国际单号/运单号" style="width: 180px;" />
          </el-form-item>
          <el-form-item label="下单日期">
            <el-date-picker
              v-model="orderDateRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="yyyy-MM-dd"
              style="width: 240px;"
            />
          </el-form-item>
          <el-form-item label="收件人">
            <el-input v-model="listQuery.receiver_name" placeholder="收件人姓名" style="width: 140px;" />
          </el-form-item>
          <el-form-item label="电话">
            <el-input v-model="listQuery.receiver_phone" placeholder="收件人电话" style="width: 140px;" />
          </el-form-item>
          <el-form-item label="订单号">
            <el-input v-model="listQuery.ae_order_id" placeholder="订单号" style="width: 180px;" />
          </el-form-item>
          <el-form-item label="备注">
            <el-input v-model="listQuery.seller_comment" placeholder="备注关键词" style="width: 140px;" />
          </el-form-item>
          <el-form-item label="后台备注">
            <el-input v-model="listQuery.admin_remark" placeholder="后台备注关键词" style="width: 160px;" />
          </el-form-item>
          <el-form-item label="买家姓名">
            <el-input v-model="listQuery.buyer_name" placeholder="买家姓名" style="width: 140px;" />
          </el-form-item>
          <el-form-item label="买家电话">
            <el-input v-model="listQuery.buyer_phone" placeholder="买家电话" style="width: 140px;" />
          </el-form-item>
          <el-form-item label="地址关键词">
            <el-input v-model="listQuery.address_keyword" placeholder="地址关键词" style="width: 180px;" />
          </el-form-item>
          <el-form-item label="采购图">
            <el-select v-model="listQuery.has_purchase_image" placeholder="全部" clearable style="width: 100px;">
              <el-option label="有" value="1" />
              <el-option label="无" value="0" />
            </el-select>
          </el-form-item>
          <el-form-item label="发货图">
            <el-select v-model="listQuery.has_shipping_image" placeholder="全部" clearable style="width: 100px;">
              <el-option label="有" value="1" />
              <el-option label="无" value="0" />
            </el-select>
          </el-form-item>
          <el-form-item label="采购日期">
            <el-date-picker
              v-model="purchaseRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="yyyy-MM-dd"
              style="width: 240px;"
            />
          </el-form-item>
          <el-form-item label="发货日期">
            <el-date-picker
              v-model="deliveryRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="yyyy-MM-dd"
              style="width: 240px;"
            />
          </el-form-item>
          <el-form-item label="争议">
            <el-select v-model="listQuery.issue_status" placeholder="争议状态" clearable style="width: 140px;">
              <el-option v-for="item in issueStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </el-form-item>
          <el-form-item label="发货单状态">
            <el-select v-model="listQuery.shipment_status" placeholder="发货单状态" clearable style="width: 180px;">
                <el-option v-for="item in shipmentStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="$emit('filter')">查询</el-button>
            <el-button @click="$emit('reset')">重置</el-button>
          </el-form-item>
        </el-form>
      </el-collapse-item>
    </el-collapse>

    <div style="margin: 12px 0; display: flex; align-items: center; gap: 12px;">
      <el-button type="danger" size="small" icon="el-icon-refresh" :loading="syncing" @click="$emit('sync')">同步订单</el-button>
      <el-button type="primary" plain size="small" icon="el-icon-download" :loading="exporting" @click="$emit('export')">导出订单</el-button>
      <el-select v-model="currentBatchBackendStatus" clearable size="small" placeholder="批量修改后台状态" style="width: 180px;">
        <el-option v-for="item in backendStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
      </el-select>
      <el-button
        type="warning"
        plain
        size="small"
        :disabled="selectedCount === 0 || !currentBatchBackendStatus"
        @click="$emit('batch-update-backend-status')"
      >批量改后台状态</el-button>
      <span style="color: #999; font-size: 12px;">已选 {{ selectedCount }} 条</span>
      <span style="color: #999; font-size: 12px;">共 {{ total }} 条</span>
      <div style="margin-left: auto;">
        <el-select v-model="listQuery.limit" size="small" style="width: 100px;" @change="$emit('filter')">
          <el-option :value="20" label="20条/页" />
          <el-option :value="50" label="50条/页" />
          <el-option :value="100" label="100条/页" />
        </el-select>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'OrderFilterPanel',
  props: {
    backendStatusTabs: {
      type: Array,
      default: () => []
    },
    backendStatusCounts: {
      type: Object,
      default: () => ({})
    },
    statusTabs: {
      type: Array,
      default: () => []
    },
    statusCounts: {
      type: Object,
      default: () => ({})
    },
    listQuery: {
      type: Object,
      required: true
    },
    showFilter: {
      type: Array,
      default: () => []
    },
    dateRange: {
      type: Array,
      default: () => []
    },
    purchaseDateRange: {
      type: Array,
      default: () => []
    },
    shippingDateRange: {
      type: Array,
      default: () => []
    },
    shopOptions: {
      type: Array,
      default: () => []
    },
    issueStatusOptions: {
      type: Array,
      default: () => []
    },
    shipmentStatusOptions: {
      type: Array,
      default: () => []
    },
    syncing: {
      type: Boolean,
      default: false
    },
    exporting: {
      type: Boolean,
      default: false
    },
    batchBackendStatus: {
      type: String,
      default: ''
    },
    backendStatusOptions: {
      type: Array,
      default: () => []
    },
    selectedCount: {
      type: Number,
      default: 0
    },
    total: {
      type: Number,
      default: 0
    }
  },
  computed: {
    filterCollapse: {
      get() {
        return this.showFilter
      },
      set(value) {
        this.$emit('update:showFilter', value)
      }
    },
    orderDateRange: {
      get() {
        return this.dateRange
      },
      set(value) {
        this.$emit('update:dateRange', value)
      }
    },
    purchaseRange: {
      get() {
        return this.purchaseDateRange
      },
      set(value) {
        this.$emit('update:purchaseDateRange', value)
      }
    },
    deliveryRange: {
      get() {
        return this.shippingDateRange
      },
      set(value) {
        this.$emit('update:shippingDateRange', value)
      }
    },
    currentBatchBackendStatus: {
      get() {
        return this.batchBackendStatus
      },
      set(value) {
        this.$emit('update:batchBackendStatus', value)
      }
    }
  }
}
</script>

<style scoped>
.status-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 12px;
  border-bottom: 1px solid #e8e8e8;
  padding-bottom: 8px;
}

.status-tab {
  padding: 6px 14px;
  cursor: pointer;
  font-size: 13px;
  color: #666;
  border-bottom: 2px solid transparent;
  white-space: nowrap;
}

.status-tab.active {
  color: #409eff;
  border-bottom-color: #409eff;
  font-weight: 600;
}

.status-tab:hover {
  color: #409eff;
}

.backend-tabs {
  margin-top: -4px;
}
</style>
