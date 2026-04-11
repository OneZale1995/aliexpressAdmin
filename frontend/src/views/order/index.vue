 <template>
  <div class="app-container order-page">
    <!-- Tab 状态栏 -->
    <div class="status-tabs">
      <span
        v-for="tab in statusTabs"
        :key="tab.key"
        :class="['status-tab', { active: listQuery.display_status === tab.key }]"
        @click="switchTab(tab.key)"
      >
        {{ tab.label }}（{{ statusCounts[tab.countKey] || 0 }}）
      </span>
    </div>

    <!-- 筛选搜索 -->
    <el-collapse v-model="showFilter">
      <el-collapse-item name="filter">
        <template slot="title"><i class="el-icon-search" /> 筛选搜索</template>
        <el-form :model="listQuery" inline class="filter-container" style="padding: 10px 0;">
          <el-form-item label="店铺">
            <el-select v-model="listQuery.shop_id" placeholder="选择店铺" clearable style="width: 180px;">
              <el-option v-for="s in shopOptions" :key="s.id" :label="s.name" :value="s.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="店铺关键词">
            <el-input v-model="listQuery.shop_keyword" placeholder="店铺名/邮箱" style="width: 180px;" />
          </el-form-item>
          <el-form-item label="国内单号">
            <el-input v-model="listQuery.ae_order_id" placeholder="国内单号" style="width: 180px;" />
          </el-form-item>
          <el-form-item label="国际单号">
            <el-input v-model="listQuery.tracking_number" placeholder="国际单号/运单号" style="width: 180px;" />
          </el-form-item>
          <el-form-item label="下单日期">
            <el-date-picker
              v-model="dateRange"
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
              v-model="purchaseDateRange"
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
              v-model="shippingDateRange"
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
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="handleFilter">查询</el-button>
            <el-button @click="resetFilter">重置</el-button>
          </el-form-item>
        </el-form>
      </el-collapse-item>
    </el-collapse>

    <!-- 操作按钮 -->
    <div style="margin: 12px 0; display: flex; align-items: center; gap: 12px;">
      <el-button type="danger" size="small" icon="el-icon-refresh" :loading="syncing" @click="handleSync">同步订单</el-button>
      <el-button type="primary" plain size="small" icon="el-icon-download" :loading="exporting" @click="handleExport">导出订单</el-button>
      <span style="color: #999; font-size: 12px;">共 {{ total }} 条</span>
      <div style="margin-left: auto;">
        <el-select v-model="listQuery.limit" size="small" style="width: 100px;" @change="handleFilter">
          <el-option :value="20" label="20条/页" />
          <el-option :value="50" label="50条/页" />
          <el-option :value="100" label="100条/页" />
        </el-select>
      </div>
    </div>

    <!-- 订单列表 -->
    <div v-loading="listLoading">
      <div v-if="list.length === 0 && !listLoading" style="text-align: center; padding: 60px; color: #999;">
        暂无订单数据，请先同步
      </div>

      <div v-for="order in list" :key="order.id" class="order-card">
        <div class="order-card-body">
          <!-- ① 勾选框 -->
          <div class="order-check-col">
            <el-checkbox :value="selectedOrders.includes(order.id)" @change="toggleSelect(order.id)" />
          </div>

          <!-- ② 产品信息 -->
          <div class="order-product-col">
            <div class="product-shop-line">
              <span class="shop-tag">{{ order.shop ? order.shop.name : '' }}</span>
              <span class="order-meta">{{ order.shop ? order.shop.email : '' }}</span>
            </div>
            <div class="product-order-line">
              <span class="order-meta">单号：<span class="order-id">{{ order.ae_order_id }}</span></span>
              <span class="order-meta" style="margin-left: 12px;">总额：<span class="highlight">{{ order.total_amount }}</span></span>
            </div>
            <div class="product-order-line">
              <span class="order-meta">下单：{{ formatDate(order.ae_created_at) }}</span>
            </div>
            <div v-for="item in order.items" :key="item.id" class="order-item-row">
              <img :src="item.img_url" class="item-img" @error="onImgError" />
              <div class="item-info">
                <div class="item-category">{{ getCategoryFromSku(item) }}</div>
                <div class="item-id">id: {{ item.ae_item_id }}</div>
                <div class="item-sku">标题：{{ item.name }}</div>
                <div class="item-qty">售价：{{ item.item_price }} * {{ item.quantity }}</div>
              </div>
            </div>
          </div>

          <!-- ③ 状态 + 费用 -->
          <div class="order-status-col">
            <el-tag :type="getStatusTagType(order.order_display_status)" size="small" style="margin-bottom: 8px;">
              {{ getStatusLabel(order.order_display_status) }}
            </el-tag>
            <div class="fee-row"><span class="label">平台费用：</span>{{ order.platform_fee || 0 }}</div>
            <div class="fee-row"><span class="label">联盟费用：</span>{{ order.affiliate_fee || 0 }}</div>
            <div class="fee-row"><span class="label">预估收入：</span><span class="success-text">{{ order.estimate_revenue || 0 }}</span></div>
          </div>

          <!-- ④ 订单数据(预计) -->
          <div class="order-amount-col">
            <div class="amount-row"><span class="label">总售价：</span><span class="highlight">{{ order.total_amount }}</span></div>
            <div class="amount-row"><span class="label">手续费：</span><span>{{ calcFee(order) }}</span></div>
            <div class="amount-row"><span class="label">总回款：</span><span class="success-text">{{ calcTotalBack(order) }}</span></div>
            <div class="amount-row"><span class="label">连连：</span><span>{{ order.lianlian_fee || 0 }}</span></div>
            <div class="amount-row"><span class="label">采购额：</span><span>{{ order.purchase_amount || 0 }}</span></div>
            <div class="amount-row"><span class="label">快递费：</span><span>{{ order.express_fee || order.shipping_fee || 0 }}</span></div>
            <div class="amount-row"><span class="label">物流费：</span><span>{{ order.logistics_fee || 0 }} 预</span></div>
            <div class="amount-row"><span class="label">利润：</span><span>{{ calcProfit(order) }}</span></div>
            <div class="amount-row"><span class="label">利润率：</span><span>{{ calcProfitRate(order) }}%</span></div>
          </div>

          <!-- ⑤ 收货地址 -->
          <div class="order-receiver-col">
            <div><span class="label">姓名：</span>{{ order.receiver_name || order.buyer_name }}</div>
            <div><span class="label">电话：</span>{{ order.receiver_phone || order.buyer_phone }}</div>
            <div><span class="label">地址：</span>{{ formatAddress(order) }}</div>
            <div><span class="label">物流：</span>{{ order.logistics_type || '-' }}</div>
            <div v-if="order.tracking_number">
              <span class="label">快递：</span>
              {{ order.tracking_number }}
              <el-button type="text" size="mini" icon="el-icon-copy-document" @click="copyText(order.tracking_number)" />
            </div>
          </div>

          <!-- ⑥ 操作 -->
          <div class="order-ops-col">
            <el-button
              v-if="canPrintLabel(order)"
              type="primary" size="mini" style="width: 80px; margin-bottom: 6px;"
              @click="handlePrintLabel(order)"
            >打印面单</el-button>
            <el-button
              v-if="order.order_display_status === 'WaitSendGoods'"
              type="success" size="mini" style="width: 80px; margin-bottom: 6px;"
              @click="handleShip(order)"
            >实际发货</el-button>
            <el-button
              type="warning" size="mini" style="width: 80px; margin-bottom: 6px;"
              @click="handleMarkShip(order)"
            >更新订单</el-button>
            <el-button
              type="info" size="mini" style="width: 80px;"
              @click="openCommentDialog(order)"
            >后台更新</el-button>
          </div>
        </div>

        <!-- 备注行 -->
        <div v-if="order.admin_remark || order.seller_comment" class="order-comment">
          <i class="el-icon-chat-dot-round" />
          <span v-if="order.admin_remark">后台备注：{{ order.admin_remark }}</span>
          <span v-if="order.admin_remark && order.seller_comment"> | </span>
          <span v-if="order.seller_comment">平台备注：{{ order.seller_comment }}</span>
        </div>
      </div>
    </div>

    <!-- 分页 -->
    <pagination v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit" @pagination="getList" />

    <!-- 后台更新对话框 -->
    <el-dialog title="订单后台更新" :visible.sync="commentDialogVisible" width="700px">
      <el-form label-width="100px">
        <el-form-item label="后台备注">
          <el-input v-model="commentTemp.admin_remark" type="textarea" :rows="3" placeholder="请输入后台备注" />
        </el-form-item>
        <el-form-item label="采购日期">
          <el-date-picker v-model="commentTemp.purchase_date" type="date" value-format="yyyy-MM-dd" placeholder="选择采购日期" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="发货日期">
          <el-date-picker v-model="commentTemp.shipping_date" type="date" value-format="yyyy-MM-dd" placeholder="选择发货日期" style="width: 100%;" />
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="连连费用">
              <el-input v-model.number="commentTemp.lianlian_fee" type="number" min="0" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="采购额">
              <el-input v-model.number="commentTemp.purchase_amount" type="number" min="0" @input="recalcEubLogisticsFee" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="快递费">
              <el-input v-model.number="commentTemp.express_fee" type="number" min="0" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="物流费">
              <el-input v-model.number="commentTemp.logistics_fee" type="number" min="0" @input="markLogisticsFeeManualEdit" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="24">
            <el-form-item label="物流模板">
              <el-radio-group v-model="commentTemp.logistics_template" @change="handleTemplateChange">
                <el-radio-button label="online">线上</el-radio-button>
                <el-radio-button label="offline_leiyi">线下-雷翼/邮政</el-radio-button>
                <el-radio-button label="offline_epacket">线下-E邮宝</el-radio-button>
              </el-radio-group>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row v-if="commentTemp.logistics_template === 'offline_epacket'" :gutter="12">
          <el-col :span="12">
            <el-form-item label="亚马逊比例%">
              <el-input v-model.number="commentTemp.eub_amazon_ratio" type="number" min="0" @input="recalcEubLogisticsFee" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="固定附加费">
              <el-input v-model.number="commentTemp.eub_base_fee" type="number" min="0" @input="recalcEubLogisticsFee" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row v-if="commentTemp.logistics_template === 'offline_epacket'" :gutter="12">
          <el-col :span="12">
            <el-form-item label="系统计算值">
              <el-input :value="commentTemp.calculated_logistics_fee" disabled />
            </el-form-item>
          </el-col>
          <el-col :span="12" style="display:flex; align-items:center;">
            <el-button size="mini" @click="resetLogisticsFeeToCalculated">按计算值回填物流费</el-button>
          </el-col>
        </el-row>
        <el-form-item label="采购图片">
          <el-upload
            :action="uploadUrl"
            :headers="uploadHeaders"
            :show-file-list="false"
            :on-success="res => handleImageUploadSuccess(res, 'purchase_image')"
          >
            <el-button size="small" type="primary">上传采购图片</el-button>
          </el-upload>
          <div v-if="commentTemp.purchase_image" style="margin-top: 8px;">
            <el-image :src="commentTemp.purchase_image" :preview-src-list="[commentTemp.purchase_image]" style="width: 100px; height: 100px; border: 1px solid #eee;" fit="cover" />
          </div>
        </el-form-item>
        <el-form-item label="发货图片">
          <el-upload
            :action="uploadUrl"
            :headers="uploadHeaders"
            :show-file-list="false"
            :on-success="res => handleImageUploadSuccess(res, 'shipping_image')"
          >
            <el-button size="small" type="success">上传发货图片</el-button>
          </el-upload>
          <div v-if="commentTemp.shipping_image" style="margin-top: 8px;">
            <el-image :src="commentTemp.shipping_image" :preview-src-list="[commentTemp.shipping_image]" style="width: 100px; height: 100px; border: 1px solid #eee;" fit="cover" />
          </div>
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="commentDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitComment">保存</el-button>
      </div>
    </el-dialog>

    <!-- 发货对话框 -->
    <el-dialog :title="shipDialogTitle" :visible.sync="shipDialogVisible" width="520px" @close="shipDialogVisible=false">
      <el-form label-width="100px">
        <el-form-item label="物流类型">
          <el-tag :type="shipForm.logistics_type === 'DBS' ? 'warning' : 'success'" size="small">{{ shipForm.logistics_type || '-' }}</el-tag>
        </el-form-item>
        <el-form-item v-if="shipForm.logistics_type === 'DBS'" label="发货渠道" required>
          <el-radio-group v-model="shipForm.ship_provider">
            <el-radio label="chinapost">中国邮政(E邮宝)</el-radio>
            <el-radio label="leiyi">雷翼(sz56t)</el-radio>
            <el-radio label="manual">手动填写单号</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="shipForm.ship_provider === 'chinapost'" label="业务产品">
          <el-select v-model="shipForm.biz_product_no" style="width:100%;">
            <el-option label="E邮宝 (001)" value="001" />
            <el-option label="挂号小包 (002)" value="002" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="shipForm.ship_provider === 'chinapost'" label="重量(克)">
          <el-input-number v-model="shipForm.weight" :min="1" :max="50000" :step="10" style="width:100%;" />
        </el-form-item>
        <el-form-item v-if="shipForm.ship_provider === 'leiyi'" label="重量(克)">
          <el-input-number v-model="shipForm.weight" :min="1" :max="50000" :step="10" style="width:100%;" />
        </el-form-item>
        <el-form-item v-if="shipForm.ship_provider !== 'chinapost' && shipForm.ship_provider !== 'leiyi' || shipForm.logistics_type !== 'DBS'" label="运单号" required>
          <el-input v-model="shipForm.track_number" placeholder="请输入运单号" clearable />
        </el-form-item>
        <el-form-item v-if="shipForm.logistics_type !== 'DBS'" label="物流方式">
          <el-select v-model="shipForm.logistic_method" placeholder="可选，不填则使用订单默认" clearable style="width:100%;">
            <el-option
              v-for="opt in logisticMethodOptions"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item v-if="shipForm.logistics_type === 'DBS' && shipForm.ship_provider === 'manual'" label="物流商名称">
          <el-input v-model="shipForm.provider_name" placeholder="例如: China Post, YANWEN" />
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="shipDialogVisible = false">取消</el-button>
        <el-button v-if="shipForm.ship_provider === 'chinapost'" type="primary" :loading="shipping" @click="submitChinaPostCreate">邮政下单</el-button>
        <el-button v-if="shipForm.ship_provider === 'leiyi'" type="primary" :loading="shipping" @click="submitSz56tCreate">雷翼下单</el-button>
        <el-button type="success" :loading="shipping" @click="submitShip">确认发货</el-button>
      </div>
    </el-dialog>

    <!-- 同步对话框 -->
    <el-dialog title="同步订单" :visible.sync="syncDialogVisible" width="460px">
      <el-form label-width="100px">
        <el-form-item label="指定店铺">
          <el-select v-model="syncForm.shop_id" placeholder="不选则同步所有店铺" clearable style="width: 100%;">
            <el-option v-for="s in shopOptions" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="更新时间范围">
          <el-date-picker
            v-model="syncDateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
            style="width: 100%;"
          />
        </el-form-item>
      </el-form>
      <div style="color: #999; font-size: 12px; padding: 0 10px;">注意：不选日期范围将同步全部订单，数据量大时可能耗时较长</div>
      <div slot="footer">
        <el-button @click="syncDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="syncing" @click="submitSync">开始同步</el-button>
      </div>
    </el-dialog>

    <el-dialog title="同步进度" :visible.sync="syncProgressDialogVisible" width="620px" :close-on-click-modal="false">
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
            <span v-if="row.error" style="color: #f56c6c;">{{ row.error }}</span>
            <span v-else style="color: #67c23a;">成功</span>
          </template>
        </el-table-column>
      </el-table>

      <div slot="footer">
        <el-button @click="syncProgressDialogVisible = false">关闭</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {fetchOrderList, fetchOrderStatusCounts, syncOrdersStart, fetchSyncProgress, updateOrderBackendFields, shipOrder, getOrderLabel, exportOrders, chinaPostCreateOrder, chinaPostGetLabel, sz56tCreateOrder, sz56tGetLabel, sz56tGetTrackingNumber} from '@/api/order'
