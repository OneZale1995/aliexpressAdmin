<template>
  <el-dialog :title="title" :visible.sync="dialogVisible" :width="dialogWidth" top="5vh">
    <div v-if="isFbs" v-loading="shipForm.workflow_loading" class="fbs-workflow">
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
                <template slot-scope="{ row }">
                  <el-input v-model.number="row.sku_id" size="mini" />
                </template>
              </el-table-column>
              <el-table-column label="来源ID" min-width="140">
                <template slot-scope="{ row }">
                  <el-input v-model.number="row.product_source_id" size="mini" />
                </template>
              </el-table-column>
              <el-table-column label="数量" width="120">
                <template slot-scope="{ row }">
                  <el-input-number v-model="row.quantity" :min="1" :step="1" size="mini" />
                </template>
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

    <el-form
      v-else
      :label-width="shipForm.ship_provider === 'leiyi' ? '108px' : '100px'"
      :size="shipForm.ship_provider === 'leiyi' ? 'small' : 'medium'"
      :class="{ 'sz56t-form': shipForm.ship_provider === 'leiyi' }"
    >
      <el-alert
        title="DBS 这里只记录本地实际发货。完成后请回订单列表点击“发送到速卖通”。"
        type="info"
        :closable="false"
        show-icon
      />
      <el-form-item label="物流类型">
        <el-tag :type="shipForm.logistics_type === 'DBS' ? 'warning' : 'success'" size="small">{{ shipForm.logistics_type || '-' }}</el-tag>
      </el-form-item>
      <el-form-item v-if="shipForm.logistics_type === 'DBS'" label="发货渠道" required>
        <el-radio-group v-model="shipForm.ship_provider" @change="handleShipProviderChange">
          <el-radio label="chinapost">中国邮政(E邮宝)</el-radio>
          <el-radio label="leiyi">雷翼(sz56t)</el-radio>
        </el-radio-group>
      </el-form-item>

      <template v-if="shipForm.ship_provider === 'chinapost'">
        <el-alert
          title="中国邮政固定按 E邮宝 下单，biz_product_no=019。下面的 JSON 会完整列出实际发送参数，默认已按订单和系统配置带入。"
          type="info"
          :closable="false"
          show-icon
        />
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="业务类型代码">
              <el-input value="019" disabled />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="业务类型">
              <el-input value="E邮宝" disabled />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="请求参数">
          <div class="chinapost-toolbar">
            <span class="chinapost-toolbar__tip">包含外层公共参数和 logistics_interface，提交时按当前 JSON 发送。</span>
            <el-button size="mini" plain @click="$emit('load-chinapost-preview', true)">按订单重置默认值</el-button>
          </div>
          <el-input
            v-model="shipForm.chinapost_request_json"
            type="textarea"
            :rows="20"
            placeholder="点击“按订单重置默认值”生成中国邮政请求参数"
            class="chinapost-json-editor"
          />
        </el-form-item>
      </template>

      <template v-if="shipForm.ship_provider === 'leiyi'">
        <div class="sz56t-form-mode-switch">
          <span class="sz56t-form-mode-switch__label">雷翼表单</span>
          <el-radio-group v-model="leiyiFormMode" size="mini">
            <el-radio-button label="simple">精简版</el-radio-button>
            <el-radio-button label="full">完整版</el-radio-button>
          </el-radio-group>
        </div>

        <template v-if="leiyiFormMode === 'simple'">
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="运输方式" required>
                <el-select
                  v-model="shipForm.product_id"
                  filterable
                  clearable
                  placeholder="请选择雷翼运输方式"
                  :loading="sz56tProductLoading"
                  style="width:100%;"
                >
                  <el-option
                    v-for="item in sz56tProductOptions"
                    :key="item.product_id"
                    :label="item.label"
                    :value="item.product_id"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="订单号">
                <el-input v-model="shipForm.sz56t_form.order_customerinvoicecode" placeholder="不填则使用平台订单号" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="总重量(克)" required>
                <el-input-number v-model="shipForm.weight" :min="1" :max="50000" :step="10" style="width:100%;" />
              </el-form-item>
            </el-col>
          </el-row>

          <el-divider content-position="left">选择收件人</el-divider>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="目的地" required>
                <el-select
                  v-model="shipForm.sz56t_form.country"
                  filterable
                  clearable
                  default-first-option
                  placeholder="请选择目的地"
                  style="width:100%;"
                >
                  <el-option
                    v-for="item in sz56tCountryOptions"
                    :key="item.value || 'simple-country-empty'"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="收件人名" required>
                <el-input v-model="shipForm.sz56t_form.consignee_name" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="收件州/省" required>
                <el-input v-model="shipForm.sz56t_form.consignee_state" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="收件城市" required>
                <el-input v-model="shipForm.sz56t_form.consignee_city" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="收件邮编" required>
                <el-input v-model="shipForm.sz56t_form.consignee_postcode" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="收件电话" required>
                <el-input v-model="shipForm.sz56t_form.consignee_telephone" placeholder="必填，电话" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="24">
              <el-form-item label="收件地址" required>
                <el-input v-model="shipForm.sz56t_form.consignee_address" type="textarea" :rows="2" />
              </el-form-item>
            </el-col>
          </el-row>

          <el-divider content-position="left">申报信息</el-divider>
          <el-table :data="shipForm.sz56t_items" size="mini" border class="sz56t-simple-items-table">
            <el-table-column label="中文品名" min-width="130">
              <template slot-scope="{ row }">
                <el-input v-model="row.sku" size="mini" placeholder="选填" />
              </template>
            </el-table-column>
            <el-table-column min-width="170">
              <template slot="header">
                <span class="sz56t-required-header">* 英文品名</span>
              </template>
              <template slot-scope="{ row }">
                <el-input v-model="row.invoice_title" size="mini" placeholder="必填" />
              </template>
            </el-table-column>
            <el-table-column label="配货" min-width="140">
              <template slot-scope="{ row }">
                <el-input v-model="row.sku_code" size="mini" placeholder="配货选填" />
              </template>
            </el-table-column>
            <el-table-column width="160">
              <template slot="header">
                <span class="sz56t-required-header">* 单个商品重量(克)</span>
              </template>
              <template slot-scope="{ row }">
                <el-input-number v-model="row.invoice_weight" :min="1" :step="1" size="mini" style="width:100%;" />
              </template>
            </el-table-column>
            <el-table-column width="110">
              <template slot="header">
                <span class="sz56t-required-header">* 产品数量</span>
              </template>
              <template slot-scope="{ row }">
                <el-input-number v-model="row.invoice_pcs" :min="1" :step="1" size="mini" style="width:100%;" />
              </template>
            </el-table-column>
            <el-table-column width="140">
              <template slot="header">
                <span class="sz56t-required-header">* 总金额USD</span>
              </template>
              <template slot-scope="{ row }">
                <el-input-number v-model="row.invoice_amount" :min="0.01" :step="0.01" :precision="2" size="mini" style="width:100%;" />
              </template>
            </el-table-column>
            <el-table-column label="出口海关编码" min-width="150">
              <template slot-scope="{ row }">
                <el-input v-model="row.hs_code" size="mini" placeholder="选填" />
              </template>
            </el-table-column>
            <el-table-column label="申报币种" width="120">
              <template slot-scope="{ row }">
                <el-select v-model="row.invoice_currency" size="mini" style="width:100%;">
                  <el-option v-for="item in sz56tCurrencyOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="90" fixed="right">
              <template slot-scope="{ $index }">
                <el-button type="text" size="mini" @click="removeSz56tItem($index)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="sz56t-item-actions">
            <el-button size="mini" plain icon="el-icon-plus" @click="addSz56tItem">新增申报项</el-button>
          </div>
        </template>

        <template v-else>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="运输方式" required>
                <el-select
                  v-model="shipForm.product_id"
                  filterable
                  clearable
                  placeholder="请选择雷翼运输方式"
                  :loading="sz56tProductLoading"
                  style="width:100%;"
                >
                  <el-option
                    v-for="item in sz56tProductOptions"
                    :key="item.product_id"
                    :label="item.label"
                    :value="item.product_id"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="订单号">
                <el-input v-model="shipForm.sz56t_form.order_customerinvoicecode" placeholder="不填则使用平台订单号" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="包裹类型">
                <el-select v-model="shipForm.sz56t_form.cargo_type" style="width:100%;">
                  <el-option v-for="item in sz56tCargoTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="销售地址">
                <el-input v-model="shipForm.sz56t_form.order_transactionurl" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="图片地址">
                <el-input v-model="shipForm.sz56t_form.product_imagepath" placeholder="多张图片可用分号分隔" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="电池类型">
                <el-select v-model="shipForm.sz56t_form.battery_type" filterable clearable style="width:100%;">
                  <el-option v-for="item in sz56tBatteryTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="总重量(克)" required>
                <el-input-number v-model="shipForm.weight" :min="1" :max="50000" :step="10" style="width:100%;" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="件数">
                <el-input-number v-model="shipForm.sz56t_form.order_piece" :min="1" :max="999" :step="1" style="width:100%;" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="发票编号">
                <el-input v-model="shipForm.sz56t_form.invoice_no" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12" class="sz56t-official-top-row">
            <el-col :span="16">
              <el-row :gutter="12">
                <el-col :span="8">
                  <el-form-item label="退件服务">
                    <el-radio-group v-model="shipForm.sz56t_form.order_returnsign">
                      <el-radio label="N">不退回</el-radio>
                      <el-radio label="Y">退回</el-radio>
                    </el-radio-group>
                  </el-form-item>
                </el-col>
                <el-col :span="8">
                  <el-form-item label="报关">
                    <el-select v-model="shipForm.sz56t_form.customs_declaration" style="width:100%;">
                      <el-option v-for="item in sz56tCustomsDeclarationOptions" :key="item.value" :label="item.label" :value="item.value" />
                    </el-select>
                  </el-form-item>
                </el-col>
                <el-col :span="8">
                  <el-form-item label="保险">
                    <el-input v-model="shipForm.sz56t_form.order_insurance" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="12">
                <el-col :span="8">
                  <el-form-item label="其他金额">
                    <el-input v-model="shipForm.sz56t_form.order_cargoamount" placeholder="用于 DHL/白关等场景" />
                  </el-form-item>
                </el-col>
                <el-col :span="8">
                  <el-form-item label="手续费">
                    <el-input v-model="shipForm.sz56t_form.order_handlingamount" />
                  </el-form-item>
                </el-col>
                <el-col :span="8">
                  <el-form-item label="发件人参考号">
                    <el-input v-model="shipForm.sz56t_form.shipper_reference" />
                  </el-form-item>
                </el-col>
              </el-row>
            </el-col>
            <el-col :span="8">
              <el-form-item label="自定义化">
                <el-input v-model="shipForm.sz56t_form.order_customnote" type="textarea" :rows="4" class="sz56t-customnote" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="生产商销售供应商">
                <el-input v-model="shipForm.sz56t_form.production_sales_suppliers_name" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="社会信用代码">
                <el-input v-model="shipForm.sz56t_form.production_sales_suppliers_credit_code" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="买家ID">
                <el-input v-model="shipForm.sz56t_form.buyerid" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="电商平台名称">
                <el-input v-model="shipForm.sz56t_form.ecommerce_platform_name" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="电商平台代码">
                <el-input v-model="shipForm.sz56t_form.ecommerce_platform_code" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="店铺代码">
                <el-input v-model="shipForm.sz56t_form.store_code" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="店铺名称">
                <el-input v-model="shipForm.sz56t_form.store_name" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="发件人类型">
                <el-input v-model="shipForm.sz56t_form.shipper_tradetype" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="收件人类型">
                <el-input v-model="shipForm.sz56t_form.consignee_tradetype" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="5">
              <el-form-item label="关税类型">
                <el-select v-model="shipForm.sz56t_form.duty_type" clearable style="width:100%;">
                  <el-option v-for="item in sz56tDutyTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="5">
              <el-form-item label="关税支付账号">
                <el-input v-model="shipForm.sz56t_form.duty_account" />
              </el-form-item>
            </el-col>
            <el-col :span="5">
              <el-form-item label="关税国家">
                <el-select
                  v-model="shipForm.sz56t_form.thirdPartyCountryCode"
                  filterable
                  clearable
                  default-first-option
                  placeholder="请选择关税国家"
                  style="width:100%;"
                >
                  <el-option
                    v-for="item in sz56tCountryOptions"
                    :key="item.value || 'duty-country-empty'"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="4">
              <el-form-item label="关税邮编">
                <el-input v-model="shipForm.sz56t_form.thirdPartyPostCode" />
              </el-form-item>
            </el-col>
            <el-col :span="5">
              <el-form-item label="关税公司">
                <el-input v-model="shipForm.sz56t_form.thirdpartycompany" />
              </el-form-item>
            </el-col>
          </el-row>

          <el-divider content-position="left">选择收件人</el-divider>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="目的地" required>
                <el-select
                  v-model="shipForm.sz56t_form.country"
                  filterable
                  clearable
                  default-first-option
                  placeholder="请选择目的地"
                  style="width:100%;"
                >
                  <el-option
                    v-for="item in sz56tCountryOptions"
                    :key="item.value || 'country-empty'"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="收件人名" required>
                <el-input v-model="shipForm.sz56t_form.consignee_name" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="收件公司">
                <el-input v-model="shipForm.sz56t_form.consignee_companyname" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="收件州/省" required>
                <el-input v-model="shipForm.sz56t_form.consignee_state" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="收件城市" required>
                <el-input v-model="shipForm.sz56t_form.consignee_city" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="收件邮编" required>
                <el-input v-model="shipForm.sz56t_form.consignee_postcode" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="12">
              <el-form-item label="收件地址" required>
                <el-input v-model="shipForm.sz56t_form.consignee_address" type="textarea" :rows="2" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="收件电话">
                <el-input v-model="shipForm.sz56t_form.consignee_telephone" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="收件手机">
                <el-input v-model="shipForm.sz56t_form.consignee_mobile" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="收件邮箱">
                <el-input v-model="shipForm.sz56t_form.consignee_email" />
              </el-form-item>
            </el-col>
            <el-col :span="4">
              <el-form-item label="街道号">
                <el-input v-model="shipForm.sz56t_form.consignee_streetno" />
              </el-form-item>
            </el-col>
            <el-col :span="4">
              <el-form-item label="门牌号">
                <el-input v-model="shipForm.sz56t_form.consignee_doorno" />
              </el-form-item>
            </el-col>
            <el-col :span="4">
              <el-form-item label="收件人区">
                <el-input v-model="shipForm.sz56t_form.consignee_suburb" />
              </el-form-item>
            </el-col>
            <el-col :span="4">
              <el-form-item label="短地址">
                <el-input v-model="shipForm.sz56t_form.consignee_shortaddress" />
              </el-form-item>
            </el-col>
          </el-row>
          <div class="sz56t-form-tip">联系电话或手机号至少填写一个</div>
          <el-row :gutter="12">
            <el-col :span="6">
              <el-form-item label="税号">
                <el-input v-model="shipForm.sz56t_form.consignee_taxno" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="税号类别">
                <el-select v-model="shipForm.sz56t_form.consignee_taxnotype" clearable style="width:100%;">
                  <el-option v-for="item in sz56tTaxTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="税号地区">
                <el-select
                  v-model="shipForm.sz56t_form.consignee_taxnocountry"
                  filterable
                  clearable
                  default-first-option
                  placeholder="请选择国家/地区"
                  style="width:100%;"
                >
                  <el-option
                    v-for="item in sz56tCountryOptions"
                    :key="item.value || 'consignee-tax-country-empty'"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="6">
              <el-form-item label="护照号">
                <el-input v-model="shipForm.sz56t_form.consignee_passportno" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="护照序列">
                <el-input v-model="shipForm.sz56t_form.consignee_passportserialnumber" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="护照签发日">
                <el-date-picker
                  v-model="shipForm.sz56t_form.consignee_passportissuedate"
                  type="date"
                  value-format="yyyy-MM-dd"
                  placeholder="请选择日期"
                  style="width:100%;"
                />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="生日">
                <el-date-picker
                  v-model="shipForm.sz56t_form.consignee_datebirth"
                  type="date"
                  value-format="yyyy-MM-dd"
                  placeholder="请选择日期"
                  style="width:100%;"
                />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="12">
              <el-form-item label="护照签发机构">
                <el-input v-model="shipForm.sz56t_form.consignee_passportissuedby" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="出口原因">
                <el-select v-model="shipForm.sz56t_form.export_reason" clearable style="width:100%;">
                  <el-option v-for="item in sz56tExportReasonOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>

          <el-divider content-position="left">发件人信息</el-divider>
          <el-row :gutter="12">
            <el-col :span="6">
              <el-form-item label="发件人姓名">
                <el-input v-model="shipForm.sz56t_form.shipper_name" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="发件公司">
                <el-input v-model="shipForm.sz56t_form.shipper_companyname" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="发件国家/地区">
                <el-select
                  v-model="shipForm.sz56t_form.shipper_country"
                  filterable
                  clearable
                  default-first-option
                  placeholder="请选择国家/地区"
                  style="width:100%;"
                >
                  <el-option
                    v-for="item in sz56tCountryOptions"
                    :key="item.value || 'shipper-country-empty'"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="发件电话">
                <el-input v-model="shipForm.sz56t_form.shipper_telephone" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="6">
              <el-form-item label="发件州/省">
                <el-input v-model="shipForm.sz56t_form.shipper_state" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="发件城市">
                <el-input v-model="shipForm.sz56t_form.shipper_city" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="发件邮编">
                <el-input v-model="shipForm.sz56t_form.shipper_postcode" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="发件人区">
                <el-input v-model="shipForm.sz56t_form.shipper_suburb" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="地址1">
                <el-input v-model="shipForm.sz56t_form.shipper_address1" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="地址2">
                <el-input v-model="shipForm.sz56t_form.shipper_address2" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="地址3">
                <el-input v-model="shipForm.sz56t_form.shipper_address3" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="6">
              <el-form-item label="发件邮箱">
                <el-input v-model="shipForm.sz56t_form.shipper_email" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="门牌号">
                <el-input v-model="shipForm.sz56t_form.shipper_doorno" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="护照号">
                <el-input v-model="shipForm.sz56t_form.shipper_passportno" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="发件人税号">
                <el-input v-model="shipForm.sz56t_form.shipper_taxno" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="6">
              <el-form-item label="税号类别">
                <el-select v-model="shipForm.sz56t_form.shipper_taxnotype" clearable style="width:100%;">
                  <el-option v-for="item in sz56tTaxTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="税号国家/地区">
                <el-select
                  v-model="shipForm.sz56t_form.shipper_taxnocountry"
                  filterable
                  clearable
                  default-first-option
                  placeholder="请选择国家/地区"
                  style="width:100%;"
                >
                  <el-option
                    v-for="item in sz56tCountryOptions"
                    :key="item.value || 'shipper-tax-country-empty'"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>

          <el-divider content-position="left">进口商信息</el-divider>
          <el-row :gutter="12">
            <el-col :span="6">
              <el-form-item label="进口商代码">
                <el-input v-model="shipForm.sz56t_form.import_code" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="进口商名称">
                <el-input v-model="shipForm.sz56t_form.import_name" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="进口商公司">
                <el-input v-model="shipForm.sz56t_form.import_companyname" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="国家/地区">
                <el-select
                  v-model="shipForm.sz56t_form.import_country"
                  filterable
                  clearable
                  default-first-option
                  placeholder="请选择国家/地区"
                  style="width:100%;"
                >
                  <el-option
                    v-for="item in sz56tCountryOptions"
                    :key="item.value || 'import-country-empty'"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="6">
              <el-form-item label="城市">
                <el-input v-model="shipForm.sz56t_form.import_city" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="州/省">
                <el-input v-model="shipForm.sz56t_form.import_state" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="邮编">
                <el-input v-model="shipForm.sz56t_form.import_postcode" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="电话">
                <el-input v-model="shipForm.sz56t_form.import_telephone" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="6">
              <el-form-item label="邮箱">
                <el-input v-model="shipForm.sz56t_form.import_email" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="税号">
                <el-input v-model="shipForm.sz56t_form.import_taxno" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="税号类别">
                <el-select v-model="shipForm.sz56t_form.import_taxtype" clearable style="width:100%;">
                  <el-option v-for="item in sz56tTaxTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="税号国家/地区">
                <el-select
                  v-model="shipForm.sz56t_form.import_taxcountry"
                  filterable
                  clearable
                  default-first-option
                  placeholder="请选择国家/地区"
                  style="width:100%;"
                >
                  <el-option
                    v-for="item in sz56tCountryOptions"
                    :key="item.value || 'import-tax-country-empty'"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8">
              <el-form-item label="地址1">
                <el-input v-model="shipForm.sz56t_form.import_address" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="地址2">
                <el-input v-model="shipForm.sz56t_form.import_address2" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="地址3">
                <el-input v-model="shipForm.sz56t_form.import_address3" />
              </el-form-item>
            </el-col>
          </el-row>

          <el-divider content-position="left">材积信息</el-divider>
          <el-table :data="shipForm.sz56t_form.orderVolumeParam" size="mini" border class="sz56t-volume-table">
            <el-table-column label="长(cm)" width="140">
              <template slot-scope="{ row }">
                <el-input-number v-model="row.volume_length" :min="1" :max="200" :step="1" size="mini" style="width:100%;" />
              </template>
            </el-table-column>
            <el-table-column label="宽(cm)" width="140">
              <template slot-scope="{ row }">
                <el-input-number v-model="row.volume_width" :min="1" :max="200" :step="1" size="mini" style="width:100%;" />
              </template>
            </el-table-column>
            <el-table-column label="高(cm)" width="140">
              <template slot-scope="{ row }">
                <el-input-number v-model="row.volume_height" :min="1" :max="200" :step="1" size="mini" style="width:100%;" />
              </template>
            </el-table-column>
            <el-table-column label="实重(克)" width="160">
              <template slot-scope="{ row }">
                <el-input-number v-model="row.volume_weight" :min="1" :max="50000" :step="10" size="mini" style="width:100%;" />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="90" fixed="right">
              <template slot-scope="{ $index }">
                <el-button type="text" size="mini" @click="removeSz56tVolume($index)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="sz56t-item-actions">
            <el-button size="mini" plain icon="el-icon-plus" @click="addSz56tVolume">新增材积行</el-button>
          </div>

          <el-divider content-position="left">申报信息</el-divider>
          <el-table :data="shipForm.sz56t_items" size="mini" border class="sz56t-items-table">
            <el-table-column type="expand" width="48">
              <template slot-scope="{ row }">
                <div class="sz56t-expand-grid">
                  <el-form-item label="销售地址" label-width="110px">
                    <el-input v-model="row.transaction_url" size="mini" />
                  </el-form-item>
                  <el-form-item label="图片地址" label-width="110px">
                    <el-input v-model="row.invoice_imgurl" size="mini" />
                  </el-form-item>
                  <el-form-item label="申报单位" label-width="110px">
                    <el-select v-model="row.invoiceunit_code" size="mini" style="width:100%;">
                      <el-option v-for="item in sz56tInvoiceUnitOptions" :key="item.value" :label="item.label" :value="item.value" />
                    </el-select>
                  </el-form-item>
                  <el-form-item label="品牌" label-width="110px">
                    <el-input v-model="row.invoice_brand" size="mini" />
                  </el-form-item>
                  <el-form-item label="规格" label-width="110px">
                    <el-input v-model="row.invoice_rule" size="mini" />
                  </el-form-item>
                  <el-form-item label="税则号" label-width="110px">
                    <el-input v-model="row.invoice_taxno" size="mini" />
                  </el-form-item>
                  <el-form-item label="材质" label-width="110px">
                    <el-input v-model="row.invoice_material" size="mini" />
                  </el-form-item>
                  <el-form-item label="用途" label-width="110px">
                    <el-input v-model="row.invoice_purpose" size="mini" />
                  </el-form-item>
                  <el-form-item label="出口单价" label-width="110px">
                    <el-input-number v-model="row.invoice_export_unitprice" :min="0" :step="0.01" :precision="2" size="mini" style="width:100%;" />
                  </el-form-item>
                  <el-form-item label="出口币种" label-width="110px">
                    <el-select v-model="row.invoice_export_currency" size="mini" style="width:100%;">
                      <el-option v-for="item in sz56tCurrencyOptions" :key="item.value" :label="item.label" :value="item.value" />
                    </el-select>
                  </el-form-item>
                  <el-form-item label="供应商" label-width="110px">
                    <el-input v-model="row.invoice_production_sales_suppliers_name" size="mini" />
                  </el-form-item>
                  <el-form-item label="信用代码" label-width="110px">
                    <el-input v-model="row.invoice_production_sales_suppliers_credit_code" size="mini" />
                  </el-form-item>
                  <el-form-item label="进口海关编码" label-width="110px">
                    <el-input v-model="row.import_hs_code" size="mini" />
                  </el-form-item>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="中文品名" min-width="160">
              <template slot-scope="{ row }">
                <el-input v-model="row.sku" size="mini" />
              </template>
            </el-table-column>
            <el-table-column label="英文品名" min-width="180">
              <template slot-scope="{ row }">
                <el-input v-model="row.invoice_title" size="mini" />
              </template>
            </el-table-column>
            <el-table-column label="配货信息" min-width="140">
              <template slot-scope="{ row }">
                <el-input v-model="row.sku_code" size="mini" />
              </template>
            </el-table-column>
            <el-table-column label="海关编码" min-width="130">
              <template slot-scope="{ row }">
                <el-input v-model="row.hs_code" size="mini" />
              </template>
            </el-table-column>
            <el-table-column label="数量" width="90">
              <template slot-scope="{ row }">
                <el-input-number v-model="row.invoice_pcs" :min="1" :step="1" size="mini" />
              </template>
            </el-table-column>
            <el-table-column label="申报金额" width="120">
              <template slot-scope="{ row }">
                <el-input-number v-model="row.invoice_amount" :min="0.01" :step="0.01" :precision="2" size="mini" />
              </template>
            </el-table-column>
            <el-table-column label="单件重(克)" width="130">
              <template slot-scope="{ row }">
                <el-input-number v-model="row.invoice_weight" :min="1" :step="1" size="mini" />
              </template>
            </el-table-column>
            <el-table-column label="币种" width="100">
              <template slot-scope="{ row }">
                <el-select v-model="row.invoice_currency" size="mini" style="width:100%;">
                  <el-option v-for="item in sz56tCurrencyOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column label="原产国" width="170">
              <template slot-scope="{ row }">
                <el-select
                  v-model="row.origin_country"
                  filterable
                  clearable
                  default-first-option
                  size="mini"
                  placeholder="原产国"
                  style="width:100%;"
                >
                  <el-option
                    v-for="item in sz56tCountryOptions"
                    :key="item.value || 'origin-country-empty'"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="90" fixed="right">
              <template slot-scope="{ $index }">
                <el-button type="text" size="mini" @click="removeSz56tItem($index)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="sz56t-item-actions">
            <el-button size="mini" plain icon="el-icon-plus" @click="addSz56tItem">新增申报项</el-button>
          </div>
        </template>
      </template>

      <el-form-item v-if="shipForm.track_number" label="当前运单号">
        <el-input :value="shipForm.track_number" disabled />
      </el-form-item>
    </el-form>

    <div slot="footer">
      <el-button @click="dialogVisible = false">取消</el-button>
      <el-button v-if="isFbs" @click="$emit('refresh-fbs-workflow')">刷新状态</el-button>
      <template v-else>
        <el-button
          v-if="shipForm.ship_provider === 'leiyi' && canCancelSz56tWaybill"
          type="danger"
          plain
          :loading="shipping"
          @click="$emit('cancel-waybill')"
        >取消订单</el-button>
        <el-button type="success" :loading="shipping" @click="$emit('submit-ship')">{{ dbsSubmitButtonText }}</el-button>
      </template>
    </div>
  </el-dialog>
