<template>
  <div class="app-container order-page">
    <order-filter-panel
      :backend-status-tabs="backendStatusTabs"
      :backend-status-counts="backendStatusCounts"
      :status-tabs="statusTabs"
      :status-counts="statusCounts"
      :list-query="listQuery"
      :show-filter.sync="showFilter"
      :date-range.sync="dateRange"
      :purchase-date-range.sync="purchaseDateRange"
      :shipping-date-range.sync="shippingDateRange"
      :shop-options="shopOptions"
      :issue-status-options="issueStatusOptions"
      :syncing="syncing"
      :exporting="exporting"
      :batch-backend-status.sync="batchBackendStatus"
      :backend-status-options="backendStatusOptions"
      :selected-count="selectedOrders.length"
      :total="total"
      @switch-backend-status="switchBackendStatusTab"
      @switch-status="switchTab"
      @filter="handleFilter"
      @reset="resetFilter"
      @sync="handleSync"
      @export="handleExport"
      @batch-update-backend-status="handleBatchUpdateBackendStatus"
    />

    <order-list-section
      :list="list"
      :list-loading="listLoading"
      :selected-orders="selectedOrders"
      :is-all-current-page-selected="isAllCurrentPageSelected"
      :is-current-page-indeterminate="isCurrentPageIndeterminate"
      :transfer-sheet-loading-id="transferSheetLoadingId"
      :dict-label-map="dictLabelMap"
      :cny-exchange-rate="cnyExchangeRate"
      @toggle-select="toggleSelect"
      @toggle-select-all-current-page="toggleSelectAllCurrentPage"
      @copy-text="copyText"
      @print-label="handlePrintLabel"
      @transfer-sheet="handleTransferSheet"
      @ship="handleShip"
      @mark-ship="handleMarkShip"
      @open-comment-dialog="openCommentDialog"
    />

    <pagination v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit" @pagination="getList" />

    <order-comment-dialog
      :visible.sync="commentDialogVisible"
      :comment-temp="commentTemp"
      :backend-status-options="backendStatusOptions"
      :upload-url="uploadUrl"
      :upload-headers="uploadHeaders"
      @template-change="handleTemplateChange"
      @recalc-eub-logistics-fee="recalcEubLogisticsFee"
      @mark-logistics-fee-manual-edit="markLogisticsFeeManualEdit"
      @reset-logistics-fee-to-calculated="resetLogisticsFeeToCalculated"
      @image-upload-success="handleImageUploadSuccess"
      @save="submitComment"
    />

    <order-ship-dialog
      :visible.sync="shipDialogVisible"
      :title="shipDialogTitle"
      :ship-form="shipForm"
      :shipping="shipping"
      :dict-label-map="dictLabelMap"
      @submit-chinapost-create="submitChinaPostCreate"
      @submit-sz56t-create="submitSz56tCreate"
      @submit-ship="submitShip"
      @submit-fbs-logistic-order="submitFbsLogisticOrder"
      @submit-fbs-handover-list="submitFbsHandoverList"
      @add-fbs-to-handover="addFbsToHandover"
      @remove-fbs-from-handover="removeFbsFromHandover"
      @print-fbs-label="printCurrentFbsLabel"
      @print-fbs-handover-label="printCurrentFbsHandoverLabel"
      @mark-fbs-ready-for-pickup="markCurrentFbsReadyForPickup"
      @transfer-fbs-handover-list="transferCurrentFbsHandoverList"
      @refresh-fbs-workflow="refreshCurrentFbsWorkflow"
      @update-step="updateShipStep"
    />

    <order-sync-dialog
      :visible.sync="syncDialogVisible"
      :sync-form="syncForm"
      :sync-date-range.sync="syncDateRange"
      :syncing="syncing"
      :shop-options="shopOptions"
      @submit="submitSync"
    />

    <order-sync-progress-dialog
      :visible.sync="syncProgressDialogVisible"
      :sync-progress="syncProgress"
      :dict-label-map="dictLabelMap"
    />
  </div>
</template>