import {fetchShopList} from '@/api/shop'
import {fetchDictByCode} from '@/api/system'
import Pagination from '@/components/Pagination'
import { getToken } from '@/utils/auth'

const ORDER_DISPLAY_STATUS_LABELS = {
  Unknown: '未知',
  PlaceOrderSuccess: '待付款',
  PaymentPending: '付款处理中',
  WaitExamineMoney: '等待确认收款',
  WaitGroup: '团购中',
  WaitSendGoods: '待发货',
  PartialSendGoods: '部分发货',
  WaitAcceptGoods: '待收货',
  InCancel: '申请取消',
  Complete: '已完成',
  Close: '已关闭',
  InFrozen: '冻结中',
  InIssue: '争议中',
}

const ORDER_STATUS_LABELS = {
  Created: '已创建',
  Cancelled: '已取消',
  Finished: '已完成',
  Closed: '已关闭',
}

const PAYMENT_STATUS_LABELS = {
  Hold: '待付款',
  Paid: '已付款',
  Refunded: '已退款',
  PartiallyRefunded: '部分退款',
  PaymentProcessing: '付款处理中',
}

const DELIVERY_STATUS_LABELS = {
  Init: '待发货',
  Processing: '处理中',
  Shipped: '已发货',
  Delivered: '已送达',
  Returned: '已退回',
  Cancelled: '已取消',
}

