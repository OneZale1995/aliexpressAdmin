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
          title="中国邮政采用三步录入，固定接口参数已内置。"
          type="info"
          :closable="false"
          show-icon
        />

        <div class="chinapost-toolbar">
          <div class="chinapost-hot-country">
            <span class="chinapost-toolbar__tip">热门国家/地区：</span>
            <el-button
              v-for="item in chinaPostHotNations"
              :key="item.value"
              size="mini"
              type="text"
              @click="useChinaPostHotNation(item.value)"
            >{{ item.label }}</el-button>
          </div>
          <el-button size="mini" plain @click="$emit('load-chinapost-preview', true)">按订单重置默认值</el-button>
        </div>

        <div class="chinapost-step-panel">
          <el-steps :active="chinaPostStep" finish-status="success" align-center>
            <el-step title="基本信息" />
            <el-step title="寄递信息" />
            <el-step title="报关信息" />
          </el-steps>
        </div>

        <div v-show="chinaPostStep === 0" class="chinapost-section">
          <el-divider content-position="left">基本信息</el-divider>
          <el-row :gutter="12">
            <el-col :span="8"><el-form-item label="业务产品" required><el-input value="e邮宝特惠(019)" disabled /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="寄达国/地区" required><el-input v-model="shipForm.chinapost_receiver.nation" placeholder="如 RU / US / GB" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="寄达国/地区简称"><el-input :value="chinaPostNationHint" disabled /></el-form-item></el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8"><el-form-item label="物流订单号" required><el-input v-model="shipForm.chinapost_form.logistics_order_no" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="订单接入时间" required><el-input v-model="shipForm.chinapost_form.created_time" placeholder="yyyy-MM-dd HH:mm:ss" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="自定义编号"><el-input v-model="shipForm.chinapost_form.barcode" /></el-form-item></el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8"><el-form-item label="协议客户代码" required><el-input v-model="shipForm.sender_no" placeholder="邮政13-15位大客户代码" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="电商标识" required><el-input v-model="shipForm.chinapost_form.mailType" placeholder="对接注册电商/ERP标识" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="揽收机构编号" required><el-input v-model="shipForm.chinapost_form.wh_code" placeholder="例如 10000030" /></el-form-item></el-col>
          </el-row>

          <el-divider content-position="left">包裹信息</el-divider>
          <el-row :gutter="12">
            <el-col :span="6"><el-form-item label="长(厘米)"><el-input-number v-model="shipForm.chinapost_form.length" :min="0" :step="1" style="width:100%;" /></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="宽(厘米)"><el-input-number v-model="shipForm.chinapost_form.width" :min="0" :step="1" style="width:100%;" /></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="高(厘米)"><el-input-number v-model="shipForm.chinapost_form.height" :min="0" :step="1" style="width:100%;" /></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="邮件重量(g)" required><el-input-number v-model="shipForm.weight" :min="1" :step="1" style="width:100%;" /></el-form-item></el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="6"><el-form-item label="邮件体积"><el-input v-model="shipForm.chinapost_form.volume" /></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="已有运单号"><el-input v-model="shipForm.chinapost_form.waybill_no" placeholder="选填，手写单补录" /></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="内件总重量(g)"><el-input-number v-model="shipForm.chinapost_form.contents_total_weight" :min="0" :step="1" style="width:100%;" /></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="内件总价值"><el-input-number v-model="shipForm.chinapost_form.contents_total_value" :min="0" :step="0.01" :precision="2" style="width:100%;" /></el-form-item></el-col>
          </el-row>
        </div>

        <div v-show="chinaPostStep === 1" class="chinapost-section">
          <el-divider content-position="left">寄件人信息</el-divider>
          <div class="chinapost-actions-line">
            <el-button size="mini" type="text" @click="openAddressBook('sender')">从地址簿选择</el-button>
            <el-button size="mini" type="text" @click="saveAddressBookEntry('sender')">保存寄件人</el-button>
          </div>
          <el-row :gutter="12">
            <el-col :span="8"><el-form-item label="寄件人姓名" required><el-input v-model="shipForm.chinapost_sender.name" placeholder="输入寄件人姓名" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="手机号码" required><el-input v-model="shipForm.chinapost_sender.mobile" placeholder="输入寄件人手机号" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="座机电话"><el-input v-model="shipForm.chinapost_sender.phone" /></el-form-item></el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8"><el-form-item label="电子邮箱"><el-input v-model="shipForm.chinapost_sender.email" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="所在国家" required><el-select v-model="shipForm.chinapost_sender.nation" filterable allow-create default-first-option clearable placeholder="输入或选择国家代码" style="width:100%;" :loading="regionLoading.sender" @change="handleChinaPostRegionFieldChange('sender', 'nation')"><el-option v-for="item in getChinaPostRegionOptions('sender', 'nations')" :key="`sender-nation-${item.value}`" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="寄件人邮编"><el-select v-model="shipForm.chinapost_sender.post_code" filterable allow-create default-first-option clearable placeholder="输入或选择邮编" style="width:100%;" :loading="regionLoading.sender"><el-option v-for="item in getChinaPostRegionOptions('sender', 'postCodes')" :key="`sender-post-${item.value}`" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8"><el-form-item label="省/州" required><el-select v-model="shipForm.chinapost_sender.province" filterable allow-create default-first-option clearable placeholder="输入或选择省/州" style="width:100%;" :loading="regionLoading.sender" @change="handleChinaPostRegionFieldChange('sender', 'province')"><el-option v-for="item in getChinaPostRegionOptions('sender', 'provinces')" :key="`sender-province-${item.value}`" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="市" required><el-select v-model="shipForm.chinapost_sender.city" filterable allow-create default-first-option clearable placeholder="输入或选择城市" style="width:100%;" :loading="regionLoading.sender" @change="handleChinaPostRegionFieldChange('sender', 'city')"><el-option v-for="item in getChinaPostRegionOptions('sender', 'cities')" :key="`sender-city-${item.value}`" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="区/县" required><el-select v-model="shipForm.chinapost_sender.county" filterable allow-create default-first-option clearable placeholder="输入或选择区/县" style="width:100%;" :loading="regionLoading.sender" @change="handleChinaPostRegionFieldChange('sender', 'county')"><el-option v-for="item in getChinaPostRegionOptions('sender', 'counties')" :key="`sender-county-${item.value}`" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="16"><el-form-item label="寄件人地址" required><el-input v-model="shipForm.chinapost_sender.address" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="公司名称"><el-input v-model="shipForm.chinapost_sender.company" /></el-form-item></el-col>
          </el-row>

          <el-divider content-position="left">收件人信息</el-divider>
          <div class="chinapost-actions-line">
            <el-button size="mini" type="text" @click="openAddressBook('receiver')">从地址簿选择</el-button>
            <el-button size="mini" type="text" @click="saveAddressBookEntry('receiver')">保存收件人</el-button>
          </div>
          <el-row :gutter="12">
            <el-col :span="8"><el-form-item label="收件人姓名" required><el-input v-model="shipForm.chinapost_receiver.name" placeholder="输入收件人姓名" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="收件人电话" required><el-input v-model="shipForm.chinapost_receiver.phone" placeholder="输入收件人电话" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="手机号码"><el-input v-model="shipForm.chinapost_receiver.mobile" /></el-form-item></el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8"><el-form-item label="电子邮箱"><el-input v-model="shipForm.chinapost_receiver.email" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="所在国家" required><el-select v-model="shipForm.chinapost_receiver.nation" filterable allow-create default-first-option clearable placeholder="输入或选择国家代码" style="width:100%;" :loading="regionLoading.receiver" @change="handleChinaPostRegionFieldChange('receiver', 'nation')"><el-option v-for="item in getChinaPostRegionOptions('receiver', 'nations')" :key="`receiver-nation-${item.value}`" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="邮政编码"><el-select v-model="shipForm.chinapost_receiver.post_code" filterable allow-create default-first-option clearable placeholder="输入或选择邮编" style="width:100%;" :loading="regionLoading.receiver"><el-option v-for="item in getChinaPostRegionOptions('receiver', 'postCodes')" :key="`receiver-post-${item.value}`" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="8"><el-form-item label="省/州" required><el-select v-model="shipForm.chinapost_receiver.province" filterable allow-create default-first-option clearable placeholder="输入或选择省/州" style="width:100%;" :loading="regionLoading.receiver" @change="handleChinaPostRegionFieldChange('receiver', 'province')"><el-option v-for="item in getChinaPostRegionOptions('receiver', 'provinces')" :key="`receiver-province-${item.value}`" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="市" required><el-select v-model="shipForm.chinapost_receiver.city" filterable allow-create default-first-option clearable placeholder="输入或选择城市" style="width:100%;" :loading="regionLoading.receiver" @change="handleChinaPostRegionFieldChange('receiver', 'city')"><el-option v-for="item in getChinaPostRegionOptions('receiver', 'cities')" :key="`receiver-city-${item.value}`" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="区/县" required><el-select v-model="shipForm.chinapost_receiver.county" filterable allow-create default-first-option clearable placeholder="输入或选择区/县" style="width:100%;" :loading="regionLoading.receiver" @change="handleChinaPostRegionFieldChange('receiver', 'county')"><el-option v-for="item in getChinaPostRegionOptions('receiver', 'counties')" :key="`receiver-county-${item.value}`" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="16"><el-form-item label="收件人地址" required><el-input v-model="shipForm.chinapost_receiver.address" /></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="公司名称"><el-input v-model="shipForm.chinapost_receiver.company" /></el-form-item></el-col>
          </el-row>
        </div>

        <div v-show="chinaPostStep === 2" class="chinapost-section">
          <el-divider content-position="left">报关信息</el-divider>
          <el-row :gutter="12">
            <el-col :span="6"><el-form-item label="申报信息来源"><el-input value="企业申报(固定)" disabled /></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="通关标识"><el-input value="企业报关(固定)" disabled /></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="申报币制"><el-input value="USD(固定)" disabled /></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="是否有电池"><el-select v-model="shipForm.chinapost_form.battery_flag" style="width:100%;"><el-option v-for="item in chinaPostBatteryFlagOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="6"><el-form-item label="预缴增值税"><el-select v-model="shipForm.chinapost_form.prepayment_of_vat" clearable style="width:100%;"><el-option label="0 - IOSS" value="0" /><el-option label="1 - no-IOSS" value="1" /><el-option label="2 - other" value="2" /></el-select></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="不能投递策略"><el-select v-model="shipForm.chinapost_form.undelivery_option" style="width:100%;"><el-option v-for="item in chinaPostUndeliveryOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="退件地址"><el-input v-model="shipForm.chinapost_form.back_addr" /></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="备注"><el-input v-model="shipForm.chinapost_form.pickup_notes" /></el-form-item></el-col>
          </el-row>

          <el-divider content-position="left">内件信息</el-divider>
          <div class="chinapost-value-summary">内件总价值：USD {{ chinaPostItemsTotalValue }}</div>
          <el-table :data="shipForm.chinapost_items" size="mini" border>
            <el-table-column label="SKU编号" min-width="120"><template slot-scope="{ row }"><el-input v-model="row.cargo_no" size="mini" /></template></el-table-column>
            <el-table-column label="物品中文名" min-width="150"><template slot-scope="{ row }"><el-input v-model="row.cargo_name" size="mini" /></template></el-table-column>
            <el-table-column label="物品英文名" min-width="150"><template slot-scope="{ row }"><el-input v-model="row.cargo_name_en" size="mini" /></template></el-table-column>
            <el-table-column label="单位申报重量(g)" width="140"><template slot-scope="{ row }"><el-input-number v-model="row.cargo_weight" :min="0" :step="1" size="mini" style="width:100%;" /></template></el-table-column>
            <el-table-column label="单位申报价值" width="130"><template slot-scope="{ row }"><el-input-number v-model="row.cost" :min="0" :step="0.01" :precision="2" size="mini" style="width:100%;" /></template></el-table-column>
            <el-table-column label="数量" width="110"><template slot-scope="{ row }"><el-input-number v-model="row.cargo_quantity" :min="1" :step="1" size="mini" style="width:100%;" /></template></el-table-column>
            <el-table-column label="原产地" width="110"><template slot-scope="{ row }"><el-input v-model="row.cargo_origin_name" size="mini" /></template></el-table-column>
            <el-table-column label="计量单位" width="110"><template slot-scope="{ row }"><el-input v-model="row.unit" size="mini" /></template></el-table-column>
            <el-table-column label="操作" width="90" fixed="right"><template slot-scope="{ $index }"><el-button type="text" size="mini" @click="removeChinaPostItem($index)">删除</el-button></template></el-table-column>
          </el-table>
          <div class="sz56t-item-actions">
            <el-button size="mini" plain icon="el-icon-plus" @click="addChinaPostItem">添加内件</el-button>
          </div>
        </div>

        <div class="chinapost-wizard-footer">
          <el-button type="text" @click="clearChinaPostCurrentStep">清空</el-button>
          <el-button v-if="chinaPostStep > 0" round @click="goChinaPostStepPrev">上一步</el-button>
          <el-button v-if="chinaPostStep < 2" type="warning" round @click="goChinaPostStepNext">下一步</el-button>
          <el-button v-else type="warning" round :loading="shipping" @click="submitChinaPostWizard">完成</el-button>
        </div>
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
        <el-button
          v-if="showDbsFooterSubmit"
          type="success"
          :loading="shipping"
          @click="$emit('submit-ship')"
        >{{ dbsSubmitButtonText }}</el-button>
      </template>
    </div>

    <el-dialog
      :visible.sync="addressBookDialogVisible"
      :title="addressBookDialogTitle"
      width="760px"
      append-to-body
    >
      <div class="address-book-header">
        <span class="address-book-tip">数据库已保存 {{ currentAddressBookEntries.length }} 条记录，可跨浏览器复用。</span>
      </div>
      <el-table :data="currentAddressBookEntries" size="mini" border :loading="addressBookLoading" empty-text="暂无地址簿记录">
        <el-table-column label="名称" min-width="110">
          <template slot-scope="{ row }">{{ row.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="电话" min-width="120">
          <template slot-scope="{ row }">{{ row.mobile || row.phone || '-' }}</template>
        </el-table-column>
        <el-table-column label="国家/地区" width="110">
          <template slot-scope="{ row }">{{ row.nation || '-' }}</template>
        </el-table-column>
        <el-table-column label="省市区" min-width="160">
          <template slot-scope="{ row }">{{ formatAddressBookRegion(row) }}</template>
        </el-table-column>
        <el-table-column label="详细地址" min-width="220" show-overflow-tooltip>
          <template slot-scope="{ row }">{{ row.address || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template slot-scope="{ row }">
            <el-button type="text" size="mini" @click="applyAddressBookEntry(row)">使用</el-button>
            <el-button type="text" size="mini" @click="removeAddressBookEntry(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div slot="footer">
        <el-button @click="addressBookDialogVisible = false">关闭</el-button>
      </div>
    </el-dialog>
  </el-dialog>
</template>

<script>
import {
  deleteOrderAddressBook,
  fetchOrderAddressBookList,
  fetchOrderAddressBookRegionOptions,
  saveOrderAddressBook
} from '@/api/order'
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
  createDefaultChinaPostItem,
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

const createEmptyChinaPostRegionOptions = () => ({
  nations: [],
  provinces: [],
  cities: [],
  counties: [],
  postCodes: []
})

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
      addressBookDialogVisible: false,
      addressBookType: 'sender',
      addressBookLoading: false,
      addressBookStore: {
        sender: [],
        receiver: []
      },
      regionLoading: {
        sender: false,
        receiver: false
      },
      chinaPostRegionOptions: {
        sender: createEmptyChinaPostRegionOptions(),
        receiver: createEmptyChinaPostRegionOptions()
      },
      chinaPostStep: 0,
      chinaPostHotNations: [
        { label: '俄罗斯(RU)', value: 'RU' },
        { label: '美国(US)', value: 'US' },
        { label: '英国(GB)', value: 'GB' }
      ],
      chinaPostNationNameMap: {
        RU: '俄罗斯',
        US: '美国',
        GB: '英国',
        FR: '法国',
        DE: '德国',
        ES: '西班牙',
        IT: '意大利',
        CA: '加拿大',
        AU: '澳大利亚'
      },
      leiyiFormMode: 'simple',
      sz56tBatteryTypeOptions: SZ56T_BATTERY_TYPE_OPTIONS,
      sz56tCargoTypeOptions: SZ56T_CARGO_TYPE_OPTIONS,
      sz56tCountryOptions: SZ56T_COUNTRY_OPTIONS,
      sz56tCurrencyOptions: SZ56T_CURRENCY_OPTIONS,
      sz56tCustomsDeclarationOptions: SZ56T_CUSTOMS_DECLARATION_OPTIONS,
      sz56tDutyTypeOptions: SZ56T_DUTY_TYPE_OPTIONS,
      sz56tExportReasonOptions: SZ56T_EXPORT_REASON_OPTIONS,
      sz56tInvoiceUnitOptions: SZ56T_INVOICE_UNIT_OPTIONS,
      sz56tTaxTypeOptions: SZ56T_TAX_TYPE_OPTIONS,
      chinaPostTransferTypeOptions: [
        { label: 'HK - 航空', value: 'HK' },
        { label: 'SLL - 水陆路', value: 'SLL' }
      ],
      chinaPostBatteryFlagOptions: [
        { label: '0 - 无电池', value: '0' },
        { label: '1 - 有电池', value: '1' }
      ],
      chinaPostInsuranceFlagOptions: [
        { label: '1 - 基本', value: '1' },
        { label: '2 - 保价', value: '2' },
        { label: '3 - 保险', value: '3' }
      ],
      chinaPostUndeliveryOptions: [
        { label: '1 - 放弃', value: '1' },
        { label: '2 - 退回', value: '2' },
        { label: '3 - 改投指定地址', value: '3' }
      ],
      chinaPostBackWayOptions: [
        { label: '1 - 经济邮路', value: '1' },
        { label: '2 - 航空', value: '2' }
      ]
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
        if (typeof window === 'undefined') {
          return '1700px'
        }

        return `${Math.min(window.innerWidth - 24, 1700)}px`
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
    chinaPostNationHint() {
      const nation = String((this.shipForm.chinapost_receiver && this.shipForm.chinapost_receiver.nation) || '').trim().toUpperCase()
      if (!nation) {
        return '-'
      }

      const nationName = this.chinaPostNationNameMap[nation]
      return nationName ? `${nationName} ${nation}` : nation
    },
    chinaPostItemsTotalValue() {
      const items = Array.isArray(this.shipForm.chinapost_items) ? this.shipForm.chinapost_items : []
      const total = items.reduce((sum, item) => {
        const price = Number(item.cost || item.cargo_value || 0)
        const quantity = Number(item.cargo_quantity || 0)
        return sum + price * quantity
      }, 0)
      return total.toFixed(2)
    },
    addressBookDialogTitle() {
      return this.addressBookType === 'sender' ? '寄件人地址簿' : '收件人地址簿'
    },
    currentAddressBookEntries() {
      return this.addressBookStore[this.addressBookType] || []
    },
    showDbsFooterSubmit() {
      return this.shipForm.ship_provider !== 'chinapost'
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

      this.chinaPostStep = 0
      this.loadAddressBookStore()
      this.loadChinaPostRegionOptions('sender')
      this.loadChinaPostRegionOptions('receiver')
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
    async loadAddressBookStore(type) {
      const types = type ? [type] : ['sender', 'receiver']

      this.addressBookLoading = true
      try {
        await Promise.all(types.map(async currentType => {
          const response = await fetchOrderAddressBookList({ type: currentType })
          this.$set(this.addressBookStore, currentType, Array.isArray(response.data.items) ? response.data.items : [])
        }))
      } finally {
        this.addressBookLoading = false
      }
    },
    normalizeAddressBookEntry(type, source) {
      const entry = source && typeof source === 'object' ? source : {}
      return {
        id: entry.id || null,
        type,
        name: String(entry.name || '').trim(),
        company: String(entry.company || '').trim(),
        post_code: String(entry.post_code || '').trim(),
        phone: String(entry.phone || '').trim(),
        mobile: String(entry.mobile || '').trim(),
        email: String(entry.email || '').trim(),
        id_type: String(entry.id_type || '').trim(),
        id_no: String(entry.id_no || '').trim(),
        nation: String(entry.nation || '').trim(),
        province: String(entry.province || '').trim(),
        city: String(entry.city || '').trim(),
        county: String(entry.county || '').trim(),
        address: String(entry.address || '').trim(),
        gis: String(entry.gis || '').trim(),
        linker: String(entry.linker || '').trim(),
        updated_at: entry.updated_at || new Date().toISOString()
      }
    },
    getAddressBookSource(type) {
      return type === 'sender' ? this.shipForm.chinapost_sender : this.shipForm.chinapost_receiver
    },
    getChinaPostRegionOptions(type, key) {
      const options = this.chinaPostRegionOptions[type] || createEmptyChinaPostRegionOptions()
      return Array.isArray(options[key]) ? options[key] : []
    },
    ensureChinaPostRegionOption(type, key, value) {
      const normalizedValue = String(value || '').trim()
      if (!normalizedValue) {
        return
      }

      const options = this.getChinaPostRegionOptions(type, key)
      if (options.some(item => item.value === normalizedValue)) {
        return
      }

      const nextOptions = options.concat([{ label: normalizedValue, value: normalizedValue }])
      this.$set(this.chinaPostRegionOptions[type], key, nextOptions)
    },
    async loadChinaPostRegionOptions(type) {
      const source = this.getAddressBookSource(type) || {}
      this.$set(this.regionLoading, type, true)

      try {
        const response = await fetchOrderAddressBookRegionOptions({
          type,
          nation: source.nation || '',
          province: source.province || '',
          city: source.city || '',
          county: source.county || ''
        })

        const data = response.data || {}
        const nextOptions = {
          nations: Array.isArray(data.nations) ? data.nations : [],
          provinces: Array.isArray(data.provinces) ? data.provinces : [],
          cities: Array.isArray(data.cities) ? data.cities : [],
          counties: Array.isArray(data.counties) ? data.counties : [],
          postCodes: Array.isArray(data.post_codes) ? data.post_codes : []
        }

        this.$set(this.chinaPostRegionOptions, type, nextOptions)
        this.ensureChinaPostRegionOption(type, 'nations', source.nation)
        this.ensureChinaPostRegionOption(type, 'provinces', source.province)
        this.ensureChinaPostRegionOption(type, 'cities', source.city)
        this.ensureChinaPostRegionOption(type, 'counties', source.county)
        this.ensureChinaPostRegionOption(type, 'postCodes', source.post_code)
        this.syncChinaPostAutoPostCode(type)
      } finally {
        this.$set(this.regionLoading, type, false)
      }
    },
    async openAddressBook(type) {
      this.addressBookType = type
      await this.loadAddressBookStore(type)
      this.addressBookDialogVisible = true
    },
    async saveAddressBookEntry(type) {
      const source = this.getAddressBookSource(type)
      const entry = this.normalizeAddressBookEntry(type, source)

      if (!entry.name) {
        this.$message.warning(type === 'sender' ? '请先填写寄件人姓名' : '请先填写收件人姓名')
        return
      }

      if (!entry.address) {
        this.$message.warning(type === 'sender' ? '请先填写寄件人地址' : '请先填写收件人地址')
        return
      }

      const response = await saveOrderAddressBook(entry)
      const targetField = type === 'sender' ? 'chinapost_sender' : 'chinapost_receiver'
      this.$set(this.shipForm, targetField, {
        ...this.getAddressBookSource(type),
        ...this.normalizeAddressBookEntry(type, response.data.item || entry)
      })
      await this.loadAddressBookStore(type)
      await this.loadChinaPostRegionOptions(type)
      this.$message.success(type === 'sender' ? '寄件人已保存到地址簿' : '收件人已保存到地址簿')
    },
    applyAddressBookEntry(row) {
      const type = this.addressBookType
      const targetField = type === 'sender' ? 'chinapost_sender' : 'chinapost_receiver'
      this.$set(this.shipForm, targetField, {
        ...this.getAddressBookSource(type),
        ...this.normalizeAddressBookEntry(type, row)
      })
      this.addressBookDialogVisible = false
      this.loadChinaPostRegionOptions(type)
      this.$message.success(type === 'sender' ? '已填充寄件人信息' : '已填充收件人信息')
    },
    async removeAddressBookEntry(row) {
      const type = this.addressBookType
      await deleteOrderAddressBook({ id: row.id })
      await this.loadAddressBookStore(type)
      await this.loadChinaPostRegionOptions(type)
      this.$message.success('地址簿记录已删除')
    },
    formatAddressBookRegion(row) {
      const values = [row.province, row.city, row.county].filter(Boolean)
      return values.length ? values.join(' / ') : '-'
    },
    async handleChinaPostRegionFieldChange(type, field) {
      const source = this.getAddressBookSource(type)
      if (!source || typeof source !== 'object') {
        return
      }

      if (field === 'nation') {
        source.nation = String(source.nation || '').trim().toUpperCase()
        source.province = ''
        source.city = ''
        source.county = ''
        source.post_code = ''
      }

      if (field === 'province') {
        source.province = String(source.province || '').trim()
        source.city = ''
        source.county = ''
        source.post_code = ''
      }

      if (field === 'city') {
        source.city = String(source.city || '').trim()
        source.county = ''
        source.post_code = ''
      }

      if (field === 'county') {
        source.county = String(source.county || '').trim()
        source.post_code = ''
      }

      await this.loadChinaPostRegionOptions(type)
    },
    syncChinaPostAutoPostCode(type) {
      const source = this.getAddressBookSource(type)
      if (!source || typeof source !== 'object') {
        return
      }

      const options = this.getChinaPostRegionOptions(type, 'postCodes')
      if (!source.post_code && options.length === 1) {
        this.$set(source, 'post_code', options[0].value)
      }
    },
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
        this.chinaPostStep = 0
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
    addChinaPostItem() {
      if (!Array.isArray(this.shipForm.chinapost_items)) {
        this.$set(this.shipForm, 'chinapost_items', [])
      }

      this.shipForm.chinapost_items.push(createDefaultChinaPostItem())
    },
    useChinaPostHotNation(code) {
      const nation = String(code || '').trim().toUpperCase()
      if (!nation) {
        return
      }

      if (!this.shipForm.chinapost_receiver || typeof this.shipForm.chinapost_receiver !== 'object') {
        this.$set(this.shipForm, 'chinapost_receiver', {})
      }

      this.$set(this.shipForm.chinapost_receiver, 'nation', nation)
      this.handleChinaPostRegionFieldChange('receiver', 'nation')
    },
    goChinaPostStepNext() {
      this.chinaPostStep = Math.min(2, this.chinaPostStep + 1)
    },
    goChinaPostStepPrev() {
      this.chinaPostStep = Math.max(0, this.chinaPostStep - 1)
    },
    submitChinaPostWizard() {
      this.$emit('submit-ship')
    },
    clearChinaPostCurrentStep() {
      if (this.chinaPostStep === 0) {
        this.shipForm.chinapost_form = {
          ...this.shipForm.chinapost_form,
          logistics_order_no: '',
          created_time: '',
          batch_no: '',
          waybill_no: '',
          barcode: '',
          volume: '',
          length: 0,
          width: 0,
          height: 0
        }
        this.shipForm.weight = 100
        return
      }

      if (this.chinaPostStep === 1) {
        this.shipForm.chinapost_sender = {
          ...this.shipForm.chinapost_sender,
          name: '',
          phone: '',
          mobile: '',
          email: '',
          province: '',
          city: '',
          county: '',
          address: ''
        }
        this.shipForm.chinapost_receiver = {
          ...this.shipForm.chinapost_receiver,
          name: '',
          phone: '',
          mobile: '',
          email: '',
          province: '',
          city: '',
          county: '',
          address: ''
        }
        this.loadChinaPostRegionOptions('sender')
        this.loadChinaPostRegionOptions('receiver')
        return
      }

      this.shipForm.chinapost_items = [createDefaultChinaPostItem()]
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
    removeChinaPostItem(index) {
      if (!Array.isArray(this.shipForm.chinapost_items)) {
        return
      }

      if (this.shipForm.chinapost_items.length <= 1) {
        this.shipForm.chinapost_items.splice(0, 1, createDefaultChinaPostItem())
        return
      }

      this.shipForm.chinapost_items.splice(index, 1)
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

.chinapost-hot-country {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.chinapost-toolbar__tip {
  color: #909399;
  font-size: 12px;
  line-height: 1.6;
}

.chinapost-step-panel {
  margin: 14px 0 10px;
  padding: 14px 12px;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  background: #fafcff;
}

.chinapost-section {
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 12px 14px 8px;
  margin-bottom: 12px;
}

.chinapost-actions-line {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-bottom: 8px;
}

.chinapost-value-summary {
  margin-bottom: 8px;
  text-align: right;
  color: #f56c6c;
  font-size: 13px;
  font-weight: 600;
}

.chinapost-wizard-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 12px;
}

.address-book-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.address-book-tip {
  color: #909399;
  font-size: 12px;
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

  .chinapost-wizard-footer {
    flex-wrap: wrap;
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