<script>
import {
  addFbsLogisticOrdersToHandover,
  batchUpdateOrderBackendStatus,
  chinaPostCreateOrder,
  createFbsHandoverList,
  createFbsLogisticOrder,
  chinaPostGetLabel,
  createOrderTransferSheet,
  exportOrders,
  fetchFbsWorkflow,
  fetchOrderBackendStatusCounts,
  fetchOrderList,
  fetchOrderStatusCounts,
  fetchSyncProgress,
  getOrderLabel,
  printFbsHandoverList,
  readyFbsHandoverForPickup,
  removeFbsLogisticOrdersFromHandover,
  shipOrder,
  syncOrdersStart,
  sz56tCreateOrder,
  sz56tGetLabel,
  transferFbsHandoverList,
  updateOrderBackendFields
} from '@/api/order'
import { fetchShopList } from '@/api/shop'
import { fetchConfigList, fetchDictByCode } from '@/api/system'
import Pagination from '@/components/Pagination'
import { getToken } from '@/utils/auth'
import OrderCommentDialog from './components/OrderCommentDialog'
import OrderFilterPanel from './components/OrderFilterPanel'
import OrderListSection from './components/OrderListSection'
import OrderShipDialog from './components/OrderShipDialog'
import OrderSyncDialog from './components/OrderSyncDialog'
import OrderSyncProgressDialog from './components/OrderSyncProgressDialog'
import {
  ORDER_DICT_CODE,
  createDefaultCommentTemp,
  createDefaultListQuery,
  createDefaultShipForm,
  createDefaultSyncProgress
} from './constants'
import {
  applyChinaPostCreateResult,
  applyCommentTempToOrder,
  applyShipResult,
  applySz56tCreateResult,
  buildBackendStatusTabs,
  buildDictLabelMap,
  buildDictOptions,
  buildExportFilename,
  buildListQuery,
  buildShipItemsFromOrder,
  buildStatusTabs,
  buildSyncParams,
  calculateEubLogisticsFee,
  createPdfObjectUrl,
  getFbsWorkflowStep,
  getBackendStatusLabel,
  isDbsLogisticsType
} from './utils'