const ANTIFRAUD_STATUS_LABELS = {
  Passed: '通过',
  InReview: '审核中',
  Rejected: '未通过',
}

const ISSUE_STATUS_LABELS = {
  NoDispute: '无争议',
  InProcess: '争议处理中',
  Finished: '争议已解决',
}

const LOGISTICS_TYPE_LABELS = {
  DBS: '卖家配送',
  FBS: '平台配送',
}

const SYNC_STATUS_LABELS = {
  pending: '排队中',
  running: '同步中',
  completed: '已完成',
  failed: '失败',
}

const FINISH_REASON_LABELS = {
  PaymentTimeout: '付款时间已过',
  ShippingTimeout: '发货时间已过',
  BuyerNotPickUpPosting: '买家未按时取货',
  CancelledByBuyer: '买家取消已付款订单',
  SecurityClose: '平台安全风控关闭',
  LogisticOrderToPostingMapFailed: '创建发货单失败',
  CancelledBySeller: '卖家取消订单',
  BuyerDoesNotWantOrder: '买家不想要订单',
  BuyerWantChangeProduct: '买家想更换商品',
  BuyerChangeCoupon: '买家想更换优惠券',
  BuyerChangeMailAddress: '买家更改收货地址',
  BuyerChangeLogistic: '买家更改配送方式',
  BuyerCannotPayment: '买家未付款',
  BuyerOtherReasons: '买家其他原因',
  ProductNotEnough: '库存不足',
  SellerDidNotUseBuyerLogisticType: '卖家未使用买家配送方式',
  BuyerCannotContactSeller: '买家无法联系卖家',
  SellerRiseOrderAmount: '卖家提高订单价格',
  CancelGroupBuyAfterPay: '付款后团购取消',
  GroupBuyFailure: '团购失败',
  FreightCommitDayNotMatch: '未按承诺时间发货',
  ConfirmedByBuyer: '买家确认收货',
  AutoConfirm: '系统自动确认收货',
  ConfirmedByLogistic: '物流确认收货',
}

