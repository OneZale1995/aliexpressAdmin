<template>
  <div v-loading="listLoading" class="order-list-section">
    <div v-if="list.length === 0 && !listLoading" class="empty-state">
      暂无订单数据，请先同步
    </div>

    <template v-if="list.length > 0">
      <div class="order-list-header">
        <div class="col-check header-check">
          <el-checkbox
            :value="isAllCurrentPageSelected"
            :indeterminate="isCurrentPageIndeterminate"
            @change="$emit('toggle-select-all-current-page', $event)"
          />
        </div>
        <div class="col-images">商品图片</div>
        <div class="col-goods">商品标题</div>
        <div class="col-basic">订单基本信息</div>
        <div class="col-logistics">物流/收货信息</div>
        <div class="col-amount">金额信息</div>
        <div class="col-backend">后台字段信息</div>
        <div class="col-ops">操作</div>
      </div>

      <div v-for="order in list" :key="order.id" class="order-card">
        <div class="order-row">
          <div class="col-check cell-check">
            <el-checkbox :value="selectedOrders.includes(order.id)" @change="$emit('toggle-select', order.id)" />
          </div>

          <div class="col-images cell-block">
            <div v-for="item in (order.items || []).slice(0, 2)" :key="item.id" class="image-item">
              <el-image
                v-if="item.img_url"
                :src="item.img_url"
                :preview-src-list="[item.img_url]"
                fit="cover"
                class="goods-thumb"
              />
              <div v-else class="goods-thumb goods-thumb--empty">无图</div>
            </div>
            <div v-if="(order.items || []).length === 0" class="empty-text">暂无图片</div>
          </div>

          <div class="col-goods cell-block">
            <div v-for="item in (order.items || []).slice(0, 2)" :key="item.id" class="goods-item">
              <div class="goods-main">
                <a v-if="getItemLink(item)" :href="getItemLink(item)" target="_blank" rel="noopener noreferrer" class="goods-title goods-title-link">{{ item.name || '-' }}</a>
                <div v-else class="goods-title">{{ item.name || '-' }}</div>
                <div class="goods-category">分类：{{ getCategoryFromSku(item) }}</div>
                <div class="goods-meta">{{ item.sku_code || '-' }} | {{ item.item_price || 0 }} x {{ item.quantity || 1 }}</div>
              </div>
            </div>
            <div v-if="(order.items || []).length === 0" class="empty-text">暂无商品</div>
            <div v-if="(order.items || []).length > 2" class="more-text">等 {{ order.items.length }} 件商品</div>
          </div>

          <div class="col-basic cell-block">
            <div class="meta-line"><span class="label">店铺名称</span><span class="clip-text">{{ order.shop ? order.shop.name : '-' }}</span></div>
            <div class="meta-line"><span class="label">店铺邮箱</span><span class="clip-text">{{ order.shop ? order.shop.email : '-' }}</span></div>
            <div class="meta-line"><span class="label">订单号</span>{{ order.ae_order_id }}</div>
            <div class="meta-line"><span class="label">下单</span>{{ formatDate(order.ae_created_at) }}</div>
            <div class="meta-line"><span class="label">买家</span>{{ order.buyer_name || '-' }}</div>
            <div class="meta-line"><span class="label">状态</span><el-tag :type="getStatusTagType(order.order_display_status)" size="mini">{{ getStatusLabel(order.order_display_status) }}</el-tag></div>
          </div>

          <div class="col-logistics cell-block">
            <div class="meta-line"><span class="label">收件人</span>{{ order.receiver_name || order.buyer_name || '-' }}</div>
            <div class="meta-line"><span class="label">电话</span>{{ order.receiver_phone || order.buyer_phone || '-' }}</div>
            <div class="meta-line"><span class="label">地址</span><span class="clip-text">{{ formatAddress(order) }}</span></div>
            <div class="meta-line"><span class="label">物流</span>{{ getLogisticsTypeLabel(order.logistics_type) }}</div>
            <div class="meta-line tracking-line">
              <span class="label">运单号</span>
              <span class="tracking-value">{{ order.tracking_number || '-' }}</span>
              <el-button
                v-if="order.tracking_number"
                type="text"
                size="mini"
                icon="el-icon-copy-document"
                class="tracking-copy-btn"
                @click="$emit('copy-text', order.tracking_number)"
              />
            </div>
            <div class="meta-line"><span class="label">交接单ID</span>{{ order.handover_list_id || '-' }}</div>
            <div class="meta-line"><span class="label">交接状态</span>{{ getHandoverListStatusLabel(order.handover_list_status) }}</div>
          </div>

          <div class="col-amount cell-block">
            <div class="meta-line"><span class="label">销售额</span><span class="strong">{{ Number(order.total_amount || 0).toFixed(2) }}</span></div>
            <div class="meta-line"><span class="label">手续费</span>{{ calcFee(order) }}</div>
            <div class="meta-line"><span class="label">回款</span>{{ calcTotalBack(order) }}</div>
            <div class="meta-line"><span class="label">采购</span>{{ Number(order.purchase_amount || 0).toFixed(2) }}</div>
            <div class="meta-line"><span class="label">物流费</span>{{ Number(order.logistics_fee || 0).toFixed(2) }}</div>
            <div class="meta-line"><span class="label">利润</span><span :class="calcProfit(order) >= 0 ? 'text-success' : 'text-danger'">{{ Number(calcProfit(order) || 0).toFixed(2) }}</span></div>
            <div class="meta-line"><span class="label">利润率</span><span :class="calcProfit(order) >= 0 ? 'text-success' : 'text-danger'">{{ calcProfitRate(order) }}%</span></div>
            <div class="meta-line"><span class="label">人民币利润</span><span :class="calcProfit(order) >= 0 ? 'text-success' : 'text-danger'">{{ calcProfitCny(order) }}</span></div>
          </div>

          <div class="col-backend cell-block">
            <div class="meta-line"><span class="label">后台状态</span>{{ getBackendStatusLabel(order.backend_status) }}</div>
            <div class="meta-line"><span class="label">物流模板</span>{{ getLogisticsTemplateLabel(order.logistics_template) }}</div>
            <div class="meta-line"><span class="label">采购日期</span>{{ order.purchase_date || '-' }}</div>
            <div class="meta-line"><span class="label">发货日期</span>{{ order.shipping_date || '-' }}</div>
            <div class="meta-line"><span class="label">后台备注</span><span class="clip-text">{{ order.admin_remark || '-' }}</span></div>
            <div class="meta-line image-line"><span class="label">采购图片</span>
              <el-image
                v-if="order.purchase_image"
                :src="order.purchase_image"
                :preview-src-list="[order.purchase_image]"
                class="backend-thumb"
                fit="cover"
              />
              <span v-else>-</span>
            </div>
            <div class="meta-line image-line"><span class="label">上传图片</span>
              <el-image
                v-if="order.shipping_image"
                :src="order.shipping_image"
                :preview-src-list="[order.shipping_image]"
                class="backend-thumb"
                fit="cover"
              />
              <span v-else>-</span>
            </div>
          </div>

          <div class="col-ops cell-ops">
            <el-button
              v-if="canPrintLabel(order)"
              type="primary"
              size="mini"
              @click="$emit('print-label', order)"
            >打印面单</el-button>
            <el-button
              v-if="canCreateTransferSheet(order)"
              type="primary"
              plain
              size="mini"
              :loading="transferSheetLoadingId === order.id"
              @click="$emit('transfer-sheet', order)"
            >打印交接单</el-button>
            <el-button
              v-if="order.order_display_status === 'WaitSendGoods'"
              type="success"
              size="mini"
              @click="$emit('ship', order)"
            >{{ getShipButtonText(order) }}</el-button>
            <el-button type="warning" size="mini" @click="$emit('mark-ship', order)">更新订单</el-button>
            <el-button type="info" size="mini" @click="$emit('open-comment-dialog', order)">后台更新</el-button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import {
  calcFee,
  calcProfit,
  calcProfitCny,
  calcProfitRate,
  calcTotalBack,
  canCreateTransferSheet,
  canPrintLabel,
  formatAddress,
  formatDate,
  getBackendStatusLabel,
  getCategoryFromSku,
  getHandoverListStatusLabel,
  getItemLink,
  getLogisticsTemplateLabel,
  getLogisticsTypeLabel,
  getStatusLabel,
  getStatusTagType,
  isDbsLogisticsType
} from '../utils'