</template>

<script>
import {
  ORDER_DICT_CODE,
  SZ56T_BATTERY_TYPE_OPTIONS,
  SZ56T_CARGO_TYPE_OPTIONS,
  SZ56T_CURRENCY_OPTIONS,
  SZ56T_CUSTOMS_DECLARATION_OPTIONS,
  SZ56T_DUTY_TYPE_OPTIONS,
  SZ56T_EXPORT_REASON_OPTIONS,
  SZ56T_INVOICE_UNIT_OPTIONS,
  SZ56T_TAX_TYPE_OPTIONS,
  createDefaultSz56tItem,
  createDefaultSz56tVolume
} from '../constants'
import { SZ56T_COUNTRY_OPTIONS } from '../sz56tCountryOptions'
import {
  formatDate,
  getDictOptionsByCode,
  getHandoverListStatusLabel,
  getHandoverShipmentTypeLabel,
  getLogisticOrderStatusLabel,
  isDbsLogisticsType
} from '../utils'

export default {
  name: 'OrderShipDialog',
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    title: {
      type: String,
      default: '实际发货'
    },
    shipForm: {
      type: Object,
      required: true
    },
    shipping: {
      type: Boolean,
      default: false
    },
    canCancelSz56tWaybill: {
      type: Boolean,
      default: false
    },
    sz56tProductOptions: {
      type: Array,
      default: () => []
    },
    sz56tProductLoading: {
      type: Boolean,
      default: false
    },
    dictLabelMap: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      leiyiFormMode: 'simple',
      sz56tBatteryTypeOptions: SZ56T_BATTERY_TYPE_OPTIONS,
      sz56tCargoTypeOptions: SZ56T_CARGO_TYPE_OPTIONS,
      sz56tCountryOptions: SZ56T_COUNTRY_OPTIONS,
      sz56tCurrencyOptions: SZ56T_CURRENCY_OPTIONS,
      sz56tCustomsDeclarationOptions: SZ56T_CUSTOMS_DECLARATION_OPTIONS,
      sz56tDutyTypeOptions: SZ56T_DUTY_TYPE_OPTIONS,
      sz56tExportReasonOptions: SZ56T_EXPORT_REASON_OPTIONS,
      sz56tInvoiceUnitOptions: SZ56T_INVOICE_UNIT_OPTIONS,
      sz56tTaxTypeOptions: SZ56T_TAX_TYPE_OPTIONS
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
    },
    dialogWidth() {
      if (this.isFbs) {
        return '900px'
      }

      if (this.shipForm.ship_provider === 'chinapost') {
        return '980px'
      }

      if (this.shipForm.ship_provider === 'leiyi') {
        if (typeof window === 'undefined') {
          return '1760px'
        }

        return `${Math.min(window.innerWidth - 24, 1760)}px`
      }

      return '520px'
    },
    isFbs() {
      return !isDbsLogisticsType(this.shipForm.logistics_type)
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
      if (!this.shipForm.handover_list_id) {
        return false
      }

      return ['Accepted', 'PartiallyAccepted', 'Sent'].includes(this.shipForm.handover_list_status)
    },
    dbsSubmitButtonText() {
      if (this.shipForm.ship_provider === 'chinapost') {
        return '记录邮政发货'
      }

      if (this.shipForm.ship_provider === 'leiyi') {
        return '确定'
      }

      return '记录发货'
    }
  },
  watch: {
    visible(value) {
      if (!value) {
        return
      }

      this.normalizeDbsShipProvider()

      if (this.shipForm.ship_provider === 'chinapost') {
        this.$emit('load-chinapost-preview')
      }

      if (this.shipForm.ship_provider === 'leiyi') {
        this.leiyiFormMode = 'simple'
        this.$emit('load-sz56t-products')
      }
    }
  },
  methods: {
    formatDate,
    normalizeDbsShipProvider() {
      if (this.shipForm.logistics_type !== 'DBS') {
        return
      }

      if (!['chinapost', 'leiyi'].includes(this.shipForm.ship_provider)) {
        this.$set(this.shipForm, 'ship_provider', 'chinapost')
      }
    },
    handleShipProviderChange(value) {
      if (value === 'chinapost') {
        this.$emit('load-chinapost-preview')
      }

      if (value === 'leiyi') {
        this.leiyiFormMode = 'simple'
        this.$emit('load-sz56t-products')
      }
    },
    addSz56tItem() {
      if (!Array.isArray(this.shipForm.sz56t_items)) {
        this.$set(this.shipForm, 'sz56t_items', [])
      }

      this.shipForm.sz56t_items.push(createDefaultSz56tItem())
    },
    addSz56tVolume() {
      if (!this.shipForm.sz56t_form || typeof this.shipForm.sz56t_form !== 'object') {
        this.$set(this.shipForm, 'sz56t_form', {})
      }

      if (!Array.isArray(this.shipForm.sz56t_form.orderVolumeParam)) {
        this.$set(this.shipForm.sz56t_form, 'orderVolumeParam', [])
      }

      this.shipForm.sz56t_form.orderVolumeParam.push(createDefaultSz56tVolume())
    },
    removeSz56tItem(index) {
      if (!Array.isArray(this.shipForm.sz56t_items)) {
        return
      }

      if (this.shipForm.sz56t_items.length <= 1) {
        this.shipForm.sz56t_items.splice(0, 1, createDefaultSz56tItem())
        return
      }

      this.shipForm.sz56t_items.splice(index, 1)
    },
    removeSz56tVolume(index) {
      if (!this.shipForm.sz56t_form || !Array.isArray(this.shipForm.sz56t_form.orderVolumeParam)) {
        return
      }

      this.shipForm.sz56t_form.orderVolumeParam.splice(index, 1)
    },
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
.fbs-workflow {
  min-height: 540px;
}

.workflow-steps {
  margin: 20px 0 24px;
}

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

.summary-label {
  color: #909399;
  font-size: 12px;
  margin-bottom: 6px;
}

.summary-value {
  color: #303133;
  font-size: 14px;
  font-weight: 600;
  word-break: break-all;
}

.workflow-panel {
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 18px;
  background: #fff;
}

.sz56t-items-table {
  margin-top: 8px;
}

.sz56t-simple-items-table {
  margin-top: 8px;
}

.sz56t-volume-table {
  margin-top: 8px;
}

.sz56t-form-mode-switch {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.sz56t-form-mode-switch__label {
  color: #606266;
  font-size: 13px;
  line-height: 1;
}

.sz56t-required-header {
  color: #f56c6c;
  font-weight: 500;
}

.sz56t-expand-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px 16px;
}

.sz56t-expand-grid :deep(.el-form-item) {
  margin-bottom: 8px;
}

.sz56t-form :deep(.el-form-item) {
  margin-bottom: 14px;
}

.sz56t-official-top-row :deep(.el-form-item) {
  margin-bottom: 12px;
}

.sz56t-form :deep(.el-divider--horizontal) {
  margin: 18px 0;
}

.sz56t-form :deep(.el-textarea__inner) {
  min-height: 64px !important;
}

.sz56t-customnote :deep(.el-textarea__inner) {
  min-height: 96px !important;
}

.sz56t-form-tip {
  margin: -4px 0 12px 108px;
  color: #909399;
  font-size: 12px;
}

.sz56t-item-actions {
  margin-top: 12px;
  display: flex;
  justify-content: flex-end;
}

.ship-item-cell {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.ship-item-image {
  width: 52px;
  height: 52px;
  border-radius: 6px;
  border: 1px solid #ebeef5;
  flex-shrink: 0;
}

.ship-item-image--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f7fa;
  color: #909399;
  font-size: 12px;
}

.ship-item-meta {
  min-width: 0;
}

.ship-item-title {
  color: #303133;
  font-size: 13px;
  line-height: 1.45;
  word-break: break-word;
}

.ship-item-subtitle {
  margin-top: 4px;
  color: #909399;
  font-size: 12px;
}

.panel-title {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 16px;
}

.panel-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 18px;
}