const STATUS_TAG_TYPE = {
  WaitSendGoods: 'warning',
  Complete: 'success',
  Close: 'info',
  InIssue: 'danger',
  InFrozen: 'danger',
  WaitAcceptGoods: '',
}

const ORDER_DICT_CODE = {
  orderDisplayStatus: 'ae_order_display_status',
  orderStatus: 'ae_order_status',
  paymentStatus: 'ae_payment_status',
  deliveryStatus: 'ae_delivery_status',
  antifraudStatus: 'ae_antifraud_status',
  issueStatus: 'ae_issue_status',
  logisticsType: 'ae_logistics_type',
  finishReason: 'ae_finish_reason',
  syncStatus: 'order_sync_status',
}

export default {
  name: 'OrderManage',
  components: {Pagination},
  data() {
    return {
      list: [],
      total: 0,
      listLoading: false,
      selectedOrders: [],
      statusCounts: {},
      shopOptions: [],
      dateRange: [],
      purchaseDateRange: [],
      shippingDateRange: [],
      listQuery: {
        page: 1,
        limit: 20,
        display_status: '',
        shop_id: '',
        shop_keyword: '',
        ae_order_id: '',
        tracking_number: '',
        receiver_name: '',
        receiver_phone: '',
        buyer_name: '',
        buyer_phone: '',
        address_keyword: '',
        seller_comment: '',
        admin_remark: '',
        has_purchase_image: '',
        has_shipping_image: '',
        issue_status: '',
        date_start: '',
        date_end: '',
        purchase_date_start: '',
        purchase_date_end: '',
        shipping_date_start: '',
        shipping_date_end: '',
      },
      showFilter: [],
      statusTabs: [
        {key: '', label: '所有订单', countKey: 'all'},
        {key: 'WaitSendGoods', label: '待处理', countKey: 'WaitSendGoods'},
        {key: 'PaymentPending', label: '已出单待发货', countKey: 'PaymentPending'},
        {key: 'MarkedShip', label: '标记发货', countKey: 'MarkedShip'},
        {key: 'PartialSendGoods', label: '实际发货', countKey: 'PartialSendGoods'},
        {key: 'Shipped', label: '未实发货', countKey: 'Shipped'},
        {key: 'WaitAcceptGoods', label: '在途中', countKey: 'WaitAcceptGoods'},
        {key: 'Close', label: '退回', countKey: 'Close'},
        {key: 'Complete', label: '已完成', countKey: 'Complete'},
        {key: 'InIssue', label: '争议退款', countKey: 'InIssue'},
        {key: 'taken', label: '已取件', countKey: 'all'},
      ],
      // 发货对话框
      shipDialogVisible: false,
      shipping: false,
      labelLoading: false,
      shipForm: {
        id: null,
        track_number: '',
        logistic_method: '',
        logistics_type: '',
        ship_provider: 'manual',
        provider_name: 'China Post',
        biz_product_no: '001',
        weight: 100
      },
      logisticMethodOptions: [],
      // 备注
      commentDialogVisible: false,
      commentTemp: {
        id: null,
        admin_remark: '',
        purchase_image: '',
        shipping_image: '',
        purchase_date: '',
        shipping_date: '',
        lianlian_fee: 0,
        purchase_amount: 0,
        express_fee: 0,
        logistics_fee: 0,
        logistics_template: 'online',
        eub_amazon_ratio: 0,
        eub_base_fee: 0,
        calculated_logistics_fee: 0,
        logistics_fee_override: false,
        apply_qianze_at: '',
        ship_qianze_at: '',
      },
      uploadUrl: process.env.VUE_APP_BASE_API + '/files/upload',
      uploadHeaders: { Authorization: 'Bearer ' + getToken() },
      // 同步
      syncDialogVisible: false,
      syncForm: {shop_id: ''},
      syncDateRange: [],
      syncing: false,
      exporting: false,
      syncTaskId: null,
      syncProgressDialogVisible: false,
      syncProgress: {
        status: '',
        progress: 0,
        total_shops: 0,
        processed_shops: 0,
        failed_shops: 0,
        synced_orders: 0,
        current_shop_name: '',
        details: [],
      },
      syncPollTimer: null,
      dictLabelMap: {},
      issueStatusOptions: [],
    }
  },
  computed: {
    shipDialogTitle() {
      const type = (this.shipForm.logistics_type || '').toUpperCase()
      return type === 'DBS' ? 'DBS 线下发货' : '实际发货'
    }
  },
  created() {
    this.loadShops()
    this.loadOrderDicts()
    this.getStatusCounts()
    this.getList()
  },
  methods: {
    loadShops() {
      fetchShopList({page: 1, limit: 200}).then(res => {
        this.shopOptions = res.data.items || []
      })
    },
    async loadOrderDicts() {
      const targets = Object.values(ORDER_DICT_CODE)
      const map = {}
      await Promise.all(targets.map(code => {
        return fetchDictByCode(code).then(res => {
          const items = (res.data || []).filter(i => Number(i.status) === 1)
          map[code] = items.reduce((acc, item) => {
            acc[String(item.value)] = item.label
            return acc
          }, {})
        }).catch(() => {
          map[code] = {}
        })
      }))
      this.dictLabelMap = map

      const issueFromDict = Object.entries(map[ORDER_DICT_CODE.issueStatus] || {}).map(([value, label]) => ({ value, label }))
      this.issueStatusOptions = issueFromDict.length > 0
        ? issueFromDict
        : Object.entries(ISSUE_STATUS_LABELS).map(([value, label]) => ({ value, label }))
    },
    getStatusCounts() {
      fetchOrderStatusCounts({shop_id: this.listQuery.shop_id}).then(res => {
        this.statusCounts = res.data || {}
      })
    },
    getList() {
      this.listLoading = true
      const query = Object.assign({}, this.listQuery)
      if (this.dateRange && this.dateRange.length === 2) {
        query.date_start = this.dateRange[0]
        query.date_end = this.dateRange[1]
      }
      if (this.purchaseDateRange && this.purchaseDateRange.length === 2) {
        query.purchase_date_start = this.purchaseDateRange[0]
        query.purchase_date_end = this.purchaseDateRange[1]
      }
      if (this.shippingDateRange && this.shippingDateRange.length === 2) {
        query.shipping_date_start = this.shippingDateRange[0]
        query.shipping_date_end = this.shippingDateRange[1]
      }
      fetchOrderList(query).then(res => {
        this.list = res.data.items || []
        this.total = res.data.total || 0
        this.listLoading = false
      }).catch(() => { this.listLoading = false })
    },
    switchTab(key) {
      this.listQuery.display_status = key
      this.listQuery.page = 1
      this.getList()
    },
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
      this.getStatusCounts()
    },
    resetFilter() {
      this.dateRange = []
      this.purchaseDateRange = []
      this.shippingDateRange = []
      this.listQuery = {
        page: 1, limit: 20, display_status: this.listQuery.display_status,
        shop_id: '', shop_keyword: '', ae_order_id: '', tracking_number: '',
        receiver_name: '', receiver_phone: '', buyer_name: '', buyer_phone: '',
        address_keyword: '', seller_comment: '', admin_remark: '',
        has_purchase_image: '', has_shipping_image: '',
        issue_status: '', date_start: '', date_end: '',
        purchase_date_start: '', purchase_date_end: '',
        shipping_date_start: '', shipping_date_end: '',
      }
      this.getList()
    },
    handleSync() {
      this.syncDialogVisible = true
    },
    submitSync() {
      this.syncing = true
      const params = {shop_id: this.syncForm.shop_id || undefined}
      if (this.syncDateRange && this.syncDateRange.length === 2) {
        params.date_start = this.syncDateRange[0]
        params.date_end = this.syncDateRange[1]
      }
      syncOrdersStart(params).then(res => {
        this.syncing = false
        this.syncDialogVisible = false
        this.syncTaskId = res.data.task_id
        this.syncProgressDialogVisible = true
        this.syncProgress.status = 'running'
        this.syncProgress.progress = 0
        this.startSyncPolling()
      }).catch(() => { this.syncing = false })
    },
    startSyncPolling() {
      this.stopSyncPolling()
      this.pollSyncProgress()
      this.syncPollTimer = setInterval(() => {
        this.pollSyncProgress()
      }, 1500)
    },
    stopSyncPolling() {
      if (this.syncPollTimer) {
        clearInterval(this.syncPollTimer)
        this.syncPollTimer = null
      }
    },
    pollSyncProgress() {
      if (!this.syncTaskId) return
      fetchSyncProgress({task_id: this.syncTaskId}).then(res => {
        const d = res.data || {}
        this.syncProgress = {
          status: d.status || '',
          progress: d.progress || 0,
          total_shops: d.total_shops || 0,
          processed_shops: d.processed_shops || 0,
          failed_shops: d.failed_shops || 0,
          synced_orders: d.synced_orders || 0,
          current_shop_name: d.current_shop_name || '',
          details: d.details || [],
        }

        if (d.status === 'completed' || d.status === 'failed') {
          this.stopSyncPolling()
          if (d.status === 'completed') {
            this.$notify({title: '同步完成', message: `共同步 ${d.synced_orders || 0} 条订单`, type: 'success', duration: 3000})
          } else {
            this.$notify({title: '同步失败', message: d.message || '请查看同步明细', type: 'error', duration: 4000})
          }
          this.getStatusCounts()
          this.getList()
        }
      })
    },
    openCommentDialog(order) {
      this.commentTemp = {
        id: order.id,
        admin_remark: order.admin_remark || '',
        purchase_image: order.purchase_image || '',
        shipping_image: order.shipping_image || '',
        purchase_date: order.purchase_date || '',
        shipping_date: order.shipping_date || '',
        lianlian_fee: order.lianlian_fee || 0,
        purchase_amount: order.purchase_amount || 0,
        express_fee: order.express_fee || 0,
        logistics_fee: order.logistics_fee || 0,
        logistics_template: order.logistics_template || 'online',
        eub_amazon_ratio: Number(order.eub_amazon_ratio || 0),
        eub_base_fee: Number(order.eub_base_fee || 0),
        calculated_logistics_fee: Number(order.calculated_logistics_fee || 0),
        logistics_fee_override: Boolean(order.logistics_fee_override),
        apply_qianze_at: order.apply_qianze_at || '',
        ship_qianze_at: order.ship_qianze_at || '',
      }
      if (this.commentTemp.logistics_template === 'offline_epacket') {
        this.recalcEubLogisticsFee()
      }
      this.commentDialogVisible = true
    },
    handleTemplateChange() {
      if (this.commentTemp.logistics_template === 'offline_epacket') {
        this.recalcEubLogisticsFee()
      }
    },
    recalcEubLogisticsFee() {
      const purchase = Number(this.commentTemp.purchase_amount || 0)
      const ratio = Number(this.commentTemp.eub_amazon_ratio || 0)
      const base = Number(this.commentTemp.eub_base_fee || 0)
      const calc = Number((purchase * ratio / 100 + base).toFixed(2))
      this.commentTemp.calculated_logistics_fee = calc
      if (!this.commentTemp.logistics_fee_override) {
        this.commentTemp.logistics_fee = calc
      }
    },
    markLogisticsFeeManualEdit() {
      this.commentTemp.logistics_fee_override = true
    },
    resetLogisticsFeeToCalculated() {
      this.commentTemp.logistics_fee_override = false
      this.commentTemp.logistics_fee = Number(this.commentTemp.calculated_logistics_fee || 0)
    },
    submitComment() {
      if (this.commentTemp.logistics_template === 'offline_epacket') {
        this.recalcEubLogisticsFee()
      }
      updateOrderBackendFields(this.commentTemp).then(() => {
        this.commentDialogVisible = false
        const order = this.list.find(o => o.id === this.commentTemp.id)
        if (order) {
          order.admin_remark = this.commentTemp.admin_remark
          order.purchase_image = this.commentTemp.purchase_image
          order.shipping_image = this.commentTemp.shipping_image
          order.purchase_date = this.commentTemp.purchase_date
          order.shipping_date = this.commentTemp.shipping_date
          order.lianlian_fee = this.commentTemp.lianlian_fee
          order.purchase_amount = this.commentTemp.purchase_amount
          order.express_fee = this.commentTemp.express_fee
          order.logistics_fee = this.commentTemp.logistics_fee
          order.logistics_template = this.commentTemp.logistics_template
          order.eub_amazon_ratio = this.commentTemp.eub_amazon_ratio
          order.eub_base_fee = this.commentTemp.eub_base_fee
          order.calculated_logistics_fee = this.commentTemp.calculated_logistics_fee
          order.logistics_fee_override = this.commentTemp.logistics_fee_override
          order.apply_qianze_at = this.commentTemp.apply_qianze_at
          order.ship_qianze_at = this.commentTemp.ship_qianze_at
        }
        this.$notify({title: '成功', message: '订单后台信息已更新', type: 'success', duration: 2000})
      })
    },
    buildExportQuery() {
      const query = Object.assign({}, this.listQuery)
      if (this.dateRange && this.dateRange.length === 2) {
        query.date_start = this.dateRange[0]
        query.date_end = this.dateRange[1]
      }
      return query
    },
    handleExport() {
      this.exporting = true
      exportOrders(this.buildExportQuery()).then(res => {
        const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8;' })
        const link = document.createElement('a')
        const url = window.URL.createObjectURL(blob)
        link.href = url
        link.download = `orders_${new Date().getTime()}.csv`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
        this.$message.success('导出成功')
      }).catch(err => {
        this.$message.error(err.message || '导出失败')
      }).finally(() => {
        this.exporting = false
      })
    },
    handleImageUploadSuccess(response, field) {
      if (response.code === 20000 && response.data && response.data.url) {
        this.commentTemp[field] = response.data.url
        this.$message.success('上传成功')
      } else {
        this.$message.error(response.message || '上传失败')
      }
    },
    canPrintLabel(order) {
      return order.sz56t_order_id || (order.tracking_number && order.logistics_type === 'DBS') || order.logistic_order_id
    },
    handlePrintLabel(order) {
      // 优先级：雷翼 > 邮政 > AliExpress FBS
      if (order.sz56t_order_id) {
        this.labelLoading = true
        sz56tGetLabel({ id: order.id }).then(res => {
          const data = res.data || {}
          if (data.label_url) {
            window.open(data.label_url, '_blank')
            return
          }
          this.$message.warning(res.message || '雷翼面单暂不可用')
        }).catch(err => {
          this.$message.error(err.message || '雷翼面单获取失败')
        }).finally(() => { this.labelLoading = false })
      } else if (order.tracking_number && (order.logistics_type || '').toUpperCase() === 'DBS') {
        this.labelLoading = true
        chinaPostGetLabel({ id: order.id }).then(res => {
          const data = res.data || {}
          if (data.pdf_base64) {
            const binary = atob(data.pdf_base64)
            const bytes = new Uint8Array(binary.length)
            for (let i = 0; i < binary.length; i++) { bytes[i] = binary.charCodeAt(i) }
            const blob = new Blob([bytes], { type: 'application/pdf' })
            const url = window.URL.createObjectURL(blob)
            window.open(url, '_blank')
            setTimeout(() => window.URL.revokeObjectURL(url), 60000)
            return
          }
          this.$message.warning(res.message || '邮政面单暂不可用')
        }).catch(err => {
          this.$message.error(err.message || '邮政面单获取失败')
        }).finally(() => { this.labelLoading = false })
      } else if (order.logistic_order_id) {
        this.labelLoading = true
        getOrderLabel({ id: order.id }).then(res => {
          const data = res.data || {}
          if (data.label_url) {
            window.open(data.label_url, '_blank')
            return
          }
          this.$message.warning(res.message || '面单暂不可用')
        }).catch(err => {
          this.$message.error(err.message || '面单获取失败')
        }).finally(() => { this.labelLoading = false })
      }
    },
    handleShip(order) {
      const isDBS = (order.logistics_type || '').toUpperCase() === 'DBS'
      this.shipForm = {
        id: order.id,
        track_number: order.tracking_number || '',
        logistic_method: order.logistics_type || '',
        logistics_type: order.logistics_type || '',
        ship_provider: isDBS ? 'chinapost' : 'aliexpress',
        provider_name: 'China Post',
        biz_product_no: '001',
        weight: 100
      }
      // 从字典加载物流方式选项
      this.logisticMethodOptions = Object.entries(
        this.dictLabelMap['ae_logistic_method'] || {}
      ).map(([value, label]) => ({ value, label }))
      this.shipDialogVisible = true
    },
    submitChinaPostCreate() {
      this.shipping = true
      chinaPostCreateOrder({
        id: this.shipForm.id,
        biz_product_no: this.shipForm.biz_product_no,
        weight: this.shipForm.weight
      }).then(res => {
        const waybillNo = res.data && res.data.waybill_no
        this.$message.success(res.message || '邮政下单成功')
        if (waybillNo) {
          this.shipForm.track_number = waybillNo
          // 更新列表
          const target = this.list.find(o => o.id === this.shipForm.id)
          if (target) {
            target.tracking_number = waybillNo
            target.logistics_template = 'offline_epacket'
          }
        }
      }).catch(err => {
        this.$message.error(err.message || '邮政下单失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    submitSz56tCreate() {
      this.shipping = true
      sz56tCreateOrder({
        id: this.shipForm.id,
        weight: this.shipForm.weight
      }).then(res => {
        const data = res.data || {}
        this.$message.success(res.message || '雷翼下单成功')
        if (data.tracking_number) {
          this.shipForm.track_number = data.tracking_number
        }
        // 更新列表
        const target = this.list.find(o => o.id === this.shipForm.id)
        if (target) {
          if (data.tracking_number) target.tracking_number = data.tracking_number
          if (data.order_id) target.sz56t_order_id = data.order_id
          target.logistics_template = 'offline_leiyi'
        }
        if (data.is_delay) {
          this.$message.info('单号延迟获取，稍后可点击"获取跟踪号"')
        }
      }).catch(err => {
        this.$message.error(err.message || '雷翼下单失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    submitShip() {
      if (!this.shipForm.track_number) {
        this.$message.warning('请输入运单号')
        return
      }
      this.shipping = true
      const payload = {
        id: this.shipForm.id,
        track_number: this.shipForm.track_number,
        logistic_method: this.shipForm.logistic_method,
        ship_provider: this.shipForm.ship_provider,
        provider_name: this.shipForm.provider_name
      }
      shipOrder(payload).then(res => {
        this.$message.success(res.message || '发货成功')
        this.shipDialogVisible = false
        // 更新本地列表中该订单的运单号
        const target = this.list.find(o => o.id === this.shipForm.id)
        if (target) {
          target.tracking_number = this.shipForm.track_number
          target.actual_ship_at = res.data && res.data.actual_ship_at || new Date().toISOString()
        }
      }).catch(err => {
        this.$message.error(err.message || '发货失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    handleMarkShip(order) {
      this.$message.info('更新订单：' + order.ae_order_id)
    },
    getStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.orderDisplayStatus, ORDER_DISPLAY_STATUS_LABELS)
    },
    translateByCode(value, code, fallbackDict = {}) {
      const dict = this.dictLabelMap[code] || {}
      return dict[String(value)] || fallbackDict[String(value)] || value || '-'
    },
    getOrderStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.orderStatus, ORDER_STATUS_LABELS)
    },
    getPaymentStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.paymentStatus, PAYMENT_STATUS_LABELS)
    },
    getDeliveryStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.deliveryStatus, DELIVERY_STATUS_LABELS)
    },
    getAntifraudStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.antifraudStatus, ANTIFRAUD_STATUS_LABELS)
    },
    getIssueStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.issueStatus, ISSUE_STATUS_LABELS)
    },
    getFinishReasonLabel(reason) {
      return this.translateByCode(reason, ORDER_DICT_CODE.finishReason, FINISH_REASON_LABELS)
    },
    getLogisticsTypeLabel(type) {
      return this.translateByCode(type, ORDER_DICT_CODE.logisticsType, LOGISTICS_TYPE_LABELS)
    },
    getSyncStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.syncStatus, SYNC_STATUS_LABELS)
    },
    getStatusTagType(status) {
      return STATUS_TAG_TYPE[status] || ''
    },
    formatDate(d) {
      if (!d) return '-'
      return d.replace('T', ' ').substring(0, 16)
    },
    formatAddress(order) {
      const parts = [order.receiver_country, order.receiver_region, order.receiver_city, order.receiver_street, order.receiver_zip]
      return parts.filter(Boolean).join(', ') || order.delivery_address || '-'
    },
    getCategoryFromSku(item) {
      if (item.properties && typeof item.properties === 'object') {
        return Object.values(item.properties).slice(0, 2).join(' / ')
      }
      return item.name ? item.name.substring(0, 30) : '-'
    },
    calcItemRevenue(item) {
      return (parseFloat(item.item_estimate_revenue || 0)).toFixed(2)
    },
    calcProfitRate(order) {
      const profit = this.calcProfit(order)
      const base = parseFloat(order.total_amount || 0)
      if (!base) return '0.00'
      return ((profit / base) * 100).toFixed(2)
    },
    calcFee(order) {
      return (parseFloat(order.platform_fee || 0) + parseFloat(order.affiliate_fee || 0)).toFixed(2)
    },
    calcTotalBack(order) {
      return parseFloat(order.estimate_revenue || 0).toFixed(2)
    },
    calcProfit(order) {
      const total = parseFloat(order.total_amount || 0)
      const fee = parseFloat(order.platform_fee || 0) + parseFloat(order.affiliate_fee || 0)
      const lianlian = parseFloat(order.lianlian_fee || 0)
      const purchase = parseFloat(order.purchase_amount || 0)
      const logistics = parseFloat(order.logistics_fee || 0)
      return +(total - fee - lianlian - purchase - logistics).toFixed(2)
    },
    getItemCount(order) {
      return order.items ? order.items.reduce((s, i) => s + (i.quantity || 1), 0) : 0
    },
    copyText(text) {
      navigator.clipboard.writeText(text).then(() => {
        this.$message.success('已复制')
      })
    },
    toggleSelect(id) {
      const idx = this.selectedOrders.indexOf(id)
      if (idx > -1) {
        this.selectedOrders.splice(idx, 1)
      } else {
        this.selectedOrders.push(id)
      }
    },
    onImgError(e) {
      e.target.style.display = 'none'
    },
  },
  beforeDestroy() {
    this.stopSyncPolling()
  },
}
</script>

