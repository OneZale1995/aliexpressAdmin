<template>
  <el-dialog title="中国邮政(E邮宝) 发货" :visible.sync="dialogVisible" :width="dialogWidth" top="5vh">
    <el-alert
      title="DBS 这里只记录本地实际发货。完成后请回订单列表点击'发送到速卖通'"
      type="info"
      :closable="false"
      show-icon
    />

    <el-form label-width="25%" size="medium">

      <div class="chinapost-toolbar">
        <div class="chinapost-hot-country">
          <span class="chinapost-toolbar__tip">寄达国/地区固定为：俄罗斯联邦-RU</span>
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
          <el-col :span="8"><el-form-item label="寄达国/地区" required><el-input value="俄罗斯联邦-RU" disabled /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="寄达国/地区简称"><el-input value="俄罗斯 RUSSIAN" disabled /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="物流订单号" required><el-input v-model="shipForm.chinapost_form.logistics_order_no" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="订单接入时间" required><el-date-picker v-model="shipForm.chinapost_form.created_time" type="datetime" value-format="yyyy-MM-dd HH:mm:ss" format="yyyy-MM-dd HH:mm:ss" placeholder="选择日期时间" style="width:100%;" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="自定义编号"><el-input v-model="shipForm.chinapost_form.barcode" /></el-form-item></el-col>
        </el-row>

        <el-divider content-position="left">包裹信息</el-divider>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="长(厘米)"><el-input-number v-model="shipForm.chinapost_form.length" :min="0" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="宽(厘米)"><el-input-number v-model="shipForm.chinapost_form.width" :min="0" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="高(厘米)"><el-input-number v-model="shipForm.chinapost_form.height" :min="0" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label-width="20%" label="邮件重量(g)" required><el-input-number v-model="shipForm.weight" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="已有运单号"><el-input v-model="shipForm.chinapost_form.waybill_no" placeholder="选填，手写单补录" /></el-form-item></el-col>
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
          <el-col :span="8"><el-form-item label="寄件人邮编"><el-input v-model="shipForm.chinapost_sender.post_code" placeholder="输入邮编" /></el-form-item></el-col>
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
          <el-col :span="8"><el-form-item label="所在国家" required><el-input value="RU" disabled /></el-form-item></el-col>
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
          <el-table-column label="单位申报价值" width="130"><template slot-scope="{ row }"><el-input v-model="row.cost" size="mini" placeholder="请填写" /></template></el-table-column>
          <el-table-column label="数量" width="110"><template slot-scope="{ row }"><el-input-number v-model="row.cargo_quantity" :min="1" :step="1" size="mini" style="width:100%;" /></template></el-table-column>
          <el-table-column label="原产地" width="110"><template slot-scope="{ row }"><el-input v-model="row.cargo_origin_name" size="mini" /></template></el-table-column>
          <el-table-column label="计量单位" width="110"><template slot-scope="{ row }"><el-input v-model="row.unit" size="mini" /></template></el-table-column>
          <el-table-column label="操作" width="90"><template slot-scope="{ $index }"><el-button type="text" size="mini" @click="removeChinaPostItem($index)">删除</el-button></template></el-table-column>
        </el-table>
        <div class="sz56t-item-actions">
          <el-button size="mini" plain icon="el-icon-plus" @click="addChinaPostItem">添加内件</el-button>
        </div>
      </div>

      <div class="chinapost-wizard-footer">
        <el-button type="text" @click="clearChinaPostCurrentStep">清空</el-button>
        <el-button v-if="chinaPostStep > 0" round @click="goChinaPostStepPrev">上一步</el-button>
        <el-button v-if="chinaPostStep < 2" type="warning" round @click="goChinaPostStepNext">下一步</el-button>
      </div>
    </el-form>

    <div slot="footer">
      <el-button @click="dialogVisible = false">取消</el-button>
      <el-button type="warning" :loading="shipping" @click="$emit('submit-ship')">提交</el-button>
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
        <el-table-column label="名称" min-width="110"><template slot-scope="{ row }">{{ row.name || '-' }}</template></el-table-column>
        <el-table-column label="电话" min-width="120"><template slot-scope="{ row }">{{ row.mobile || row.phone || '-' }}</template></el-table-column>
        <el-table-column label="国家/地区" width="110"><template slot-scope="{ row }">{{ row.nation || '-' }}</template></el-table-column>
        <el-table-column label="省市区" min-width="160"><template slot-scope="{ row }">{{ formatAddressBookRegion(row) }}</template></el-table-column>
        <el-table-column label="详细地址" min-width="220" show-overflow-tooltip><template slot-scope="{ row }">{{ row.address || '-' }}</template></el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template slot-scope="{ row }">
            <el-button type="text" size="mini" @click="applyAddressBookEntry(row)">使用</el-button>
            <el-button type="text" size="mini" @click="removeAddressBookEntry(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div slot="footer"><el-button @click="addressBookDialogVisible = false">关闭</el-button></div>
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
import { createDefaultChinaPostItem } from '../constants'