export default {
  name: 'OrderListSection',
  props: {
    list: {
      type: Array,
      default: () => []
    },
    listLoading: {
      type: Boolean,
      default: false
    },
    selectedOrders: {
      type: Array,
      default: () => []
    },
    isAllCurrentPageSelected: {
      type: Boolean,
      default: false
    },
    isCurrentPageIndeterminate: {
      type: Boolean,
      default: false
    },
    transferSheetLoadingId: {
      type: Number,
      default: null
    },
    dictLabelMap: {
      type: Object,
      default: () => ({})
    },
    cnyExchangeRate: {
      type: Number,
      default: 7.2
    }
  },
  methods: {
    calcFee,
    calcProfit,
    calcProfitRate,
    calcTotalBack,
    canCreateTransferSheet,
    canPrintLabel,
    formatAddress,
    formatDate,
    getCategoryFromSku,
    getItemLink,
    getLogisticsTemplateLabel,
    getStatusTagType,
    calcProfitCny(order) {
      return calcProfitCny(order, this.cnyExchangeRate)
    },
    getBackendStatusLabel(status) {
      return getBackendStatusLabel(status, this.dictLabelMap)
    },
    getHandoverListStatusLabel(status) {
      return getHandoverListStatusLabel(status, this.dictLabelMap)
    },
    getLogisticsTypeLabel(type) {
      return getLogisticsTypeLabel(type, this.dictLabelMap)
    },
    getStatusLabel(status) {
      return getStatusLabel(status, this.dictLabelMap)
    },
    getShipButtonText(order) {
      return isDbsLogisticsType(order.logistics_type) ? '实际发货' : 'FBS发货流程'
    }
  }
}
</script>