export default {
  name: 'OrderManage',
  components: {
    OrderCommentDialog,
    OrderFilterPanel,
    OrderListSection,
    OrderShipDialog,
    OrderSyncDialog,
    OrderSyncProgressDialog,
    Pagination
  },
  data() {
    return {
      list: [],
      total: 0,
      listLoading: false,
      selectedOrders: [],
      statusCounts: {},
      backendStatusCounts: {},
      shopOptions: [],
      dateRange: [],
      purchaseDateRange: [],
      shippingDateRange: [],
      listQuery: createDefaultListQuery(),
      showFilter: [],
      statusTabs: buildStatusTabs(),
      backendStatusTabs: buildBackendStatusTabs(),
      backendStatusOptions: [],
      batchBackendStatus: '',
      shipDialogVisible: false,
      shipping: false,
      transferSheetLoadingId: null,
      shipForm: createDefaultShipForm(),
      commentDialogVisible: false,
      commentTemp: createDefaultCommentTemp(),
      uploadUrl: process.env.VUE_APP_BASE_API + '/files/upload',
      uploadHeaders: { Authorization: 'Bearer ' + getToken() },
      syncDialogVisible: false,
      syncForm: { shop_id: '' },
      syncDateRange: [],
      syncing: false,
      exporting: false,
      syncTaskId: null,
      syncProgressDialogVisible: false,
      syncProgress: createDefaultSyncProgress(),
      syncPollTimer: null,
      dictLabelMap: {},
      issueStatusOptions: [],
      cnyExchangeRate: 7.2
    }
  },
  computed: {
    shipDialogTitle() {
      return isDbsLogisticsType(this.shipForm.logistics_type) ? 'DBS 线下发货' : 'FBS 发货流程'
    },
    currentPageOrderIds() {
      return this.list.map(order => order.id)
    },
    isAllCurrentPageSelected() {
      if (!this.currentPageOrderIds.length) return false
      return this.currentPageOrderIds.every(id => this.selectedOrders.includes(id))
    },
    isCurrentPageIndeterminate() {
      if (!this.currentPageOrderIds.length) return false
      const selectedCount = this.currentPageOrderIds.filter(id => this.selectedOrders.includes(id)).length
      return selectedCount > 0 && selectedCount < this.currentPageOrderIds.length
    }
  },
  created() {
    this.loadShops()
    this.loadOrderDicts()
    this.loadFinanceConfig()
    this.getStatusCounts()
    this.getBackendStatusCounts()
    this.getList()
  },
  beforeDestroy() {
    this.stopSyncPolling()
  },
  methods: {
    loadFinanceConfig() {
      fetchConfigList({ group: 'finance' }).then(res => {
        const configs = res.data || []
        const rateConfig = configs.find(item => item.key === 'cny_exchange_rate')
        const rate = parseFloat(rateConfig && rateConfig.value)
        this.cnyExchangeRate = rate > 0 ? rate : 7.2
      }).catch(() => {
        this.cnyExchangeRate = 7.2
      })
    },
    loadShops() {
      fetchShopList({ page: 1, limit: 200 }).then(res => {
        this.shopOptions = res.data.items || []
      })
    },
    async loadOrderDicts() {
      const targets = Object.values(ORDER_DICT_CODE)
      const map = {}
      const optionsByCode = {}

      await Promise.all(targets.map(code => {
        return fetchDictByCode(code).then(res => {
          const items = (res.data || []).filter(item => Number(item.status) === 1)
          map[code] = buildDictLabelMap(items)
          optionsByCode[code] = buildDictOptions(items)
        }).catch(() => {
          map[code] = {}
          optionsByCode[code] = []
        })
      }))

      this.dictLabelMap = map
      this.statusTabs = buildStatusTabs(optionsByCode[ORDER_DICT_CODE.orderDisplayStatus] || [])
      this.issueStatusOptions = optionsByCode[ORDER_DICT_CODE.issueStatus] || []
      this.backendStatusOptions = optionsByCode[ORDER_DICT_CODE.backendStatus] || []
      this.backendStatusTabs = buildBackendStatusTabs(this.backendStatusOptions)
    },
    getStatusCounts() {
      fetchOrderStatusCounts({
        shop_id: this.listQuery.shop_id,
        backend_status: this.listQuery.backend_status
      }).then(res => {
        this.statusCounts = res.data || {}
      })
    },
    getBackendStatusCounts() {
      fetchOrderBackendStatusCounts({
        shop_id: this.listQuery.shop_id
      }).then(res => {
        this.backendStatusCounts = res.data || {}
      })
    },
    getList() {
      this.listLoading = true
      const query = buildListQuery(this.listQuery, this.dateRange, this.purchaseDateRange, this.shippingDateRange)
      fetchOrderList(query).then(res => {
        this.list = res.data.items || []
        this.total = res.data.total || 0
        this.selectedOrders = this.selectedOrders.filter(id => this.list.some(order => order.id === id))
      }).finally(() => {
        this.listLoading = false
      })
    },
    switchTab(key) {
      this.listQuery.display_status = key
      this.listQuery.page = 1
      this.getList()
    },
    switchBackendStatusTab(key) {
      this.listQuery.backend_status = key
      this.listQuery.page = 1
      this.getList()
      this.getStatusCounts()
    },
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
      this.getStatusCounts()
      this.getBackendStatusCounts()
    },
    resetFilter() {
      this.dateRange = []
      this.purchaseDateRange = []
      this.shippingDateRange = []
      this.listQuery = createDefaultListQuery({
        display_status: this.listQuery.display_status,
        backend_status: this.listQuery.backend_status
      })
      this.getList()
      this.getStatusCounts()
      this.getBackendStatusCounts()
    },
    handleSync() {
      this.syncDialogVisible = true
    },
    submitSync() {
      this.syncing = true
      const params = buildSyncParams(this.syncForm, this.syncDateRange)
      syncOrdersStart(params).then(res => {
        this.syncDialogVisible = false
        this.syncTaskId = res.data.task_id
        this.syncProgressDialogVisible = true
        this.syncProgress = createDefaultSyncProgress({
          status: 'running',
          progress: 0
        })
        this.startSyncPolling()
      }).finally(() => {
        this.syncing = false
      })
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

      fetchSyncProgress({ task_id: this.syncTaskId }).then(res => {
        const data = res.data || {}
        this.syncProgress = createDefaultSyncProgress({
          status: data.status || '',
          progress: data.progress || 0,
          total_shops: data.total_shops || 0,
          processed_shops: data.processed_shops || 0,
          failed_shops: data.failed_shops || 0,
          synced_orders: data.synced_orders || 0,
          current_shop_name: data.current_shop_name || '',
          details: data.details || []
        })

        if (data.status === 'completed' || data.status === 'failed') {
          this.stopSyncPolling()
          if (data.status === 'completed') {
            this.$notify({ title: '同步完成', message: `共同步 ${data.synced_orders || 0} 条订单`, type: 'success', duration: 3000 })
          } else {
            this.$notify({ title: '同步失败', message: data.message || '请查看同步明细', type: 'error', duration: 4000 })
          }
          this.getStatusCounts()
          this.getList()
        }
      })
    },
    openCommentDialog(order) {
      this.commentTemp = createDefaultCommentTemp({
        id: order.id,
        admin_remark: order.admin_remark || '',
        backend_status: order.backend_status || '',
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
        ship_qianze_at: order.ship_qianze_at || ''
      })
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
      const calculated = calculateEubLogisticsFee({
        purchaseAmount: this.commentTemp.purchase_amount,
        amazonRatio: this.commentTemp.eub_amazon_ratio,
        baseFee: this.commentTemp.eub_base_fee
      })
      this.commentTemp.calculated_logistics_fee = calculated
      if (!this.commentTemp.logistics_fee_override) {
        this.commentTemp.logistics_fee = calculated
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
        const order = this.list.find(item => item.id === this.commentTemp.id)
        applyCommentTempToOrder(order, this.commentTemp)
        this.getBackendStatusCounts()
        this.$notify({ title: '成功', message: '订单后台信息已更新', type: 'success', duration: 2000 })
      })
    },
    handleBatchUpdateBackendStatus() {
      if (!this.batchBackendStatus) {
        this.$message.warning('请选择要更新的后台状态')
        return
      }
      if (!this.selectedOrders.length) {
        this.$message.warning('请先选择订单')
        return
      }

      const targetLabel = getBackendStatusLabel(this.batchBackendStatus, this.dictLabelMap)
      this.$confirm(`确定将选中的 ${this.selectedOrders.length} 条订单更新为“${targetLabel}”吗？`, '批量更新后台状态', { type: 'warning' })
        .then(() => {
          return batchUpdateOrderBackendStatus({
            ids: this.selectedOrders,
            backend_status: this.batchBackendStatus
          })
        })
        .then(res => {
          this.$message.success(res.message || '批量更新成功')
          this.getList()
          this.getBackendStatusCounts()
        })
        .catch(() => {})
    },
    buildExportQuery() {
      const query = { ...this.listQuery }
      if (Array.isArray(this.dateRange) && this.dateRange.length === 2) {
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
        link.download = buildExportFilename()
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
        return
      }
      this.$message.error(response.message || '上传失败')
    },
    handleTransferSheet(order) {
      this.transferSheetLoadingId = order.id
      createOrderTransferSheet({ id: order.id }).then(res => {
        const data = res.data || {}
        if (data.handover_list_id) {
          this.$set(order, 'handover_list_id', data.handover_list_id)
          if (!order.handover_list_status) {
            this.$set(order, 'handover_list_status', 'Created')
          }
        }
        if (data.label_url) {
          window.open(data.label_url, '_blank')
          this.$message.success(res.message || '交接单已生成并打开')
          return
        }
        this.$message.warning(res.message || '交接单已创建，但未返回可打印链接')
      }).catch(err => {
        this.$message.error(err.message || '交接单处理失败')
      }).finally(() => {
        this.transferSheetLoadingId = null
      })
    },
    handlePrintLabel(order) {
      if (order.sz56t_order_id) {
        sz56tGetLabel({ id: order.id }).then(res => {
          const data = res.data || {}
          if (data.label_url) {
            window.open(data.label_url, '_blank')
            return
          }
          this.$message.warning(res.message || '雷翼面单暂不可用')
        }).catch(err => {
          this.$message.error(err.message || '雷翼面单获取失败')
        })
        return
      }

      if (order.tracking_number && isDbsLogisticsType(order.logistics_type)) {
        chinaPostGetLabel({ id: order.id }).then(res => {
          const data = res.data || {}
          const labelUrl = createPdfObjectUrl(data.pdf_base64)
          if (labelUrl) {
            window.open(labelUrl, '_blank')
            setTimeout(() => window.URL.revokeObjectURL(labelUrl), 60000)
            return
          }
          this.$message.warning(res.message || '邮政面单暂不可用')
        }).catch(err => {
          this.$message.error(err.message || '邮政面单获取失败')
        })
        return
      }

      if (order.logistic_order_id) {
        getOrderLabel({ id: order.id }).then(res => {
          const data = res.data || {}
          if (data.label_url) {
            window.open(data.label_url, '_blank')
            return
          }
          this.$message.warning(res.message || '面单暂不可用')
        }).catch(err => {
          this.$message.error(err.message || '面单获取失败')
        })
      }
    },
    updateShipStep(step) {
      this.shipForm.current_step = step
    },
    getCurrentShipOrder() {
      return this.list.find(order => order.id === this.shipForm.id) || null
    },
    buildFbsItemsPayload() {
      return (this.shipForm.items || [])
        .map(item => {
          const payload = {
            quantity: Number(item.quantity || 0),
            sku_id: Number(item.sku_id || 0)
          }
          const productSourceId = item.product_source_id
          if (productSourceId !== '' && productSourceId !== null && productSourceId !== undefined) {
            payload.product_source_id = Number(productSourceId)
          }
          return payload
        })
        .filter(item => item.quantity > 0 && item.sku_id > 0)
    },
    syncLocalOrderWorkflow(workflow) {
      const target = this.getCurrentShipOrder()
      if (!target || !workflow) return

      if (Object.prototype.hasOwnProperty.call(workflow, 'logistic_order_id')) {
        this.$set(target, 'logistic_order_id', workflow.logistic_order_id || null)
      }
      if (Object.prototype.hasOwnProperty.call(workflow, 'handover_list_id')) {
        this.$set(target, 'handover_list_id', workflow.handover_list_id || null)
      }
      if (workflow.handover_list && Object.prototype.hasOwnProperty.call(workflow.handover_list, 'status')) {
        this.$set(target, 'handover_list_status', workflow.handover_list.status || '')
      }

      const trackingNumber = workflow.tracking_number || (workflow.logistic_order && workflow.logistic_order.platform_tracking_code) || ''
      if (trackingNumber) {
        target.tracking_number = trackingNumber
      }
    },
    applyFbsWorkflow(workflow) {
      if (!workflow) return

      const logisticOrder = workflow.logistic_order || {}
      const handoverList = workflow.handover_list || {}
      const trackingNumber = workflow.tracking_number || logisticOrder.platform_tracking_code || this.shipForm.track_number || ''

      this.shipForm.trade_order_id = workflow.trade_order_id || this.shipForm.trade_order_id
      this.shipForm.logistic_order_id = workflow.logistic_order_id || null
      this.shipForm.logistic_order_status = logisticOrder.status || ''
      this.shipForm.logistic_order_state_status_name = logisticOrder.state_status_name || ''
      this.shipForm.platform_tracking_code = logisticOrder.platform_tracking_code || trackingNumber
      this.shipForm.cut_off_date = logisticOrder.cut_off_date || ''
      this.shipForm.handover_list_id = workflow.handover_list_id || null
      this.shipForm.handover_list_status = handoverList.status || ''
      this.shipForm.handover_arrival_date = handoverList.arrival_date || ''
      this.shipForm.handover_shipment_type = handoverList.shipment_type || ''
      this.shipForm.handover_created_at = handoverList.gmt_create || ''
      if (trackingNumber) {
        this.shipForm.track_number = trackingNumber
      }
      this.shipForm.current_step = getFbsWorkflowStep(this.shipForm)
      this.syncLocalOrderWorkflow(workflow)
    },
    refreshCurrentFbsWorkflow() {
      if (!this.shipForm.id || isDbsLogisticsType(this.shipForm.logistics_type)) return

      this.shipForm.workflow_loading = true
      fetchFbsWorkflow({ id: this.shipForm.id }).then(res => {
        this.applyFbsWorkflow(res.data || {})
      }).catch(err => {
        this.$message.error(err.message || '获取 FBS 工作流失败')
      }).finally(() => {
        this.shipForm.workflow_loading = false
      })
    },
    handleShip(order) {
      const isDbs = isDbsLogisticsType(order.logistics_type)
      this.shipForm = createDefaultShipForm({
        id: order.id,
        trade_order_id: order.ae_order_id,
        track_number: order.tracking_number || '',
        logistic_method: order.logistics_type || '',
        logistics_type: order.logistics_type || '',
        ship_provider: isDbs ? 'chinapost' : 'aliexpress',
        items: buildShipItemsFromOrder(order),
        logistic_order_id: order.logistic_order_id || null,
        handover_list_id: order.handover_list_id || null,
        handover_list_status: order.handover_list_status || '',
        workflow_loading: !isDbs
      })
      this.shipDialogVisible = true
      if (!isDbs) {
        this.refreshCurrentFbsWorkflow()
      }
    },
    submitFbsLogisticOrder() {
      const items = this.buildFbsItemsPayload()
      if (!items.length) {
        this.$message.warning('请至少填写一条有效的发货商品')
        return
      }

      this.shipping = true
      createFbsLogisticOrder({
        id: this.shipForm.id,
        total_length: this.shipForm.total_length,
        total_width: this.shipForm.total_width,
        total_height: this.shipForm.total_height,
        total_weight: this.shipForm.total_weight,
        undeliverable_option: this.shipForm.undeliverable_option,
        danger_type: this.shipForm.danger_type,
        items
      }).then(res => {
        this.$message.success(res.message || 'FBS 发货单创建成功')
        this.applyFbsWorkflow(res.data && res.data.workflow ? res.data.workflow : null)
        this.shipForm.current_step = 1
      }).catch(err => {
        this.$message.error(err.message || 'FBS 发货单创建失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    submitFbsHandoverList() {
      if (!this.shipForm.logistic_order_id) {
        this.$message.warning('请先创建发货单')
        return
      }

      this.shipping = true
      createFbsHandoverList({
        id: this.shipForm.id,
        logistic_order_ids: [this.shipForm.logistic_order_id],
        arrival_date: this.shipForm.arrival_date || ''
      }).then(res => {
        this.$message.success(res.message || '交接清单创建成功')
        this.applyFbsWorkflow(res.data && res.data.workflow ? res.data.workflow : null)
        this.shipForm.current_step = 2
      }).catch(err => {
        this.$message.error(err.message || '交接清单创建失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    addFbsToHandover() {
      if (!this.shipForm.logistic_order_id || !this.shipForm.existing_handover_list_id) {
        this.$message.warning('请填写已有交接单ID，并确保当前发货单已创建')
        return
      }

      this.shipping = true
      addFbsLogisticOrdersToHandover({
        id: this.shipForm.id,
        handover_list_id: this.shipForm.existing_handover_list_id,
        logistic_order_ids: [this.shipForm.logistic_order_id]
      }).then(res => {
        this.$message.success(res.message || '已追加到交接清单')
        this.applyFbsWorkflow(res.data && res.data.workflow ? res.data.workflow : null)
        this.shipForm.current_step = 2
      }).catch(err => {
        this.$message.error(err.message || '追加到交接清单失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    removeFbsFromHandover() {
      if (!this.shipForm.logistic_order_id || !this.shipForm.handover_list_id) {
        this.$message.warning('当前没有可移除的交接单关系')
        return
      }

      this.shipping = true
      removeFbsLogisticOrdersFromHandover({
        id: this.shipForm.id,
        handover_list_id: this.shipForm.handover_list_id,
        logistic_order_ids: [this.shipForm.logistic_order_id]
      }).then(res => {
        this.$message.success(res.message || '已从交接清单移除')
        this.applyFbsWorkflow(res.data && res.data.workflow ? res.data.workflow : null)
        this.shipForm.current_step = 1
      }).catch(err => {
        this.$message.error(err.message || '移除交接清单失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    printCurrentFbsLabel() {
      if (!this.shipForm.logistic_order_id) {
        this.$message.warning('请先创建发货单')
        return
      }

      getOrderLabel({ id: this.shipForm.id }).then(res => {
        const data = res.data || {}
        if (data.label_url) {
          window.open(data.label_url, '_blank')
          this.$message.success(res.message || '发货标签打印链接已打开')
          this.shipForm.current_step = 3
          return
        }
        this.$message.warning(res.message || '发货标签暂不可用')
      }).catch(err => {
        this.$message.error(err.message || '发货标签打印失败')
      })
    },
    printCurrentFbsHandoverLabel() {
      if (!this.shipForm.handover_list_id) {
        this.$message.warning('请先创建交接清单')
        return
      }

      printFbsHandoverList({
        id: this.shipForm.id,
        handover_list_id: this.shipForm.handover_list_id
      }).then(res => {
        const data = res.data || {}
        if (data.label_url) {
          window.open(data.label_url, '_blank')
          this.$message.success(res.message || '交接清单打印链接已打开')
          this.shipForm.current_step = 4
          return
        }
        this.$message.warning(res.message || '暂未获取到交接清单打印链接')
      }).catch(err => {
        this.$message.error(err.message || '交接清单打印失败')
      })
    },
    markCurrentFbsReadyForPickup() {
      if (!this.shipForm.handover_list_id) {
        this.$message.warning('请先创建交接清单')
        return
      }

      this.shipping = true
      readyFbsHandoverForPickup({
        id: this.shipForm.id,
        handover_list_id: this.shipForm.handover_list_id
      }).then(res => {
        const actionResult = (res.data && res.data.action_result) || {}
        this.shipForm.pickup_date = actionResult.pickup_date || this.shipForm.pickup_date
        this.shipForm.pickup_time_from = actionResult.pickup_time_from || this.shipForm.pickup_time_from
        this.shipForm.pickup_time_to = actionResult.pickup_time_to || this.shipForm.pickup_time_to
        this.applyFbsWorkflow(res.data && res.data.workflow ? res.data.workflow : null)
        this.$message.success(res.message || '已标记待揽收')
      }).catch(err => {
        this.$message.error(err.message || '标记待揽收失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    transferCurrentFbsHandoverList() {
      if (!this.shipForm.handover_list_id) {
        this.$message.warning('请先创建交接清单')
        return
      }

      this.shipping = true
      transferFbsHandoverList({
        id: this.shipForm.id,
        handover_list_id: this.shipForm.handover_list_id
      }).then(res => {
        this.applyFbsWorkflow(res.data && res.data.workflow ? res.data.workflow : null)
        const currentOrder = this.getCurrentShipOrder()
        if (currentOrder) {
          this.$set(currentOrder, 'actual_ship_at', new Date().toISOString())
        }
        this.$message.success(res.message || '交接单状态已更新')
      }).catch(err => {
        this.$message.error(err.message || '交接单状态更新失败')
      }).finally(() => {
        this.shipping = false
      })
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
          const target = this.list.find(order => order.id === this.shipForm.id)
          applyChinaPostCreateResult(target, waybillNo)
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
        const target = this.list.find(order => order.id === this.shipForm.id)
        applySz56tCreateResult(target, data)
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
      if (isDbsLogisticsType(this.shipForm.logistics_type) && !this.shipForm.track_number) {
        this.$message.warning('请输入运单号')
        return
      }

      this.shipping = true
      shipOrder({
        id: this.shipForm.id,
        track_number: this.shipForm.track_number,
        logistic_method: this.shipForm.logistic_method,
        ship_provider: this.shipForm.ship_provider,
        provider_name: this.shipForm.provider_name,
        total_length: this.shipForm.total_length,
        total_width: this.shipForm.total_width,
        total_height: this.shipForm.total_height,
        total_weight: this.shipForm.total_weight,
        undeliverable_option: this.shipForm.undeliverable_option,
        danger_type: this.shipForm.danger_type
      }).then(res => {
        this.$message.success(res.message || '发货成功')
        this.shipDialogVisible = false
        const target = this.list.find(order => order.id === this.shipForm.id)
        applyShipResult(target, res.data || {}, this.shipForm.track_number)
      }).catch(err => {
        this.$message.error(err.message || '发货失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    handleMarkShip(order) {
      this.$message.info('更新订单：' + order.ae_order_id)
    },
    copyText(text) {
      navigator.clipboard.writeText(text).then(() => {
        this.$message.success('已复制')
      })
    },
    toggleSelect(id) {
      const index = this.selectedOrders.indexOf(id)
      if (index > -1) {
        this.selectedOrders.splice(index, 1)
        return
      }
      this.selectedOrders.push(id)
    },
    toggleSelectAllCurrentPage(checked) {
      const pageIds = this.currentPageOrderIds
      if (checked) {
        this.selectedOrders = Array.from(new Set([...this.selectedOrders, ...pageIds]))
        return
      }
      this.selectedOrders = this.selectedOrders.filter(id => !pageIds.includes(id))
    }
  }
}
</script>

<style scoped>
.order-page {
  padding-bottom: 30px;
}
</style>