<style scoped>
.order-page { padding-bottom: 30px; }
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
.status-tab:hover { color: #409eff; }

.order-card {
  border: 1px solid #e8e8e8;
  border-radius: 4px;
  margin-bottom: 12px;
}
.order-card-body {
  display: flex;
  gap: 0;
}
.order-check-col {
  width: 40px;
  flex-shrink: 0;
  padding: 12px 4px 12px 12px;
  display: flex;
  align-items: flex-start;
  border-right: 1px solid #f0f0f0;
}
.order-product-col {
  flex: 1;
  padding: 10px 12px;
  border-right: 1px solid #f0f0f0;
  min-width: 0;
}
.product-shop-line,
.product-order-line {
  margin-bottom: 4px;
  font-size: 12px;
  color: #666;
}
.shop-tag {
  font-weight: 600;
  color: #333;
  background: #e8f4ff;
  padding: 2px 8px;
  border-radius: 3px;
  font-size: 12px;
  margin-right: 8px;
}
.order-id {
  font-family: monospace;
  font-size: 13px;
  color: #333;
}
.order-meta { font-size: 12px; }
.order-item-row {
  display: flex;
  gap: 8px;
  padding: 6px 0;
  border-bottom: 1px solid #fafafa;
}
.order-item-row:last-child { border-bottom: none; }
.item-img { width: 60px; height: 60px; object-fit: cover; border: 1px solid #eee; border-radius: 3px; flex-shrink: 0; }
.item-info { font-size: 12px; min-width: 0; }
.item-info > div { margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.item-category { font-weight: 600; color: #333; }
.item-id, .item-sku { color: #999; }
.item-qty { color: #666; }
.order-status-col {
  width: 150px;
  flex-shrink: 0;
  padding: 12px;
  font-size: 12px;
  border-right: 1px solid #f0f0f0;
}
.fee-row { margin-bottom: 4px; color: #666; }
.order-amount-col {
  width: 160px;
  flex-shrink: 0;
  padding: 12px;
  font-size: 12px;
  border-right: 1px solid #f0f0f0;
}
.amount-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
.label { color: #999; }
.highlight { font-weight: 600; color: #e6a23c; }
.success-text { color: #67c23a; font-weight: 600; }

.order-receiver-col {
  width: 240px;
  flex-shrink: 0;
  padding: 12px;
  font-size: 12px;
  color: #333;
  border-right: 1px solid #f0f0f0;
  line-height: 1.8;
}
.order-receiver-col .label { color: #999; }

.order-ops-col {
  width: 100px;
  flex-shrink: 0;
  padding: 12px 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
}

.order-comment {
  padding: 6px 14px;
  background: #fffbe6;
  font-size: 12px;
  color: #856404;
  border-top: 1px solid #f0e68c;
}
</style>