<style scoped>
.empty-state {
  text-align: center;
  padding: 60px;
  color: #999;
}

.order-list-header {
  display: grid;
  grid-template-columns: 50px 100px 1.05fr 0.85fr 1.2fr 0.95fr 1.05fr 130px;
  gap: 8px;
  align-items: center;
  background: #f5f7fa;
  border: 1px solid #e4e7ed;
  border-radius: 6px;
  padding: 8px 10px;
  margin-bottom: 8px;
  color: #606266;
  font-size: 12px;
  font-weight: 600;
}

.order-card {
  border: none;
  border-bottom: 1px solid #ebeef5;
  border-radius: 0;
  background: #fff;
  margin-bottom: 0;
  overflow: hidden;
}

.order-card:first-child {
  border-top: 1px solid #ebeef5;
}

.order-row {
  display: grid;
  grid-template-columns: 50px 100px 1.05fr 0.85fr 1.2fr 0.95fr 1.05fr 130px;
  gap: 8px;
  align-items: stretch;
  padding: 10px;
}

.cell-check {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding-top: 2px;
}

.header-check {
  display: flex;
  justify-content: center;
  align-items: center;
}

.header-check .el-checkbox {
  margin-right: 0;
}

.cell-block {
  min-width: 0;
  border-right: 1px dashed #eef1f6;
  padding-right: 6px;
}

.cell-ops {
  border-right: none;
  padding-right: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: stretch;
}

.cell-ops .el-button {
  width: 100%;
  margin-left: 0;
}

.cell-ops .el-button + .el-button {
  margin-left: 0;
}

.meta-line {
  display: flex;
  align-items: flex-start;
  gap: 4px;
  font-size: 12px;
  color: #606266;
  line-height: 1.35;
  margin-bottom: 2px;
}

.label {
  color: #909399;
  width: 64px;
  flex-shrink: 0;
  white-space: nowrap;
}

.tracking-line {
  align-items: center;
}

.tracking-value {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tracking-copy-btn {
  margin-left: 2px;
  padding: 0;
}

.goods-item {
  display: block;
  margin-bottom: 6px;
}

.image-item {
  margin-bottom: 6px;
}

.goods-thumb {
  width: 56px;
  height: 56px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  object-fit: cover;
  flex-shrink: 0;
}

.goods-thumb--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: #b0b6bf;
  background: #fafafa;
}

.goods-main {
  min-width: 0;
}

.goods-title {
  font-size: 12px;
  color: #303133;
  line-height: 1.4;
  white-space: normal;
  word-break: break-word;
}

.goods-category {
  margin-top: 2px;
  font-size: 12px;
  color: #8d96a3;
}

.goods-title-link {
  color: #409eff;
  text-decoration: underline;
}

.goods-title-link:hover {
  color: #1f78d1;
  text-decoration: underline;
}

.goods-meta {
  font-size: 12px;
  color: #909399;
  margin-top: 2px;
}

.empty-text,
.more-text {
  color: #909399;
  font-size: 12px;
}

.clip-text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.image-line {
  align-items: center;
}

.backend-thumb {
  width: 36px;
  height: 36px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
}

.strong {
  font-weight: 700;
  color: #303133;
}

@media (max-width: 1400px) {
  .order-list-header,
  .order-row {
    grid-template-columns: 44px 86px 1fr 0.8fr 1.05fr 0.9fr 0.95fr 120px;
    gap: 8px;
  }
}

@media (max-width: 1100px) {
  .order-list-header {
    display: none;
  }

  .order-row {
    grid-template-columns: 40px 1fr;
    gap: 8px;
  }

  .cell-block,
  .cell-ops {
    grid-column: 2;
    border-right: none;
    border-top: 1px dashed #f0f2f5;
    padding-top: 6px;
    padding-right: 0;
  }
}
</style>