.multi-actions {
  flex-wrap: wrap;
}

.workflow-check {
  display: block;
  margin-top: 18px;
}

.summary-inline {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  color: #606266;
  font-size: 13px;
}

.summary-inline--stack {
  flex-direction: column;
  gap: 8px;
}

.chinapost-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}

.chinapost-toolbar__tip {
  color: #909399;
  font-size: 12px;
  line-height: 1.6;
}

.chinapost-json-editor :deep(textarea) {
  font-family: Consolas, 'Courier New', monospace;
  line-height: 1.6;
}

@media (max-width: 900px) {
  .workflow-summary {
    grid-template-columns: 1fr;
  }

  .sz56t-expand-grid {
    grid-template-columns: 1fr;
  }

  .sz56t-form :deep(.el-col-5),
  .sz56t-form :deep(.el-col-4),
  .sz56t-form :deep(.el-col-6),
  .sz56t-form :deep(.el-col-8),
  .sz56t-form :deep(.el-col-16) {
    width: 100%;
  }

  .sz56t-form-tip {
    margin-left: 0;
  }

  .sz56t-form-mode-switch {
    align-items: flex-start;
    flex-direction: column;
  }

  .chinapost-toolbar {
    align-items: flex-start;
    flex-direction: column;
  }
}

@media (max-width: 1280px) {
  .sz56t-expand-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .sz56t-form :deep(.el-col-5),
  .sz56t-form :deep(.el-col-4),
  .sz56t-form :deep(.el-col-6) {
    width: 50%;
  }

  .sz56t-form :deep(.el-col-8),
  .sz56t-form :deep(.el-col-12),
  .sz56t-form :deep(.el-col-16) {
    width: 100%;
  }
}
</style>
