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
      :estimated-receipt-rate="estimatedReceiptRate"
      :selected-orders="selectedOrders"
      :is-all-current-page-selected="isAllCurrentPageSelected"
      :is-current-page-indeterminate="isCurrentPageIndeterminate"
      :transfer-sheet-loading-id="transferSheetLoadingId"
      :dict-label-map="dictLabelMap"
      @toggle-select="toggleSelect"
      @toggle-select-all-current-page="toggleSelectAllCurrentPage"
      @copy-text="copyText"
      @print-label="handlePrintLabel"
      @transfer-sheet="handleTransferSheet"
      @ship="handleShip"
      @mark-ship="handleMarkShip"
      @mark-ready-for-pickup="handleMarkReadyForPickup"
      @mark-delivered="handleMarkDelivered"
      @cancel-waybill="handleCancelWaybill"
      @open-comment-dialog="openCommentDialog"
    />

    <pagination v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit" @pagination="getList" />

    <order-comment-dialog
      :visible.sync="commentDialogVisible"
      :comment-temp="commentTemp"
      :backend-status-options="backendStatusOptions"
      :upload-url="uploadUrl"
      :upload-headers="uploadHeaders"
      @save="submitComment"
    />

    <order-ship-dialog
      :visible.sync="shipDialogVisible"
      :title="shipDialogTitle"
      :ship-form="shipForm"
      :shipping="shipping"
      :dict-label-map="dictLabelMap"
      @submit-fbs-logistic-order="submitFbsLogisticOrder"
      @submit-fbs-handover-list="submitFbsHandoverList"
      @add-fbs-to-handover="addFbsToHandover"
      @remove-fbs-from-handover="removeFbsFromHandover"
      @print-fbs-label="printCurrentFbsLabel"
      @print-fbs-handover-label="printCurrentFbsHandoverLabel"
      @set-fbs-big-bag-count="setCurrentFbsHandoverBigBagCount"
      @mark-fbs-ready-for-pickup="markCurrentFbsReadyForPickup"
      @transfer-fbs-handover-list="transferCurrentFbsHandoverList"
      @refresh-fbs-workflow="refreshCurrentFbsWorkflow"
      @update-step="updateShipStep"
    />

    <dbs-provider-select-dialog
      :visible.sync="dbsProviderSelectVisible"
      :default-provider="dbsDefaultProvider"
      @select="handleDbsProviderSelect"
    />

    <china-post-ship-dialog
      :visible.sync="chinaPostDialogVisible"
      :ship-form="shipForm"
      :shipping="shipping"
      @load-chinapost-preview="ensureChinaPostPreview"
      @submit-ship="submitShip"
    />

    <leiyi-ship-dialog
      :visible.sync="leiyiDialogVisible"
      :ship-form="shipForm"
      :shipping="shipping"
      :can-cancel-sz56t-waybill="canCancelCurrentShipWaybill"
      :sz56t-product-options="sz56tProductOptions"
      :sz56t-product-loading="sz56tProductLoading"
      @cancel-waybill="cancelCurrentShipWaybill"
      @submit-ship="submitShip"
      @load-sz56t-products="ensureSz56tProductOptions"
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
  chinaPostPreviewOrder,
  createFbsHandoverList,
  createFbsLogisticOrder,
  chinaPostGetLabel,
  createOrderTransferSheet,
  exportOrders,
  fetchFbsWorkflow,
  fetchOrderBackendStatusCounts,
  fetchOrderList,
  fetchOrderStatusCounts,
  fetchSz56tProductList,
  fetchSyncProgress,
  getOrderLabel,
  printFbsHandoverList,
  readyFbsHandoverForPickup,
  setFbsHandoverBigBagCount,
  removeFbsLogisticOrdersFromHandover,
  shipFbsOrder,
  shipDbsChinaPostOrder,
  shipDbsLeiyiOrder,
  syncDbsShipmentDelivered,
  syncDbsShipmentReadyForPickup,
  syncDbsShipmentToPlatform,
  syncOrdersStart,
  sz56tCancelOrder,
  sz56tCreateOrder,
  sz56tGetLabel,
  transferFbsHandoverList,
  updateOrderBackendFields
} from '@/api/order'
import { fetchShopList } from '@/api/shop'
import { fetchConfigList, fetchDictBatch } from '@/api/system'
import Pagination from '@/components/Pagination'
import { getToken } from '@/utils/auth'
import OrderCommentDialog from './components/OrderCommentDialog'
import OrderFilterPanel from './components/OrderFilterPanel'
import OrderListSection from './components/OrderListSection'
import OrderShipDialog from './components/OrderShipDialog'
import ChinaPostShipDialog from './components/ChinaPostShipDialog'
import LeiyiShipDialog from './components/LeiyiShipDialog'
import DbsProviderSelectDialog from './components/DbsProviderSelectDialog'
import OrderSyncDialog from './components/OrderSyncDialog'
import OrderSyncProgressDialog from './components/OrderSyncProgressDialog'
import {
  ORDER_DICT_CODE,
  createDefaultCommentTemp,
  createDefaultListQuery,
  createDefaultShipForm,
  createDefaultSyncProgress,
  createDefaultChinaPostItem
} from './constants'
import {
  applyChinaPostCreateResult,
  applyCommentTempToOrder,
  applyPlatformSyncResult,
  applyShipResult,
  applySz56tCancelResult,
  applySz56tCreateResult,
  buildChinaPostReceiverFromOrder,
  buildChinaPostItemsFromOrder,
  buildSz56tFormFromOrder,
  buildSz56tItemsFromOrder,
  buildBackendStatusTabs,
  buildDictLabelMap,
  buildDictOptions,
  buildExportFilename,
  buildListQuery,
  buildShipItemsFromOrder,
  buildStatusTabs,
  buildSyncParams,
  canCancelSz56tWaybill,
  canMarkDbsDelivered,
  canMarkDbsInTransit,
  canMarkDbsReadyForPickup,
  createPdfObjectUrl,
  getFbsWorkflowStep,
  getBackendStatusLabel,
  resolvePrintLabelProviderCandidates,
  getSz56tProductIdFromOrder,
  getSz56tWeightFromOrder,
  isDbsLogisticsType
} from './utils'

const SZ56T_PRODUCT_CACHE_KEY = 'order:sz56t:product-list'
const SZ56T_PRODUCT_CACHE_TTL = 24 * 60 * 60 * 1000