const createEmptyChinaPostRegionOptions = () => ({
  nations: [],
  provinces: [],
  cities: [],
  counties: [],
  postCodes: []
})

export default {
  name: 'ChinaPostShipDialog',
  props: {
    visible: { type: Boolean, default: false },
    shipForm: { type: Object, required: true },
    shipping: { type: Boolean, default: false }
  },
  data() {
    return {
      chinaPostStep: 0,
      addressBookDialogVisible: false,
      addressBookType: 'sender',
      addressBookLoading: false,
      addressBookStore: { sender: [], receiver: [] },
      regionLoading: { sender: false, receiver: false },
      chinaPostRegionOptions: {
        sender: createEmptyChinaPostRegionOptions(),
        receiver: createEmptyChinaPostRegionOptions()
      },
      chinaPostBatteryFlagOptions: [
        { label: '0 - 无电池', value: '0' },
        { label: '1 - 有电池', value: '1' }
      ],
      chinaPostUndeliveryOptions: [
        { label: '1 - 放弃', value: '1' },
        { label: '2 - 退回', value: '2' },
        { label: '3 - 改投指定地址', value: '3' }
      ]
    }
  },
  computed: {
    dialogVisible: {
      get() { return this.visible },
      set(v) { this.$emit('update:visible', v) }
    },
    dialogWidth() {
      if (typeof window === 'undefined') return '1700px'
      return `${Math.min(window.innerWidth - 24, 1700)}px`
    },
    chinaPostItemsTotalValue() {
      const items = Array.isArray(this.shipForm.chinapost_items) ? this.shipForm.chinapost_items : []
      return items.reduce((sum, item) => {
        return sum + Number(item.cost || item.cargo_value || 0) * Number(item.cargo_quantity || 0)
      }, 0).toFixed(2)
    },
    addressBookDialogTitle() {
      return this.addressBookType === 'sender' ? '寄件人地址簿' : '收件人地址簿'
    },
    currentAddressBookEntries() {
      return this.addressBookStore[this.addressBookType] || []
    }
  },
  watch: {
    visible(v) {
      if (!v) return
      this.chinaPostStep = 0
      this.loadAddressBookStore()
      this.ensureReceiverNationRU()
      this.loadChinaPostRegionOptions('sender')
      this.loadChinaPostRegionOptions('receiver')
      this.$emit('load-chinapost-preview')
    }
  },
  methods: {
    ensureReceiverNationRU() {
      if (!this.shipForm.chinapost_receiver || typeof this.shipForm.chinapost_receiver !== 'object') {
        this.$set(this.shipForm, 'chinapost_receiver', {})
      }
      this.$set(this.shipForm.chinapost_receiver, 'nation', 'RU')
    },
    async loadAddressBookStore(type) {
      const types = type ? [type] : ['sender', 'receiver']
      this.addressBookLoading = true
      try {
        await Promise.all(types.map(async t => {
          const response = await fetchOrderAddressBookList({ type: t })
          this.$set(this.addressBookStore, t, Array.isArray(response.data.items) ? response.data.items : [])
        }))
      } finally {
        this.addressBookLoading = false
      }
    },
    normalizeAddressBookEntry(type, source) {
      const entry = source && typeof source === 'object' ? source : {}
      return {
        id: entry.id || null, type,
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
      const v = String(value || '').trim()
      if (!v) return
      const options = this.getChinaPostRegionOptions(type, key)
      if (options.some(item => item.value === v)) return
      this.$set(this.chinaPostRegionOptions[type], key, options.concat([{ label: v, value: v }]))
    },
    async loadChinaPostRegionOptions(type) {
      const source = this.getAddressBookSource(type) || {}
      this.$set(this.regionLoading, type, true)
      try {
        const response = await fetchOrderAddressBookRegionOptions({
          type, nation: source.nation || '', province: source.province || '',
          city: source.city || '', county: source.county || ''
        })
        const data = response.data || {}
        this.$set(this.chinaPostRegionOptions, type, {
          nations: Array.isArray(data.nations) ? data.nations : [],
          provinces: Array.isArray(data.provinces) ? data.provinces : [],
          cities: Array.isArray(data.cities) ? data.cities : [],
          counties: Array.isArray(data.counties) ? data.counties : [],
          postCodes: Array.isArray(data.post_codes) ? data.post_codes : []
        })
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
    syncChinaPostAutoPostCode(type) {
      const source = this.getAddressBookSource(type)
      if (!source || typeof source !== 'object') return
      const options = this.getChinaPostRegionOptions(type, 'postCodes')
      if (!source.post_code && options.length === 1) {
        this.$set(source, 'post_code', options[0].value)
      }
    },
    async handleChinaPostRegionFieldChange(type, field) {
      const source = this.getAddressBookSource(type)
      if (!source || typeof source !== 'object') return
      if (field === 'nation') {
        source.nation = String(source.nation || '').trim().toUpperCase()
        source.province = ''; source.city = ''; source.county = ''; source.post_code = ''
      }
      if (field === 'province') {
        source.province = String(source.province || '').trim()
        source.city = ''; source.county = ''; source.post_code = ''
      }
      if (field === 'city') {
        source.city = String(source.city || '').trim()
        source.county = ''; source.post_code = ''
      }
      if (field === 'county') {
        source.county = String(source.county || '').trim()
        source.post_code = ''
      }
      await this.loadChinaPostRegionOptions(type)
    },
    async openAddressBook(type) {
      this.addressBookType = type
      await this.loadAddressBookStore(type)
      this.addressBookDialogVisible = true
    },
    async saveAddressBookEntry(type) {
      const source = this.getAddressBookSource(type)
      const entry = this.normalizeAddressBookEntry(type, source)
      if (!entry.name) { this.$message.warning(type === 'sender' ? '请先填写寄件人姓名' : '请先填写收件人姓名'); return }
      if (!entry.address) { this.$message.warning(type === 'sender' ? '请先填写寄件人地址' : '请先填写收件人地址'); return }
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
      return [row.province, row.city, row.county].filter(Boolean).join(' / ') || '-'
    },
    addChinaPostItem() {
      if (!Array.isArray(this.shipForm.chinapost_items)) {
        this.$set(this.shipForm, 'chinapost_items', [])
      }
      this.shipForm.chinapost_items.push(createDefaultChinaPostItem())
    },
    removeChinaPostItem(index) {
      if (!Array.isArray(this.shipForm.chinapost_items)) return
      if (this.shipForm.chinapost_items.length <= 1) {
        this.shipForm.chinapost_items.splice(0, 1, createDefaultChinaPostItem())
        return
      }
      this.shipForm.chinapost_items.splice(index, 1)
    },
    goChinaPostStepNext() { this.chinaPostStep = Math.min(2, this.chinaPostStep + 1) },
    goChinaPostStepPrev() { this.chinaPostStep = Math.max(0, this.chinaPostStep - 1) },
    clearChinaPostCurrentStep() {
      if (this.chinaPostStep === 0) {
        this.shipForm.chinapost_form = {
          ...this.shipForm.chinapost_form,
          logistics_order_no: '', created_time: '', batch_no: '', waybill_no: '',
          barcode: '', volume: '', length: 0, width: 0, height: 0
        }
        this.shipForm.weight = 100
        return
      }
      if (this.chinaPostStep === 1) {
        this.shipForm.chinapost_sender = {
          ...this.shipForm.chinapost_sender,
          name: '', phone: '', mobile: '', email: '', province: '', city: '', county: '', address: ''
        }
        this.shipForm.chinapost_receiver = {
          ...this.shipForm.chinapost_receiver,
          name: '', phone: '', mobile: '', email: '', province: '', city: '', county: '', address: ''
        }
        this.loadChinaPostRegionOptions('sender')
        this.loadChinaPostRegionOptions('receiver')
        return
      }
      this.shipForm.chinapost_items = [createDefaultChinaPostItem()]
    }
  }
}
</script>

<style scoped>
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

.sz56t-item-actions {
  margin-top: 12px;
  display: flex;
  justify-content: flex-end;
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

@media (max-width: 900px) {
  .chinapost-toolbar {
    align-items: flex-start;
    flex-direction: column;
  }

  .chinapost-wizard-footer {
    flex-wrap: wrap;
  }
}
</style>
