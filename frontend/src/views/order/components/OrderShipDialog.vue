<template>
  <el-dialog :title="title" :visible.sync="dialogVisible" width="900px" top="5vh">
    <div v-loading="shipForm.workflow_loading" class="fbs-workflow">
      <el-alert
        title="FBS 页面只展示平台动作步骤；打包、检查、备件和实际交寄请在线下完成。"
        type="info"
        :closable="false"
        show-icon
      />

      <el-steps :active="shipForm.current_step" finish-status="success" align-center class="workflow-steps">
        <el-step title="创建发货单" description="提交包裹参数" />
        <el-step title="创建交接单" description="可新建或编辑" />
        <el-step title="打印发货标签" description="打印并粘贴" />
        <el-step title="打印交接单" description="打印转印表" />
        <el-step title="完成交接" description="揽收或交寄后同步平台" />
      </el-steps>

      <div class="workflow-summary">
        <div class="summary-card">
          <div class="summary-label">平台单号</div>
          <div class="summary-value">{{ shipForm.trade_order_id || '-' }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">发货单ID</div>
          <div class="summary-value">{{ shipForm.logistic_order_id || '-' }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">发货单状态</div>
          <div class="summary-value">{{ getLogisticOrderStatusLabel(shipForm.logistic_order_status) }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">平台运单号</div>
          <div class="summary-value">{{ shipForm.platform_tracking_code || '-' }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">交接单ID</div>
          <div class="summary-value">{{ shipForm.handover_list_id || '-' }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">交接单状态</div>
          <div class="summary-value">{{ getHandoverListStatusLabel(shipForm.handover_list_status) }}</div>
        </div>
      </div>

      <div v-show="shipForm.current_step === 0" class="workflow-panel">
        <div class="panel-title">第一步：创建发货单</div>
        <el-form label-width="120px">
          <el-row :gutter="12">
            <el-col :span="12">
              <el-form-item label="包裹长(cm)" required>
                <el-input-number v-model="shipForm.total_length" :min="1" :max="200" :step="1" style="width:100%;" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="包裹宽(cm)" required>
                <el-input-number v-model="shipForm.total_width" :min="1" :max="200" :step="1" style="width:100%;" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="12">
              <el-form-item label="包裹高(cm)" required>
                <el-input-number v-model="shipForm.total_height" :min="1" :max="200" :step="1" style="width:100%;" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="重量(kg)" required>
                <el-input-number v-model="shipForm.total_weight" :min="0.01" :max="50" :step="0.01" :precision="2" style="width:100%;" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="12">
              <el-form-item label="投递失败处理">
                <el-select v-model="shipForm.undeliverable_option" style="width:100%;">
                  <el-option v-for="item in undeliverableOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="危险类型">
                <el-select v-model="shipForm.danger_type" style="width:100%;">
                  <el-option v-for="item in dangerTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item label="发货商品" required>
            <el-table :data="shipForm.items" size="mini" border>
              <el-table-column label="商品信息" min-width="260">
                <template slot-scope="{ row }">
                  <div class="ship-item-cell">
                    <el-image
                      v-if="row.img_url"
                      :src="row.img_url"
                      :preview-src-list="[row.img_url]"
                      fit="cover"
                      class="ship-item-image"
                    />
                    <div v-else class="ship-item-image ship-item-image--empty">无图</div>
                    <div class="ship-item-meta">
                      <div class="ship-item-title">{{ row.title || '-' }}</div>
                      <div class="ship-item-subtitle">SKU编码：{{ row.sku_code || '-' }}</div>
                    </div>
                  </div>
                </template>
              </el-table-column>
              <el-table-column label="SKU ID" min-width="140">
                <template slot-scope="{ row }"><el-input v-model.number="row.sku_id" size="mini" /></template>
              </el-table-column>
              <el-table-column label="来源ID" min-width="140">
                <template slot-scope="{ row }"><el-input v-model.number="row.product_source_id" size="mini" /></template>
              </el-table-column>
              <el-table-column label="数量" width="120">
                <template slot-scope="{ row }"><el-input-number v-model="row.quantity" :min="1" :step="1" size="mini" /></template>
              </el-table-column>
            </el-table>
          </el-form-item>
        </el-form>
        <div class="panel-actions">
          <el-button type="primary" :loading="shipping" @click="$emit('submit-fbs-logistic-order')">创建发货单</el-button>
        </div>
      </div>

      <div v-show="shipForm.current_step === 1" class="workflow-panel">
        <div class="panel-title">第二步：创建或编辑交接清单</div>
        <el-form label-width="140px">
          <el-form-item label="发货日期">
            <el-date-picker v-model="shipForm.arrival_date" type="date" value-format="yyyy-MM-dd" placeholder="不填则按平台截止日处理" style="width:100%;" />
          </el-form-item>
          <el-form-item label="交接单ID(追加用)">
            <el-input v-model.number="shipForm.existing_handover_list_id" placeholder="填写后可把当前发货单追加到已有交接单" />
          </el-form-item>
          <el-form-item label="当前交接单">
            <div class="summary-inline">
              <span>ID：{{ shipForm.handover_list_id || '-' }}</span>
              <span>状态：{{ getHandoverListStatusLabel(shipForm.handover_list_status) }}</span>
              <span v-if="shipForm.handover_arrival_date">交接日期：{{ formatDate(shipForm.handover_arrival_date) }}</span>
            </div>
          </el-form-item>
        </el-form>
        <div class="panel-actions multi-actions">
          <el-button @click="$emit('update-step', 0)">上一步</el-button>
          <el-button type="primary" :loading="shipping" @click="$emit('submit-fbs-handover-list')">新建交接单</el-button>
          <el-button :loading="shipping" :disabled="!shipForm.existing_handover_list_id" @click="$emit('add-fbs-to-handover')">追加到已有交接单</el-button>
          <el-button :loading="shipping" :disabled="!shipForm.handover_list_id" @click="$emit('remove-fbs-from-handover')">从当前交接单移除</el-button>
        </div>
      </div>

      <div v-show="shipForm.current_step === 2" class="workflow-panel">
        <div class="panel-title">第三步：打印标签并粘贴到货物</div>
        <div class="summary-inline summary-inline--stack">
          <span>发货单ID：{{ shipForm.logistic_order_id || '-' }}</span>
          <span>平台运单号：{{ shipForm.platform_tracking_code || shipForm.track_number || '-' }}</span>
          <span>截止发货时间：{{ formatDate(shipForm.cut_off_date) }}</span>
        </div>
        <div class="panel-actions">
          <el-button @click="$emit('update-step', 1)">上一步</el-button>
          <el-button type="primary" @click="$emit('print-fbs-label')">打印发货标签</el-button>
          <el-button :disabled="!shipForm.handover_list_id" @click="$emit('update-step', 3)">继续打印交接单</el-button>
        </div>
      </div>

      <div v-show="shipForm.current_step === 3" class="workflow-panel">
        <div class="panel-title">第四步：打印交接单</div>
        <div class="summary-inline summary-inline--stack">
          <span>交接单ID：{{ shipForm.handover_list_id || '-' }}</span>
          <span>状态：{{ getHandoverListStatusLabel(shipForm.handover_list_status) }}</span>
          <span>类型：{{ getHandoverShipmentTypeLabel(shipForm.handover_shipment_type) }}</span>
        </div>
        <div class="panel-actions multi-actions">
          <el-button @click="$emit('update-step', 2)">上一步</el-button>
          <el-button type="primary" :disabled="!shipForm.handover_list_id" @click="$emit('print-fbs-handover-label')">打印交接单</el-button>
          <el-button :disabled="!shipForm.handover_list_id" @click="$emit('update-step', 4)">进入完成交接</el-button>
        </div>
      </div>

      <div v-show="shipForm.current_step === 4" class="workflow-panel">
        <div class="panel-title">第五步：完成交接</div>
        <el-alert title="线下打包、复核和交寄完成后，在这里同步平台状态。" type="warning" :closable="false" show-icon />
        <div class="summary-inline summary-inline--stack">
          <span>交接单ID：{{ shipForm.handover_list_id || '-' }}</span>
          <span>交接状态：{{ getHandoverListStatusLabel(shipForm.handover_list_status) }}</span>
          <span v-if="shipForm.pickup_date">预约揽收：{{ shipForm.pickup_date }} {{ shipForm.pickup_time_from }} - {{ shipForm.pickup_time_to }}</span>
        </div>
        <div class="panel-actions multi-actions">
          <el-button @click="$emit('update-step', 3)">上一步</el-button>
          <el-button :disabled="!shipForm.handover_list_id || isTransferCompleted" @click="$emit('mark-fbs-ready-for-pickup')">标记待揽收</el-button>
          <el-button type="primary" :disabled="!canTransferHandover" @click="$emit('transfer-fbs-handover-list')">关闭交接单</el-button>
        </div>
      </div>
    </div>

    <div slot="footer">
      <el-button @click="dialogVisible = false">取消</el-button>
      <el-button @click="$emit('refresh-fbs-workflow')">刷新状态</el-button>
    </div>
  </el-dialog>
</template>

<script>
import { ORDER_DICT_CODE } from '../constants'
import {
  formatDate,
  getDictOptionsByCode,
  getHandoverListStatusLabel,
  getHandoverShipmentTypeLabel,
  getLogisticOrderStatusLabel
} from '../utils'

export default {
  name: 'OrderShipDialog',
  props: {
    visible: { type: Boolean, default: false },
    title: { type: String, default: '实际发货' },
    shipForm: { type: Object, required: true },
    shipping: { type: Boolean, default: false },
    dictLabelMap: { type: Object, default: () => ({}) }
  },
  computed: {
    dialogVisible: {
      get() { return this.visible },
      set(v) { this.$emit('update:visible', v) }
    },
    undeliverableOptions() {
      return getDictOptionsByCode(ORDER_DICT_CODE.undeliverableOption, this.dictLabelMap)
    },
    dangerTypeOptions() {
      return getDictOptionsByCode(ORDER_DICT_CODE.dangerType, this.dictLabelMap)
    },
    isTransferCompleted() {
      return ['Transferred', 'Completed'].includes(this.shipForm.handover_list_status)
    },
    canTransferHandover() {
      if (!this.shipForm.handover_list_id) return false
      return ['Accepted', 'PartiallyAccepted', 'Sent'].includes(this.shipForm.handover_list_status)
    }
  },
  methods: {
    formatDate,
    getHandoverListStatusLabel(status) {
      return getHandoverListStatusLabel(status, this.dictLabelMap)
    },
    getHandoverShipmentTypeLabel(type) {
      return getHandoverShipmentTypeLabel(type, this.dictLabelMap)
    },
    getLogisticOrderStatusLabel(status) {
      return getLogisticOrderStatusLabel(status, this.dictLabelMap)
    }
  }
}
</script>

<style scoped>
.fbs-workflow { min-height: 540px; }
.workflow-steps { margin: 20px 0 24px; }

.workflow-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.summary-card {
  padding: 12px 14px;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  background: #fafcff;
}

.summary-label { color: #909399; font-size: 12px; margin-bottom: 6px; }
.summary-value { color: #303133; font-size: 14px; font-weight: 600; word-break: break-all; }

.workflow-panel {
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 18px;
  background: #fff;
}

.ship-item-cell { display: flex; align-items: flex-start; gap: 10px; }

.ship-item-image {
  width: 52px; height: 52px; border-radius: 6px;
  border: 1px solid #ebeef5; flex-shrink: 0;
}

.ship-item-image--empty {
  display: flex; align-items: center; justify-content: center;
  background: #f5f7fa; color: #909399; font-size: 12px;
}

.ship-item-meta { min-width: 0; }
.ship-item-title { color: #303133; font-size: 13px; line-height: 1.45; word-break: break-word; }
.ship-item-subtitle { margin-top: 4px; color: #909399; font-size: 12px; }
.panel-title { font-size: 16px; font-weight: 600; color: #303133; margin-bottom: 16px; }
.panel-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 18px; }
.multi-actions { flex-wrap: wrap; }
.workflow-check { display: block; margin-top: 18px; }

.summary-inline {
  display: flex; gap: 16px; flex-wrap: wrap;
  color: #606266; font-size: 13px;
}

.summary-inline--stack { flex-direction: column; gap: 8px; }

@media (max-width: 900px) {
  .workflow-summary { grid-template-columns: 1fr; }
}
</style>