export default {
  name: 'OrderManage',
  components: {
    ChinaPostShipDialog,
    DbsProviderSelectDialog,
    LeiyiShipDialog,
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
      dbsProviderSelectVisible: false,
      chinaPostDialogVisible: false,
      leiyiDialogVisible: false,
      dbsDefaultProvider: 'chinapost',
      shipping: false,
      transferSheetLoadingId: null,
      shipForm: createDefaultShipForm(),
      sz56tProductOptions: [],
      sz56tProductLoading: false,
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
      estimatedReceiptRate: 0.908,
      dictLabelMap: {},
      issueStatusOptions: []
    }
  },
  computed: {
    shipDialogTitle() {
      return 'FBS 发货流程'
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
    },
    canCancelCurrentShipWaybill() {
      const currentOrder = this.getCurrentShipOrder()
      if (canCancelSz56tWaybill(currentOrder)) {
        return true
      }

      return Boolean(
        isDbsLogisticsType(this.shipForm.logistics_type) &&
        this.shipForm.ship_provider === 'leiyi' &&
        String(this.shipForm.track_number || '').trim()
      )
    }
  },
  created() {
    this.loadShops()
    this.loadFinanceConfigs()
    this.loadOrderDicts()
    this.getStatusCounts()
    this.getBackendStatusCounts()
    this.getList()
  },
  beforeDestroy() {
    this.stopSyncPolling()
  },
  methods: {
    // 统一错误提示：拦截器已弹过的不重复弹
    showError(err, fallback) {
      if (err && err.alreadyNotified) return
      this.$message.error((err && err.message) || fallback || '操作失败')
    },
    loadShops() {
      fetchShopList({ page: 1, limit: 200 }).then(res => {
        this.shopOptions = res.data.items || []
      })
    },
    loadFinanceConfigs() {
      const bootstrap = this.$store && this.$store.getters ? (this.$store.getters.bootstrap || {}) : {}
      const bootstrapConfigs = Array.isArray(bootstrap.system_configs) ? bootstrap.system_configs : []
      if (bootstrapConfigs.length) {
        const rateConfig = bootstrapConfigs.find(item => item.key === 'estimated_receipt_rate')
        const parsedRate = Number(rateConfig ? rateConfig.value : '')
        if (!Number.isNaN(parsedRate) && parsedRate > 0) {
          this.estimatedReceiptRate = parsedRate
          return
        }
      }

      fetchConfigList({ group: 'finance' }).then(res => {
        const configs = res.data || []
        const rateConfig = configs.find(item => item.key === 'estimated_receipt_rate')
        const parsedRate = Number(rateConfig ? rateConfig.value : '')
        if (!Number.isNaN(parsedRate) && parsedRate > 0) {
          this.estimatedReceiptRate = parsedRate
        }
      }).catch(() => {})
    },
    async loadOrderDicts() {
      const targets = Object.values(ORDER_DICT_CODE)
      try {
        const res = await fetchDictBatch(targets)
        const rawMap = res.data || {}
        const map = {}
        const optionsByCode = {}
        targets.forEach(code => {
          const items = (rawMap[code] || []).filter(item => Number(item.status) === 1)
          map[code] = buildDictLabelMap(items)
          optionsByCode[code] = buildDictOptions(items)
        })
        this.dictLabelMap = map
        this.statusTabs = buildStatusTabs(optionsByCode[ORDER_DICT_CODE.orderDisplayStatus] || [])
        this.issueStatusOptions = optionsByCode[ORDER_DICT_CODE.issueStatus] || []
        this.backendStatusOptions = optionsByCode[ORDER_DICT_CODE.backendStatus] || []
        this.backendStatusTabs = buildBackendStatusTabs(this.backendStatusOptions)
      } catch {
        this.dictLabelMap = {}
      }
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
      this.stopSyncPolling()
      this.syncTaskId = null
      this.syncDialogVisible = false
      this.syncProgressDialogVisible = true
      this.syncProgress = createDefaultSyncProgress({
        status: 'running',
        progress: 0
      })

      syncOrdersStart(params).then(res => {
        const taskId = res && res.data ? res.data.task_id : null
        if (!taskId) {
          throw new Error('未返回同步任务ID')
        }

        this.syncTaskId = taskId
        this.startSyncPolling()
      }).catch(() => {
        this.stopSyncPolling()
        this.syncTaskId = null
        this.syncProgressDialogVisible = false
        this.syncDialogVisible = true
        this.syncProgress = createDefaultSyncProgress()
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
        purchase_amount: order.purchase_amount || 0,
        logistics_fee: order.logistics_fee || 0,
        weight: order.weight || null,
        logistics_template: order.logistics_template || '',
        apply_qianze_at: order.apply_qianze_at || '',
        ship_qianze_at: order.ship_qianze_at || ''
      })
      this.commentDialogVisible = true
    },
    submitComment() {
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
        this.showError(err, '导出失败')
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
    createPendingPrintWindow() {
      return window.open('', '_blank')
    },
    createPrintLabelLoading(text = '正在获取面单，请稍候...') {
      return this.$loading({
        lock: true,
        text,
        spinner: 'el-icon-loading',
        background: 'rgba(255, 255, 255, 0.75)'
      })
    },
    closePrintLabelLoading(loadingInstance) {
      if (loadingInstance && typeof loadingInstance.close === 'function') {
        loadingInstance.close()
      }
    },
    closePendingPrintWindow(printWindow) {
      if (printWindow && !printWindow.closed) {
        printWindow.close()
      }
    },
    createDocumentObjectUrl(documentUrl) {
      if (!documentUrl || !/^data:/i.test(documentUrl)) {
        return ''
      }

      const separatorIndex = documentUrl.indexOf(',')
      if (separatorIndex < 0) {
        return ''
      }

      const header = documentUrl.slice(0, separatorIndex)
      const rawPayload = documentUrl.slice(separatorIndex + 1)
      const mimeTypeMatch = header.match(/^data:([^;,]+)/i)
      const mimeType = mimeTypeMatch && mimeTypeMatch[1] ? mimeTypeMatch[1] : 'application/octet-stream'

      try {
        if (/;base64/i.test(header)) {
          const binary = atob(rawPayload)
          const bytes = new Uint8Array(binary.length)
          for (let index = 0; index < binary.length; index++) {
            bytes[index] = binary.charCodeAt(index)
          }
          return window.URL.createObjectURL(new Blob([bytes], { type: mimeType }))
        }

        return window.URL.createObjectURL(new Blob([decodeURIComponent(rawPayload)], { type: mimeType }))
      } catch (error) {
        return ''
      }
    },
    openDocumentUrl(documentUrl, printWindow) {
      if (!documentUrl) {
        this.closePendingPrintWindow(printWindow)
        return false
      }

      const objectUrl = this.createDocumentObjectUrl(documentUrl)
      const finalUrl = objectUrl || documentUrl
      const targetWindow = printWindow && !printWindow.closed ? printWindow : window.open('', '_blank')

      if (!targetWindow) {
        if (objectUrl) {
          setTimeout(() => window.URL.revokeObjectURL(objectUrl), 60000)
        }
        return false
      }

      targetWindow.location.replace(finalUrl)

      if (objectUrl) {
        setTimeout(() => window.URL.revokeObjectURL(objectUrl), 60000)
      } else if (/^blob:/i.test(finalUrl)) {
        setTimeout(() => window.URL.revokeObjectURL(finalUrl), 60000)
      }

      return true
    },
    handleTransferSheet(order) {
      const printWindow = this.createPendingPrintWindow()
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
          if (this.openDocumentUrl(data.label_url, printWindow)) {
            this.$message.success(res.message || '交接单已生成并打开')
            return
          }
          this.$message.warning('交接单已生成，但打印窗口被浏览器拦截，请允许弹窗后重试')
          return
        }
        this.closePendingPrintWindow(printWindow)
        this.$message.warning(res.message || '交接单已创建，但未返回可打印链接')
      }).catch(err => {
        this.closePendingPrintWindow(printWindow)
        this.showError(err, '交接单处理失败')
      }).finally(() => {
        this.transferSheetLoadingId = null
      })
    },
    async handlePrintLabel(order) {
      const labelProviders = resolvePrintLabelProviderCandidates(order)
      const currentLogistics = order.current_logistics || {}
      const currentPayload = currentLogistics.payload || {}
      const sz56tPayload = currentPayload.sz56t || {}
      const sz56tOrderId = currentLogistics.external_order_id || currentLogistics.externalOrderId || sz56tPayload.order_id || order.sz56t_order_id
      const platformLogisticOrderId = currentLogistics.platform_logistic_order_id || currentLogistics.platformLogisticOrderId || order.logistic_order_id
      const trackingNumber = order.tracking_number || currentLogistics.tracking_number || currentLogistics.trackingNumber || ''

      if (!labelProviders.length) {
        this.$message.warning('当前订单缺少可识别的承运商信息，无法判断应打印哪种面单')
        return
      }

      const loading = this.createPrintLabelLoading()

      try {
        if (labelProviders[0] === 'platform') {
          if (!platformLogisticOrderId) {
            this.$message.warning('当前订单缺少平台物流单号，暂时无法打印平台面单')
            return
          }

          const printWindow = this.createPendingPrintWindow()
          try {
            const res = await getOrderLabel({ id: order.id })
            const data = res.data || {}
            if (data.label_url) {
              if (this.openDocumentUrl(data.label_url, printWindow)) {
                return
              }
              this.$message.warning('面单已生成，但打印窗口被浏览器拦截，请允许弹窗后重试')
              return
            }
            this.closePendingPrintWindow(printWindow)
            this.$message.warning(res.message || '面单暂不可用')
          } catch (err) {
            this.closePendingPrintWindow(printWindow)
            this.showError(err, '面单获取失败')
          }
          return
        }

        const printWindow = this.createPendingPrintWindow()
        let lastErrorMessage = ''
        for (let index = 0; index < labelProviders.length; index++) {
          const provider = labelProviders[index]
          const result = await this.tryPrintCarrierLabel({
            order,
            provider,
            printWindow,
            sz56tOrderId,
            trackingNumber
          })

          if (result.ok) {
            if (index > 0) {
              this.$message.success(this.getPrintFallbackSuccessMessage(labelProviders[0], provider))
            }
            return
          }

          if (!lastErrorMessage && result.message) {
            lastErrorMessage = result.message
          }
        }

        this.closePendingPrintWindow(printWindow)
        this.$message.warning(lastErrorMessage || '面单暂不可用')
      } finally {
        this.closePrintLabelLoading(loading)
      }
    },
    getPrintProviderLabel(provider) {
      if (provider === 'sz56t') {
        return '雷翼'
      }

      if (provider === 'chinapost') {
        return '邮政'
      }

      if (provider === 'platform') {
        return '平台'
      }

      return '未知渠道'
    },
    getPrintFallbackSuccessMessage(primaryProvider, fallbackProvider) {
      return this.getPrintProviderLabel(primaryProvider) + '接口失败，已自动切换' + this.getPrintProviderLabel(fallbackProvider) + '接口打印'
    },
    async openSz56tLabel(orderId, printWindow) {
      try {
        const res = await sz56tGetLabel({ id: orderId })
        const data = res.data || {}
        if (data.pdf_base64) {
          const labelUrl = createPdfObjectUrl(data.pdf_base64)
          if (labelUrl && this.openDocumentUrl(labelUrl, printWindow)) {
            return { ok: true }
          }
          if (labelUrl) {
            return {
              ok: false,
              message: '面单已生成，但打印窗口被浏览器拦截，请允许弹窗后重试'
            }
          }
        }

        if (data.label_url) {
          if (this.openDocumentUrl(data.label_url, printWindow)) {
            return { ok: true }
          }
          return {
            ok: false,
            message: '面单已生成，但打印窗口被浏览器拦截，请允许弹窗后重试'
          }
        }

        return {
          ok: false,
          message: res.message || '雷翼面单暂不可用'
        }
      } catch (err) {
        return {
          ok: false,
          message: err.message || '雷翼面单获取失败'
        }
      }
    },
    async tryPrintCarrierLabel({ order, provider, printWindow, sz56tOrderId, trackingNumber }) {
      if (provider === 'sz56t') {
        return this.openSz56tLabel(order.id, printWindow)
      }

      if (provider === 'chinapost') {
        try {
          const res = await chinaPostGetLabel({ id: order.id })
          const data = res.data || {}
          const labelUrl = createPdfObjectUrl(data.pdf_base64)
          if (labelUrl && this.openDocumentUrl(labelUrl, printWindow)) {
            return { ok: true }
          }
          if (labelUrl) {
            return {
              ok: false,
              message: '面单已生成，但打印窗口被浏览器拦截，请允许弹窗后重试'
            }
          }

          return {
            ok: false,
            message: res.message || '邮政面单暂不可用'
          }
        } catch (err) {
          return {
            ok: false,
            message: err.message || '邮政面单获取失败'
          }
        }
      }

      return {
        ok: false,
        message: '当前订单缺少可识别的承运商信息，无法判断应打印哪种面单'
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
        this.showError(err, '获取 FBS 工作流失败')
      }).finally(() => {
        this.shipForm.workflow_loading = false
      })
    },
    handleShip(order) {
      const isDbs = isDbsLogisticsType(order.logistics_type)
      const defaultDbsProvider = order.sz56t_order_id || order.logistics_template === 'leiyi' ? 'leiyi' : 'chinapost'
      const defaultSz56tWeight = getSz56tWeightFromOrder(order, 100)
      this.shipForm = createDefaultShipForm({
        id: order.id,
        trade_order_id: order.ae_order_id,
        track_number: order.tracking_number || '',
        logistic_method: order.logistics_type || '',
        logistics_type: order.logistics_type || '',
        ship_provider: isDbs ? defaultDbsProvider : 'aliexpress',
        product_id: getSz56tProductIdFromOrder(order),
        weight: defaultSz56tWeight,
        sz56t_form: buildSz56tFormFromOrder(order, defaultSz56tWeight),
        sz56t_items: buildSz56tItemsFromOrder(order, defaultSz56tWeight),
        chinapost_receiver: buildChinaPostReceiverFromOrder(order),
        chinapost_items: buildChinaPostItemsFromOrder(order, defaultSz56tWeight),
        items: buildShipItemsFromOrder(order),
        logistic_order_id: order.logistic_order_id || null,
        handover_list_id: order.handover_list_id || null,
        handover_list_status: order.handover_list_status || '',
        big_bag_count: 1,
        workflow_loading: !isDbs
      })
      if (isDbs) {
        this.dbsDefaultProvider = defaultDbsProvider
        this.dbsProviderSelectVisible = true
      } else {
        this.shipDialogVisible = true
        this.refreshCurrentFbsWorkflow()
      }
    },
    handleDbsProviderSelect(provider) {
      this.shipForm.ship_provider = provider
      if (provider === 'chinapost') {
        this.chinaPostDialogVisible = true
        this.ensureChinaPostPreview()
      } else {
        this.leiyiDialogVisible = true
        this.ensureSz56tProductOptions()
      }
    },
    ensureChinaPostPreview(force = false) {
      if (!this.shipForm.id || this.shipForm.ship_provider !== 'chinapost') {
        return Promise.resolve()
      }

      if (!force && String(this.shipForm.chinapost_request_json || '').trim()) {
        return Promise.resolve()
      }

      return chinaPostPreviewOrder({ id: this.shipForm.id }).then(res => {
        const requestData = (res && res.data) || {}
        const logisticsInterface = requestData.logistics_interface || {}

        this.shipForm.biz_product_no = '019'
        this.shipForm.chinapost_request_json = JSON.stringify(requestData, null, 2)

        if (Number(logisticsInterface.weight || 0) > 0) {
          this.shipForm.weight = Number(logisticsInterface.weight || 0)
        }

        if (requestData.senderNo || requestData.sender_no) {
          this.shipForm.sender_no = requestData.senderNo || requestData.sender_no
        }
        if (logisticsInterface.mailType) {
          this.shipForm.chinapost_form.mailType = logisticsInterface.mailType
        }
        if (logisticsInterface.wh_code) {
          this.shipForm.chinapost_form.wh_code = logisticsInterface.wh_code
        }
        if (!this.shipForm.chinapost_form.created_time) {
          this.shipForm.chinapost_form.created_time = logisticsInterface.created_time || new Date().toISOString().replace('T', ' ').slice(0, 19)
        }
        if (logisticsInterface.logistics_order_no && !this.shipForm.chinapost_form.logistics_order_no) {
          this.shipForm.chinapost_form.logistics_order_no = logisticsInterface.logistics_order_no
        }
        if (logisticsInterface.barcode && !this.shipForm.chinapost_form.barcode) {
          this.shipForm.chinapost_form.barcode = logisticsInterface.barcode
        }

        const receiverData = logisticsInterface.receiver || {}
        if (!this.shipForm.chinapost_receiver) {
          this.$set(this.shipForm, 'chinapost_receiver', {})
        }
        const receiverFields = ['name', 'company', 'post_code', 'phone', 'mobile', 'email', 'province', 'city', 'county', 'address', 'linker']
        receiverFields.forEach(key => {
          if (receiverData[key] && !this.shipForm.chinapost_receiver[key]) {
            this.$set(this.shipForm.chinapost_receiver, key, receiverData[key])
          }
        })
        this.$set(this.shipForm.chinapost_receiver, 'nation', 'RU')

        const senderData = logisticsInterface.sender || {}
        if (!this.shipForm.chinapost_sender) {
          this.$set(this.shipForm, 'chinapost_sender', {})
        }
        const senderFields = ['name', 'company', 'post_code', 'phone', 'mobile', 'email', 'nation', 'province', 'city', 'county', 'address', 'linker']
        senderFields.forEach(key => {
          if (senderData[key] && !this.shipForm.chinapost_sender[key]) {
            this.$set(this.shipForm.chinapost_sender, key, senderData[key])
          }
        })

        const backendItems = Array.isArray(logisticsInterface.items) ? logisticsInterface.items : []
        if (backendItems.length) {
          this.$set(this.shipForm, 'chinapost_items', backendItems.map(item => this.normalizeChinaPostItemForSubmit(item, { forceRandomDeclaredValue: true })))
        }
      }).catch(err => {
        this.showError(err, '获取中国邮政请求参数失败')
      })
    },
    normalizeChinaPostItemForSubmit(rawItem = {}, options = {}) {
      const item = createDefaultChinaPostItem(rawItem && typeof rawItem === 'object' ? rawItem : {})
      const forceRandomDeclaredValue = Boolean(options.forceRandomDeclaredValue)
      const cargoName = String(item.cargo_name || '').trim()
      const cargoNameEn = String(item.cargo_name_en || '').trim()
      const cargoTypeName = String(item.cargo_type_name || '').trim()
      const cargoTypeNameEn = String(item.cargo_type_name_en || '').trim()

      const resolvedCargoName = cargoName || cargoTypeName || cargoNameEn || '商品'
      const resolvedCargoNameEn = cargoNameEn || cargoTypeNameEn || cargoName || 'Product'
      const declaredRaw = String(item.cargo_value || item.cost || '').trim()
      const declaredNumeric = Number(declaredRaw)
      const resolvedDeclaredValue = (forceRandomDeclaredValue || declaredNumeric <= 0)
        ? (Math.random() * 9 + 1).toFixed(2)
        : declaredRaw

      item.cargo_name = resolvedCargoName
      item.cargo_name_en = resolvedCargoNameEn
      item.cargo_type_name = cargoTypeName || resolvedCargoName
      item.cargo_type_name_en = cargoTypeNameEn || resolvedCargoNameEn
      item.cost = resolvedDeclaredValue
      item.cargo_value = resolvedDeclaredValue

      if (!String(item.cargo_description || '').trim()) {
        item.cargo_description = resolvedCargoNameEn || resolvedCargoName
      }

      item.cargo_quantity = Number(item.cargo_quantity || 0) > 0 ? Number(item.cargo_quantity) : 1
      const resolvedWeight = Number(item.cargo_weight || item.carogo_weight || 0) > 0
        ? Number(item.cargo_weight || item.carogo_weight || 0)
        : 1
      item.cargo_weight = resolvedWeight
      item.carogo_weight = resolvedWeight
      item.cargo_origin_name = String(item.cargo_origin_name || 'CN').trim() || 'CN'
      item.unit = String(item.unit || '个').trim() || '个'

      return item
    },
    parseChinaPostRequest() {
      const raw = String(this.shipForm.chinapost_request_json || '').trim()
      if (!raw) {
        this.$message.warning('请先生成中国邮政请求参数')
        return null
      }

      let parsed
      try {
        parsed = JSON.parse(raw)
      } catch (error) {
        this.$message.warning('中国邮政请求参数不是合法 JSON')
        return null
      }

      const logisticsInterface = parsed && typeof parsed === 'object'
        ? (parsed.logistics_interface || parsed.logisticsInterface || parsed)
        : null

      if (!logisticsInterface || Array.isArray(logisticsInterface) || typeof logisticsInterface !== 'object') {
        this.$message.warning('中国邮政请求参数缺少 logistics_interface 对象')
        return null
      }

      logisticsInterface.biz_product_no = '019'

      if (this.shipForm.chinapost_sender && typeof this.shipForm.chinapost_sender === 'object') {
        logisticsInterface.sender = { ...logisticsInterface.sender, ...this.shipForm.chinapost_sender }
      }
      this.sanitizeChinaPostContact(logisticsInterface.sender)

      if (this.shipForm.chinapost_receiver && typeof this.shipForm.chinapost_receiver === 'object') {
        logisticsInterface.receiver = { ...logisticsInterface.receiver, ...this.shipForm.chinapost_receiver }
      }
      this.sanitizeChinaPostContact(logisticsInterface.receiver)
      if (Array.isArray(this.shipForm.chinapost_items) && this.shipForm.chinapost_items.length) {
        logisticsInterface.items = this.shipForm.chinapost_items.map(item => this.normalizeChinaPostItemForSubmit(item))
      }
      if (this.shipForm.chinapost_form && typeof this.shipForm.chinapost_form === 'object') {
        const formFields = this.shipForm.chinapost_form
        if (formFields.barcode) logisticsInterface.barcode = formFields.barcode
        if (formFields.mailType) logisticsInterface.mailType = formFields.mailType
        if (formFields.volume) logisticsInterface.volume = formFields.volume
        if (formFields.length > 0) logisticsInterface.length = formFields.length
        if (formFields.width > 0) logisticsInterface.width = formFields.width
        if (formFields.height > 0) logisticsInterface.height = formFields.height
      }
      logisticsInterface.weight = Number(this.shipForm.weight || logisticsInterface.weight || 0)

      const payload = {
        api_code: parsed.apiCode || parsed.api_code || '110001',
        sender_no: parsed.senderNo || parsed.sender_no || logisticsInterface.sender_no || '',
        msg_type: parsed.msgType || parsed.msg_type || '0',
        version: parsed.version || 'V1.0.0',
        user_code: parsed.userCode || parsed.user_code || '',
        product_type: 'E邮宝',
        biz_product_no: '019',
        weight: Number(logisticsInterface.weight || this.shipForm.weight || 0),
        logistics_interface: logisticsInterface
      }

      if (!(payload.weight > 0)) {
        this.$message.warning('中国邮政请求参数中的 weight 必须大于 0')
        return null
      }

      return payload
    },
    sanitizeChinaPostContact(contact) {
      if (!contact || typeof contact !== 'object') return
      if (!contact.linker && contact.name) {
        contact.linker = contact.name
      }
      if (contact.mobile) {
        contact.mobile = String(contact.mobile).replace(/^\+/, '')
      }
      if (contact.phone) {
        contact.phone = String(contact.phone).replace(/^\+/, '')
      }
    },
    getSz56tProductCache() {
      try {
        const raw = window.localStorage.getItem(SZ56T_PRODUCT_CACHE_KEY)
        if (!raw) return null

        const parsed = JSON.parse(raw)
        if (!parsed || !Array.isArray(parsed.items) || !parsed.expiresAt) {
          window.localStorage.removeItem(SZ56T_PRODUCT_CACHE_KEY)
          return null
        }

        if (parsed.expiresAt <= Date.now()) {
          window.localStorage.removeItem(SZ56T_PRODUCT_CACHE_KEY)
          return null
        }

        return parsed.items
      } catch (error) {
        window.localStorage.removeItem(SZ56T_PRODUCT_CACHE_KEY)
        return null
      }
    },
    setSz56tProductCache(items) {
      try {
        window.localStorage.setItem(SZ56T_PRODUCT_CACHE_KEY, JSON.stringify({
          items,
          expiresAt: Date.now() + SZ56T_PRODUCT_CACHE_TTL
        }))
      } catch (error) {
        // ignore localStorage quota or private mode failures
      }
    },
    ensureSz56tProductOptions(force = false) {
      if (this.sz56tProductLoading) {
        return
      }

      if (!force && this.sz56tProductOptions.length) {
        return
      }

      if (!force) {
        const cachedItems = this.getSz56tProductCache()
        if (cachedItems && cachedItems.length) {
          this.sz56tProductOptions = cachedItems
          return
        }
      }

      this.sz56tProductLoading = true
      fetchSz56tProductList({ refresh: force }).then(res => {
        const items = (res.data && res.data.items) || []
        this.sz56tProductOptions = items
        if (items.length) {
          this.setSz56tProductCache(items)
        }
      }).catch(err => {
        this.showError(err, '获取雷翼运输方式失败')
      }).finally(() => {
        this.sz56tProductLoading = false
      })
    },
    buildSz56tPayload() {
      const form = this.shipForm.sz56t_form || {}
      const volumeRows = Array.isArray(form.orderVolumeParam)
        ? form.orderVolumeParam
          .map(item => ({
            volume_length: item.volume_length === null || item.volume_length === undefined || item.volume_length === '' ? null : Number(item.volume_length),
            volume_width: item.volume_width === null || item.volume_width === undefined || item.volume_width === '' ? null : Number(item.volume_width),
            volume_height: item.volume_height === null || item.volume_height === undefined || item.volume_height === '' ? null : Number(item.volume_height),
            volume_weight: item.volume_weight === null || item.volume_weight === undefined || item.volume_weight === '' ? null : Number(item.volume_weight)
          }))
          .filter(item => item.volume_length || item.volume_width || item.volume_height || item.volume_weight)
        : []
      const firstVolume = volumeRows[0] || {}

      return {
        product_id: this.shipForm.product_id,
        weight: Number(this.shipForm.weight || 0),
        sz56t_form: {
          ...form,
          consignee_address: String(form.consignee_address || '').slice(0, 500),
          order_piece: Number(form.order_piece || 1),
          length: firstVolume.volume_length || null,
          width: firstVolume.volume_width || null,
          height: firstVolume.volume_height || null,
          orderVolumeParam: volumeRows,
          weight: Number(this.shipForm.weight || 0)
        },
        sz56t_items: (this.shipForm.sz56t_items || []).map(item => ({
          sku: item.sku,
          invoice_title: item.invoice_title,
          invoice_amount: Number(item.invoice_amount || 0),
          invoice_pcs: Number(item.invoice_pcs || 0),
          invoice_weight: Number(item.invoice_weight || 0),
          sku_code: item.sku_code,
          hs_code: item.hs_code,
          transaction_url: item.transaction_url,
          invoice_currency: item.invoice_currency || 'USD',
          invoiceunit_code: item.invoiceunit_code || 'PCS',
          origin_country: item.origin_country || 'CN',
          invoice_brand: item.invoice_brand,
          invoice_rule: item.invoice_rule,
          invoice_taxno: item.invoice_taxno,
          invoice_material: item.invoice_material,
          invoice_purpose: item.invoice_purpose,
          invoice_export_unitprice: item.invoice_export_unitprice === null || item.invoice_export_unitprice === undefined || item.invoice_export_unitprice === ''
            ? null
            : Number(item.invoice_export_unitprice),
          invoice_export_currency: item.invoice_export_currency || item.invoice_currency || 'USD',
          invoice_production_sales_suppliers_name: item.invoice_production_sales_suppliers_name,
          invoice_production_sales_suppliers_credit_code: item.invoice_production_sales_suppliers_credit_code,
          import_hs_code: item.import_hs_code,
          invoice_imgurl: item.invoice_imgurl
        }))
      }
    },
    validateSz56tPayload() {
      if (!this.shipForm.product_id) {
        return '请选择雷翼运输方式'
      }

      if (!(Number(this.shipForm.weight || 0) > 0)) {
        return '请填写总重量'
      }

      const form = this.shipForm.sz56t_form || {}
      const requiredFields = [
        ['consignee_name', '请填写收件人'],
        ['country', '请选择目的地'],
        ['consignee_state', '请填写州/省'],
        ['consignee_city', '请填写城市'],
        ['consignee_postcode', '请填写邮编'],
        ['consignee_address', '请填写详细地址']
      ]

      for (let index = 0; index < requiredFields.length; index++) {
        const field = requiredFields[index][0]
        const message = requiredFields[index][1]
        if (!String(form[field] || '').trim()) {
          return message
        }
      }

      if (!String(form.consignee_telephone || '').trim() && !String(form.consignee_mobile || '').trim()) {
        return '联系电话和手机号至少填写一个'
      }

      const volumeRows = Array.isArray(form.orderVolumeParam) ? form.orderVolumeParam : []
      for (let index = 0; index < volumeRows.length; index++) {
        const item = volumeRows[index] || {}
        const rowNumber = index + 1
        const hasAnyValue = item.volume_length || item.volume_width || item.volume_height || item.volume_weight
        if (!hasAnyValue) {
          continue
        }

        if (!(Number(item.volume_length || 0) > 0)) {
          return `第${rowNumber}条材积信息缺少长度`
        }
        if (!(Number(item.volume_width || 0) > 0)) {
          return `第${rowNumber}条材积信息缺少宽度`
        }
        if (!(Number(item.volume_height || 0) > 0)) {
          return `第${rowNumber}条材积信息缺少高度`
        }
        if (!(Number(item.volume_weight || 0) > 0)) {
          return `第${rowNumber}条材积信息缺少实重`
        }
      }

      const items = this.shipForm.sz56t_items || []
      if (!items.length) {
        return '请至少填写一条申报信息'
      }

      for (let index = 0; index < items.length; index++) {
        const item = items[index] || {}
        const rowNumber = index + 1
        if (!String(item.invoice_title || '').trim()) {
          return `第${rowNumber}条申报信息缺少申报品名`
        }
        if (!(Number(item.invoice_amount || 0) > 0)) {
          return `第${rowNumber}条申报信息的申报总金额必须大于0`
        }
        if (!(Number(item.invoice_pcs || 0) > 0)) {
          return `第${rowNumber}条申报信息的数量必须大于0`
        }
        if (!(Number(item.invoice_weight || 0) > 0)) {
          return `第${rowNumber}条申报信息的单件重量必须大于0`
        }
        if (!String(item.origin_country || 'CN').trim()) {
          return `第${rowNumber}条申报信息缺少原产国`
        }
      }

      return ''
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
        this.showError(err, 'FBS 发货单创建失败')
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
        this.showError(err, '交接清单创建失败')
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
        this.showError(err, '追加到交接清单失败')
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
        this.showError(err, '移除交接清单失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    async printCurrentFbsLabel() {
      if (!this.shipForm.logistic_order_id) {
        this.$message.warning('请先创建发货单')
        return
      }

      const loading = this.createPrintLabelLoading('正在生成发货面单，请稍候...')
      const printWindow = this.createPendingPrintWindow()
      try {
        const res = await getOrderLabel({ id: this.shipForm.id })
        const data = res.data || {}
        if (data.label_url) {
          if (this.openDocumentUrl(data.label_url, printWindow)) {
            this.$message.success(res.message || '发货标签打印链接已打开')
            this.shipForm.current_step = 3
            return
          }
          this.$message.warning('发货标签已生成，但打印窗口被浏览器拦截，请允许弹窗后重试')
          return
        }
        this.closePendingPrintWindow(printWindow)
        this.$message.warning(res.message || '发货标签暂不可用')
      } catch (err) {
        this.closePendingPrintWindow(printWindow)
        this.showError(err, '发货标签打印失败')
      } finally {
        this.closePrintLabelLoading(loading)
      }
    },
    printCurrentFbsHandoverLabel() {
      if (!this.shipForm.handover_list_id) {
        this.$message.warning('请先创建交接清单')
        return
      }

      const printWindow = this.createPendingPrintWindow()
      printFbsHandoverList({
        id: this.shipForm.id,
        handover_list_id: this.shipForm.handover_list_id
      }).then(res => {
        const data = res.data || {}
        if (data.label_url) {
          if (this.openDocumentUrl(data.label_url, printWindow)) {
            this.$message.success(res.message || '交接清单打印链接已打开')
            this.shipForm.current_step = 4
            return
          }
          this.$message.warning('交接清单已生成，但打印窗口被浏览器拦截，请允许弹窗后重试')
          return
        }
        this.closePendingPrintWindow(printWindow)
        this.$message.warning(res.message || '暂未获取到交接清单打印链接')
      }).catch(err => {
        this.closePendingPrintWindow(printWindow)
        this.showError(err, '交接清单打印失败')
      })
    },
    setCurrentFbsHandoverBigBagCount() {
      if (!this.shipForm.handover_list_id) {
        this.$message.warning('请先创建交接清单')
        return
      }
      const count = parseInt(this.shipForm.big_bag_count, 10)
      if (!count || count <= 0) {
        this.$message.warning('请输入有效的大袋数量')
        return
      }

      this.shipping = true
      setFbsHandoverBigBagCount({
        id: this.shipForm.id,
        handover_list_id: this.shipForm.handover_list_id,
        big_bag_count: count
      }).then(res => {
        this.$message.success(res.message || '大袋数量已设置')
      }).catch(err => {
        this.showError(err, '设置大袋数量失败')
      }).finally(() => {
        this.shipping = false
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
        this.showError(err, '标记待揽收失败')
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
        this.showError(err, '交接单状态更新失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    submitChinaPostCreate() {
      const chinaPostPayload = this.parseChinaPostRequest()
      if (!chinaPostPayload) {
        return
      }

      this.shipping = true
      chinaPostCreateOrder({
        id: this.shipForm.id,
        ...chinaPostPayload
      }).then(res => {
        const waybillNo = res.data && res.data.waybill_no
        this.$message.success(res.message || '邮政下单成功')
        this.chinaPostDialogVisible = false
        if (waybillNo) {
          this.shipForm.track_number = waybillNo
          const target = this.list.find(order => order.id === this.shipForm.id)
          applyChinaPostCreateResult(target, waybillNo)
        }
      }).catch(err => {
        this.showError(err, '邮政下单失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    submitSz56tCreate() {
      const validationMessage = this.validateSz56tPayload()
      if (validationMessage) {
        this.$message.warning(validationMessage)
        return
      }

      this.shipping = true
      sz56tCreateOrder({
        id: this.shipForm.id,
        ...this.buildSz56tPayload()
      }).then(async res => {
        const data = res.data || {}
        this.$message.success(res.message || '雷翼下单成功')
        if (data.tracking_number) {
          this.shipForm.track_number = data.tracking_number
        }
        const target = this.list.find(order => order.id === this.shipForm.id)
        applySz56tCreateResult(target, data)
        if (data.order_id || data.tracking_number || (target && target.sz56t_order_id)) {
          const labelResult = await this.openSz56tLabel(this.shipForm.id)
          if (!labelResult.ok) {
            console.warn('雷翼面单暂不可用:', labelResult.message)
          }
        }
        if (data.is_delay) {
          this.$message.info('单号延迟获取，稍后可点击"获取跟踪号"')
        }
      }).catch(err => {
        this.showError(err, '雷翼下单失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    submitShip() {
      const order = this.getCurrentShipOrder()
      const isDbs = order && String(order.logistics_type || '').toUpperCase() === 'DBS'

      if (isDbs) {
        const shipProvider = ['chinapost', 'leiyi'].includes(this.shipForm.ship_provider)
          ? this.shipForm.ship_provider
          : 'chinapost'

        if (shipProvider !== this.shipForm.ship_provider) {
          this.shipForm.ship_provider = shipProvider
        }

        if (shipProvider === 'leiyi') {
          if (this.canCancelCurrentShipWaybill) {
            this.$message.info('当前雷翼订单已创建，可直接取消订单或关闭弹窗')
            return
          }
          const validationMessage = this.validateSz56tPayload()
          if (validationMessage) {
            this.$message.warning(validationMessage)
            return
          }
          this.shipping = true
          const payload = {
            id: this.shipForm.id,
            track_number: this.shipForm.track_number,
            ...this.buildSz56tPayload()
          }
          shipDbsLeiyiOrder(payload).then(async res => {
            this.$message.success(res.message || 'DBS 雷翼发货已记录')
            this.leiyiDialogVisible = false
            const target = this.list.find(o => o.id === this.shipForm.id)
            const resultData = res.data || {}
            applyShipResult(target, resultData, this.shipForm.track_number, 'leiyi')
            if (resultData.tracking_number) {
              this.shipForm.track_number = resultData.tracking_number
            }
            const providerResult = resultData.provider_result || {}
            if (providerResult.order_id || resultData.tracking_number || (target && target.sz56t_order_id)) {
              const labelResult = await this.openSz56tLabel(this.shipForm.id)
              if (!labelResult.ok) {
                console.warn('雷翼面单暂不可用:', labelResult.message)
              }
            }
          }).catch(err => {
            this.showError(err, 'DBS 雷翼发货失败')
          }).finally(() => {
            this.shipping = false
          })
          return
        }

        // DBS ChinaPost
        const requiresChinaPostCreate = !String(this.shipForm.track_number || '').trim()
        const chinaPostPayload = requiresChinaPostCreate ? this.parseChinaPostRequest() : null
        if (requiresChinaPostCreate && !chinaPostPayload) {
          return
        }
        this.shipping = true
        const payload = {
          id: this.shipForm.id,
          track_number: this.shipForm.track_number,
          biz_product_no: this.shipForm.biz_product_no,
          weight: this.shipForm.weight
        }
        if (chinaPostPayload) {
          Object.assign(payload, chinaPostPayload)
        }
        shipDbsChinaPostOrder(payload).then(res => {
          this.$message.success(res.message || 'DBS 邮政发货已记录')
          this.chinaPostDialogVisible = false
          const target = this.list.find(o => o.id === this.shipForm.id)
          const resultData = res.data || {}
          applyShipResult(target, resultData, this.shipForm.track_number, 'chinapost')
          if (resultData.tracking_number) {
            this.shipForm.track_number = resultData.tracking_number
          }
        }).catch(err => {
          this.showError(err, 'DBS 邮政发货失败')
        }).finally(() => {
          this.shipping = false
        })
        return
      }

      // FBS
      this.shipping = true
      const payload = {
        id: this.shipForm.id,
        track_number: this.shipForm.track_number,
        logistic_method: this.shipForm.logistic_method,
        total_length: this.shipForm.total_length,
        total_width: this.shipForm.total_width,
        total_height: this.shipForm.total_height,
        total_weight: this.shipForm.total_weight,
        undeliverable_option: this.shipForm.undeliverable_option,
        danger_type: this.shipForm.danger_type
      }
      shipFbsOrder(payload).then(async res => {
        this.$message.success(res.message || '发货成功')
        const target = this.list.find(o => o.id === this.shipForm.id)
        const resultData = res.data || {}
        applyShipResult(target, resultData, this.shipForm.track_number, 'fbs')
        if (resultData.tracking_number) {
          this.shipForm.track_number = resultData.tracking_number
        }
        this.shipDialogVisible = false
      }).catch(err => {
        this.showError(err, '发货失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    submitDbsPlatformStatus(order, options = {}) {
      const {
        available,
        invalidMessage,
        confirmMessage,
        confirmTitle,
        request,
        successMessage,
        errorMessage
      } = options

      if (typeof available === 'function' && !available(order)) {
        this.$message.info(invalidMessage || '当前订单暂无可同步的平台状态')
        return
      }

      this.$confirm(confirmMessage, confirmTitle, { type: 'warning' })
        .then(() => {
          this.shipping = true
          return request({ id: order.id })
        })
        .then(res => {
          this.$message.success(res.message || successMessage || '平台状态同步成功')
          applyPlatformSyncResult(order, (res && res.data) || {})
        })
        .catch(err => {
          if (err === 'cancel' || err === 'close') {
            return
          }
          this.showError(err, errorMessage || '平台状态同步失败')
        })
        .finally(() => {
          this.shipping = false
        })
    },
    handleMarkShip(order) {
      this.submitDbsPlatformStatus(order, {
        available: canMarkDbsInTransit,
        invalidMessage: '当前订单暂无可同步的已发货状态',
        confirmMessage: `确定将订单 ${order.ae_order_id} 同步为速卖通已发货吗？`,
        confirmTitle: '同步已发货',
        request: syncDbsShipmentToPlatform,
        successMessage: '已同步已发货状态',
        errorMessage: '同步已发货状态失败'
      })
    },
    handleMarkReadyForPickup(order) {
      this.submitDbsPlatformStatus(order, {
        available: canMarkDbsReadyForPickup,
        invalidMessage: '当前订单暂无可同步的准备取货状态',
        confirmMessage: `确定将订单 ${order.ae_order_id} 同步为速卖通准备取货吗？`,
        confirmTitle: '同步准备取货',
        request: syncDbsShipmentReadyForPickup,
        successMessage: '已同步准备取货状态',
        errorMessage: '同步准备取货状态失败'
      })
    },
    handleMarkDelivered(order) {
      this.submitDbsPlatformStatus(order, {
        available: canMarkDbsDelivered,
        invalidMessage: '当前订单暂无可同步的已交付状态',
        confirmMessage: `确定将订单 ${order.ae_order_id} 同步为速卖通已交付吗？`,
        confirmTitle: '同步已交付',
        request: syncDbsShipmentDelivered,
        successMessage: '已同步已交付状态',
        errorMessage: '同步已交付状态失败'
      })
    },
    handleCancelWaybill(order) {
      if (!canCancelSz56tWaybill(order)) {
        this.$message.info('当前订单暂无可取消的雷翼运单')
        return
      }

      this.$confirm(`确定取消订单 ${order.ae_order_id} 的雷翼运单吗？`, '取消运单', { type: 'warning' })
        .then(() => {
          this.shipping = true
          return sz56tCancelOrder({ id: order.id })
        })
        .then(res => {
          this.$message.success(res.message || '雷翼运单已取消')
          applySz56tCancelResult(order)

          if (this.shipForm.id === order.id) {
            this.shipForm.track_number = ''
          }
        })
        .catch(err => {
          if (err === 'cancel' || err === 'close') {
            return
          }

          this.$message.error((err && err.message) || '取消雷翼运单失败')
        })
        .finally(() => {
          this.shipping = false
        })
    },
    cancelCurrentShipWaybill() {
      const currentOrder = this.getCurrentShipOrder()
      if (currentOrder && canCancelSz56tWaybill(currentOrder)) {
        this.handleCancelWaybill(currentOrder)
        return
      }

      if (!this.shipForm.id || this.shipForm.ship_provider !== 'leiyi' || !String(this.shipForm.track_number || '').trim()) {
        this.$message.warning('当前订单不存在，无法取消雷翼订单')
        return
      }

      this.handleCancelWaybill({
        id: this.shipForm.id,
        ae_order_id: this.shipForm.trade_order_id,
        logistics_type: this.shipForm.logistics_type,
        logistics_template: 'leiyi',
        tracking_number: this.shipForm.track_number,
        sz56t_order_id: currentOrder && currentOrder.sz56t_order_id ? currentOrder.sz56t_order_id : ''
      })
    },
    copyText(text) {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
          this.$message.success('已复制')
        })
      } else {
        const textarea = document.createElement('textarea')
        textarea.value = text
        textarea.style.position = 'fixed'
        textarea.style.opacity = '0'
        document.body.appendChild(textarea)
        textarea.select()
        document.execCommand('copy')
        document.body.removeChild(textarea)
        this.$message.success('已复制')
      }
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
