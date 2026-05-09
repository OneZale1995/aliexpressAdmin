<template>
  <el-dialog title="雷翼(sz56t) 发货" :visible.sync="dialogVisible" :width="dialogWidth" top="5vh">
    <el-alert
      title="DBS 这里只记录本地实际发货。完成后请回订单列表点击'发送到速卖通'。"
      type="info"
      :closable="false"
      show-icon
    />

    <el-form label-width="25%" size="small" class="sz56t-form">
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
              <el-select v-model="shipForm.product_id" filterable clearable placeholder="请选择雷翼运输方式" :loading="sz56tProductLoading" style="width:100%;">
                <el-option v-for="item in sz56tProductOptions" :key="item.product_id" :label="item.label" :value="item.product_id" />
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
              <el-select v-model="shipForm.sz56t_form.country" filterable clearable default-first-option placeholder="请选择目的地" style="width:100%;">
                <el-option v-for="item in sz56tCountryOptions" :key="item.value || 'simple-country-empty'" :label="item.label" :value="item.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8"><el-form-item label="收件人名" required><el-input v-model="shipForm.sz56t_form.consignee_name" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="收件州/省" required><el-input v-model="shipForm.sz56t_form.consignee_state" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="收件城市" required><el-input v-model="shipForm.sz56t_form.consignee_city" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="收件邮编" required><el-input v-model="shipForm.sz56t_form.consignee_postcode" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="收件电话" required><el-input v-model="shipForm.sz56t_form.consignee_telephone" placeholder="必填，电话" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="24"><el-form-item label="收件地址" required><el-input v-model="shipForm.sz56t_form.consignee_address" type="textarea" :rows="2" /></el-form-item></el-col>
        </el-row>

        <el-divider content-position="left">申报信息</el-divider>
        <el-table :data="shipForm.sz56t_items" size="mini" border class="sz56t-simple-items-table">
          <el-table-column label="中文品名" min-width="150"><template slot-scope="{ row }"><el-select v-model="row.sku" filterable allow-create default-first-option size="mini" placeholder="选择或输入" style="width:100%;" @change="handleCustomsNameCnChange(row)"><el-option v-for="item in customsProducts" :key="'cn-'+item.id" :label="item.name_cn" :value="item.name_cn" /></el-select></template></el-table-column>
          <el-table-column min-width="180"><template slot="header"><span class="sz56t-required-header">* 英文品名</span></template><template slot-scope="{ row }"><el-select v-model="row.invoice_title" filterable allow-create default-first-option size="mini" placeholder="选择或输入" style="width:100%;" @change="handleCustomsNameEnChange(row)"><el-option v-for="item in customsProducts" :key="'en-'+item.id" :label="item.name_en" :value="item.name_en" /></el-select></template></el-table-column>
          <el-table-column label="配货" min-width="140"><template slot-scope="{ row }"><el-input v-model="row.sku_code" size="mini" placeholder="配货选填" /></template></el-table-column>
          <el-table-column width="160"><template slot="header"><span class="sz56t-required-header">* 单个商品重量(克)</span></template><template slot-scope="{ row }"><el-input-number v-model="row.invoice_weight" :min="1" :step="1" size="mini" style="width:100%;" /></template></el-table-column>
          <el-table-column width="110"><template slot="header"><span class="sz56t-required-header">* 产品数量</span></template><template slot-scope="{ row }"><el-input-number v-model="row.invoice_pcs" :min="1" :step="1" size="mini" style="width:100%;" /></template></el-table-column>
          <el-table-column width="140"><template slot="header"><span class="sz56t-required-header">* 总金额</span></template><template slot-scope="{ row }"><el-input v-model="row.invoice_amount" size="mini" placeholder="请填写金额" /></template></el-table-column>
          <el-table-column label="出口海关编码" min-width="150"><template slot-scope="{ row }"><el-input v-model="row.hs_code" size="mini" placeholder="选填" /></template></el-table-column>
          <el-table-column label="申报币种" width="120"><template slot-scope="{ row }"><el-select v-model="row.invoice_currency" size="mini" style="width:100%;"><el-option v-for="item in sz56tCurrencyOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></template></el-table-column>
          <el-table-column label="操作" width="90"><template slot-scope="{ $index }"><el-button type="text" size="mini" @click="removeSz56tItem($index)">删除</el-button></template></el-table-column>
        </el-table>
        <div class="sz56t-item-actions"><el-button size="mini" plain icon="el-icon-plus" @click="addSz56tItem">新增申报项</el-button></div>
      </template>

      <template v-else>
        <!-- full mode part 1: basic + order settings -->
        <el-row :gutter="12">
          <el-col :span="8">
            <el-form-item label="运输方式" required>
              <el-select v-model="shipForm.product_id" filterable clearable placeholder="请选择雷翼运输方式" :loading="sz56tProductLoading" style="width:100%;">
                <el-option v-for="item in sz56tProductOptions" :key="item.product_id" :label="item.label" :value="item.product_id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8"><el-form-item label="订单号"><el-input v-model="shipForm.sz56t_form.order_customerinvoicecode" placeholder="不填则使用平台订单号" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="包裹类型"><el-select v-model="shipForm.sz56t_form.cargo_type" style="width:100%;"><el-option v-for="item in sz56tCargoTypeOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="销售地址"><el-input v-model="shipForm.sz56t_form.order_transactionurl" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="图片地址"><el-input v-model="shipForm.sz56t_form.product_imagepath" placeholder="多张图片可用分号分隔" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="电池类型"><el-select v-model="shipForm.sz56t_form.battery_type" filterable clearable style="width:100%;"><el-option v-for="item in sz56tBatteryTypeOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="总重量(克)" required><el-input-number v-model="shipForm.weight" :min="1" :max="50000" :step="10" style="width:100%;" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="件数"><el-input-number v-model="shipForm.sz56t_form.order_piece" :min="1" :max="999" :step="1" style="width:100%;" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="发票编号"><el-input v-model="shipForm.sz56t_form.invoice_no" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12" class="sz56t-official-top-row">
          <el-col :span="16">
            <el-row :gutter="12">
              <el-col :span="8"><el-form-item label="退件服务"><el-radio-group v-model="shipForm.sz56t_form.order_returnsign"><el-radio label="N">不退回</el-radio><el-radio label="Y">退回</el-radio></el-radio-group></el-form-item></el-col>
              <el-col :span="8"><el-form-item label="报关"><el-select v-model="shipForm.sz56t_form.customs_declaration" style="width:100%;"><el-option v-for="item in sz56tCustomsDeclarationOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
              <el-col :span="8"><el-form-item label="保险"><el-input v-model="shipForm.sz56t_form.order_insurance" /></el-form-item></el-col>
            </el-row>
            <el-row :gutter="12">
              <el-col :span="8"><el-form-item label="其他金额"><el-input v-model="shipForm.sz56t_form.order_cargoamount" placeholder="用于 DHL/白关等场景" /></el-form-item></el-col>
              <el-col :span="8"><el-form-item label="手续费"><el-input v-model="shipForm.sz56t_form.order_handlingamount" /></el-form-item></el-col>
              <el-col :span="8"><el-form-item label="发件人参考号"><el-input v-model="shipForm.sz56t_form.shipper_reference" /></el-form-item></el-col>
            </el-row>
          </el-col>
          <el-col :span="8"><el-form-item label="自定义化"><el-input v-model="shipForm.sz56t_form.order_customnote" type="textarea" :rows="4" class="sz56t-customnote" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="生产商销售供应商"><el-input v-model="shipForm.sz56t_form.production_sales_suppliers_name" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="社会信用代码"><el-input v-model="shipForm.sz56t_form.production_sales_suppliers_credit_code" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="买家ID"><el-input v-model="shipForm.sz56t_form.buyerid" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="电商平台名称"><el-input v-model="shipForm.sz56t_form.ecommerce_platform_name" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="电商平台代码"><el-input v-model="shipForm.sz56t_form.ecommerce_platform_code" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="店铺代码"><el-input v-model="shipForm.sz56t_form.store_code" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="店铺名称"><el-input v-model="shipForm.sz56t_form.store_name" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="发件人类型"><el-input v-model="shipForm.sz56t_form.shipper_tradetype" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="收件人类型"><el-input v-model="shipForm.sz56t_form.consignee_tradetype" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="5"><el-form-item label="关税类型"><el-select v-model="shipForm.sz56t_form.duty_type" clearable style="width:100%;"><el-option v-for="item in sz56tDutyTypeOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
          <el-col :span="5"><el-form-item label="关税支付账号"><el-input v-model="shipForm.sz56t_form.duty_account" /></el-form-item></el-col>
          <el-col :span="5"><el-form-item label="关税国家"><el-select v-model="shipForm.sz56t_form.thirdPartyCountryCode" filterable clearable default-first-option placeholder="请选择关税国家" style="width:100%;"><el-option v-for="item in sz56tCountryOptions" :key="item.value || 'duty-country-empty'" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
          <el-col :span="4"><el-form-item label="关税邮编"><el-input v-model="shipForm.sz56t_form.thirdPartyPostCode" /></el-form-item></el-col>
          <el-col :span="5"><el-form-item label="关税公司"><el-input v-model="shipForm.sz56t_form.thirdpartycompany" /></el-form-item></el-col>
        </el-row>
        <el-divider content-position="left">选择收件人</el-divider>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="目的地" required><el-select v-model="shipForm.sz56t_form.country" filterable clearable default-first-option placeholder="请选择目的地" style="width:100%;"><el-option v-for="item in sz56tCountryOptions" :key="item.value || 'country-empty'" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="收件人名" required><el-input v-model="shipForm.sz56t_form.consignee_name" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="收件公司"><el-input v-model="shipForm.sz56t_form.consignee_companyname" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="收件州/省" required><el-input v-model="shipForm.sz56t_form.consignee_state" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="收件城市" required><el-input v-model="shipForm.sz56t_form.consignee_city" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="收件邮编" required><el-input v-model="shipForm.sz56t_form.consignee_postcode" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="12"><el-form-item label="收件地址" required><el-input v-model="shipForm.sz56t_form.consignee_address" type="textarea" :rows="2" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="收件电话"><el-input v-model="shipForm.sz56t_form.consignee_telephone" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="收件手机"><el-input v-model="shipForm.sz56t_form.consignee_mobile" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="收件邮箱"><el-input v-model="shipForm.sz56t_form.consignee_email" /></el-form-item></el-col>
          <el-col :span="4"><el-form-item label="街道号"><el-input v-model="shipForm.sz56t_form.consignee_streetno" /></el-form-item></el-col>
          <el-col :span="4"><el-form-item label="门牌号"><el-input v-model="shipForm.sz56t_form.consignee_doorno" /></el-form-item></el-col>
          <el-col :span="4"><el-form-item label="收件人区"><el-input v-model="shipForm.sz56t_form.consignee_suburb" /></el-form-item></el-col>
          <el-col :span="4"><el-form-item label="短地址"><el-input v-model="shipForm.sz56t_form.consignee_shortaddress" /></el-form-item></el-col>
        </el-row>
        <div class="sz56t-form-tip">联系电话或手机号至少填写一个</div>
        <el-row :gutter="12">
          <el-col :span="6"><el-form-item label="税号"><el-input v-model="shipForm.sz56t_form.consignee_taxno" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="税号类别"><el-select v-model="shipForm.sz56t_form.consignee_taxnotype" clearable style="width:100%;"><el-option v-for="item in sz56tTaxTypeOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="税号地区"><el-select v-model="shipForm.sz56t_form.consignee_taxnocountry" filterable clearable default-first-option placeholder="请选择国家/地区" style="width:100%;"><el-option v-for="item in sz56tCountryOptions" :key="item.value || 'consignee-tax-country-empty'" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="6"><el-form-item label="护照号"><el-input v-model="shipForm.sz56t_form.consignee_passportno" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="护照序列"><el-input v-model="shipForm.sz56t_form.consignee_passportserialnumber" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="护照签发日"><el-date-picker v-model="shipForm.sz56t_form.consignee_passportissuedate" type="date" value-format="yyyy-MM-dd" placeholder="请选择日期" style="width:100%;" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="生日"><el-date-picker v-model="shipForm.sz56t_form.consignee_datebirth" type="date" value-format="yyyy-MM-dd" placeholder="请选择日期" style="width:100%;" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="12"><el-form-item label="护照签发机构"><el-input v-model="shipForm.sz56t_form.consignee_passportissuedby" /></el-form-item></el-col>
          <el-col :span="12"><el-form-item label="出口原因"><el-select v-model="shipForm.sz56t_form.export_reason" clearable style="width:100%;"><el-option v-for="item in sz56tExportReasonOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
        </el-row>

        <el-divider content-position="left">发件人信息</el-divider>
        <el-row :gutter="12">
          <el-col :span="6"><el-form-item label="发件人姓名"><el-input v-model="shipForm.sz56t_form.shipper_name" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="发件公司"><el-input v-model="shipForm.sz56t_form.shipper_companyname" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="发件国家/地区"><el-select v-model="shipForm.sz56t_form.shipper_country" filterable clearable default-first-option placeholder="请选择国家/地区" style="width:100%;"><el-option v-for="item in sz56tCountryOptions" :key="item.value || 'shipper-country-empty'" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="发件电话"><el-input v-model="shipForm.sz56t_form.shipper_telephone" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="6"><el-form-item label="发件州/省"><el-input v-model="shipForm.sz56t_form.shipper_state" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="发件城市"><el-input v-model="shipForm.sz56t_form.shipper_city" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="发件邮编"><el-input v-model="shipForm.sz56t_form.shipper_postcode" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="发件人区"><el-input v-model="shipForm.sz56t_form.shipper_suburb" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="地址1"><el-input v-model="shipForm.sz56t_form.shipper_address1" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="地址2"><el-input v-model="shipForm.sz56t_form.shipper_address2" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="地址3"><el-input v-model="shipForm.sz56t_form.shipper_address3" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="6"><el-form-item label="发件邮箱"><el-input v-model="shipForm.sz56t_form.shipper_email" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="门牌号"><el-input v-model="shipForm.sz56t_form.shipper_doorno" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="护照号"><el-input v-model="shipForm.sz56t_form.shipper_passportno" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="发件人税号"><el-input v-model="shipForm.sz56t_form.shipper_taxno" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="6"><el-form-item label="税号类别"><el-select v-model="shipForm.sz56t_form.shipper_taxnotype" clearable style="width:100%;"><el-option v-for="item in sz56tTaxTypeOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="税号国家/地区"><el-select v-model="shipForm.sz56t_form.shipper_taxnocountry" filterable clearable default-first-option placeholder="请选择国家/地区" style="width:100%;"><el-option v-for="item in sz56tCountryOptions" :key="item.value || 'shipper-tax-country-empty'" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
        </el-row>

        <el-divider content-position="left">进口商信息</el-divider>
        <el-row :gutter="12">
          <el-col :span="6"><el-form-item label="进口商代码"><el-input v-model="shipForm.sz56t_form.import_code" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="进口商名称"><el-input v-model="shipForm.sz56t_form.import_name" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="进口商公司"><el-input v-model="shipForm.sz56t_form.import_companyname" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="国家/地区"><el-select v-model="shipForm.sz56t_form.import_country" filterable clearable default-first-option placeholder="请选择国家/地区" style="width:100%;"><el-option v-for="item in sz56tCountryOptions" :key="item.value || 'import-country-empty'" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="6"><el-form-item label="城市"><el-input v-model="shipForm.sz56t_form.import_city" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="州/省"><el-input v-model="shipForm.sz56t_form.import_state" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="邮编"><el-input v-model="shipForm.sz56t_form.import_postcode" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="电话"><el-input v-model="shipForm.sz56t_form.import_telephone" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="6"><el-form-item label="邮箱"><el-input v-model="shipForm.sz56t_form.import_email" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="税号"><el-input v-model="shipForm.sz56t_form.import_taxno" /></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="税号类别"><el-select v-model="shipForm.sz56t_form.import_taxtype" clearable style="width:100%;"><el-option v-for="item in sz56tTaxTypeOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
          <el-col :span="6"><el-form-item label="税号国家/地区"><el-select v-model="shipForm.sz56t_form.import_taxcountry" filterable clearable default-first-option placeholder="请选择国家/地区" style="width:100%;"><el-option v-for="item in sz56tCountryOptions" :key="item.value || 'import-tax-country-empty'" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="地址1"><el-input v-model="shipForm.sz56t_form.import_address" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="地址2"><el-input v-model="shipForm.sz56t_form.import_address2" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="地址3"><el-input v-model="shipForm.sz56t_form.import_address3" /></el-form-item></el-col>
        </el-row>

        <el-divider content-position="left">材积信息</el-divider>
        <el-table :data="shipForm.sz56t_form.orderVolumeParam" size="mini" border class="sz56t-volume-table">
          <el-table-column label="长(cm)" width="140"><template slot-scope="{ row }"><el-input-number v-model="row.volume_length" :min="1" :max="200" :step="1" size="mini" style="width:100%;" /></template></el-table-column>
          <el-table-column label="宽(cm)" width="140"><template slot-scope="{ row }"><el-input-number v-model="row.volume_width" :min="1" :max="200" :step="1" size="mini" style="width:100%;" /></template></el-table-column>
          <el-table-column label="高(cm)" width="140"><template slot-scope="{ row }"><el-input-number v-model="row.volume_height" :min="1" :max="200" :step="1" size="mini" style="width:100%;" /></template></el-table-column>
          <el-table-column label="实重(克)" width="160"><template slot-scope="{ row }"><el-input-number v-model="row.volume_weight" :min="1" :max="50000" :step="10" size="mini" style="width:100%;" /></template></el-table-column>
          <el-table-column label="操作" width="90" fixed="right"><template slot-scope="{ $index }"><el-button type="text" size="mini" @click="removeSz56tVolume($index)">删除</el-button></template></el-table-column>
        </el-table>
        <div class="sz56t-item-actions"><el-button size="mini" plain icon="el-icon-plus" @click="addSz56tVolume">新增材积行</el-button></div>

        <el-divider content-position="left">申报信息</el-divider>
        <el-table :data="shipForm.sz56t_items" size="mini" border class="sz56t-items-table">
          <el-table-column type="expand" width="48">
            <template slot-scope="{ row }">
              <div class="sz56t-expand-grid">
                <el-form-item label="销售地址" label-width="110px"><el-input v-model="row.transaction_url" size="mini" /></el-form-item>
                <el-form-item label="图片地址" label-width="110px"><el-input v-model="row.invoice_imgurl" size="mini" /></el-form-item>
                <el-form-item label="申报单位" label-width="110px"><el-select v-model="row.invoiceunit_code" size="mini" style="width:100%;"><el-option v-for="item in sz56tInvoiceUnitOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item>
                <el-form-item label="品牌" label-width="110px"><el-input v-model="row.invoice_brand" size="mini" /></el-form-item>
                <el-form-item label="规格" label-width="110px"><el-input v-model="row.invoice_rule" size="mini" /></el-form-item>
                <el-form-item label="税则号" label-width="110px"><el-input v-model="row.invoice_taxno" size="mini" /></el-form-item>
                <el-form-item label="材质" label-width="110px"><el-input v-model="row.invoice_material" size="mini" /></el-form-item>
                <el-form-item label="用途" label-width="110px"><el-input v-model="row.invoice_purpose" size="mini" /></el-form-item>
                <el-form-item label="出口单价" label-width="110px"><el-input-number v-model="row.invoice_export_unitprice" :min="0" :step="0.01" :precision="2" size="mini" style="width:100%;" /></el-form-item>
                <el-form-item label="出口币种" label-width="110px"><el-select v-model="row.invoice_export_currency" size="mini" style="width:100%;"><el-option v-for="item in sz56tCurrencyOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item>
                <el-form-item label="供应商" label-width="110px"><el-input v-model="row.invoice_production_sales_suppliers_name" size="mini" /></el-form-item>
                <el-form-item label="信用代码" label-width="110px"><el-input v-model="row.invoice_production_sales_suppliers_credit_code" size="mini" /></el-form-item>
                <el-form-item label="进口海关编码" label-width="110px"><el-input v-model="row.import_hs_code" size="mini" /></el-form-item>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="中文品名" min-width="160"><template slot-scope="{ row }"><el-select v-model="row.sku" filterable allow-create default-first-option size="mini" placeholder="选择或输入" style="width:100%;" @change="handleCustomsNameCnChange(row)"><el-option v-for="item in customsProducts" :key="'cn2-'+item.id" :label="item.name_cn" :value="item.name_cn" /></el-select></template></el-table-column>
          <el-table-column label="英文品名" min-width="180"><template slot-scope="{ row }"><el-select v-model="row.invoice_title" filterable allow-create default-first-option size="mini" placeholder="选择或输入" style="width:100%;" @change="handleCustomsNameEnChange(row)"><el-option v-for="item in customsProducts" :key="'en2-'+item.id" :label="item.name_en" :value="item.name_en" /></el-select></template></el-table-column>
          <el-table-column label="配货信息" min-width="140"><template slot-scope="{ row }"><el-input v-model="row.sku_code" size="mini" /></template></el-table-column>
          <el-table-column label="海关编码" min-width="130"><template slot-scope="{ row }"><el-input v-model="row.hs_code" size="mini" /></template></el-table-column>
          <el-table-column label="数量" width="90"><template slot-scope="{ row }"><el-input-number v-model="row.invoice_pcs" :min="1" :step="1" size="mini" /></template></el-table-column>
          <el-table-column label="申报金额" width="120"><template slot-scope="{ row }"><el-input v-model="row.invoice_amount" size="mini" placeholder="请填写" /></template></el-table-column>
          <el-table-column label="单件重(克)" width="130"><template slot-scope="{ row }"><el-input-number v-model="row.invoice_weight" :min="1" :step="1" size="mini" /></template></el-table-column>
          <el-table-column label="币种" width="100"><template slot-scope="{ row }"><el-select v-model="row.invoice_currency" size="mini" style="width:100%;"><el-option v-for="item in sz56tCurrencyOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></template></el-table-column>
          <el-table-column label="原产国" width="170"><template slot-scope="{ row }"><el-select v-model="row.origin_country" filterable clearable default-first-option size="mini" placeholder="原产国" style="width:100%;"><el-option v-for="item in sz56tCountryOptions" :key="item.value || 'origin-country-empty'" :label="item.label" :value="item.value" /></el-select></template></el-table-column>
          <el-table-column label="操作" width="90" fixed="right"><template slot-scope="{ $index }"><el-button type="text" size="mini" @click="removeSz56tItem($index)">删除</el-button></template></el-table-column>
        </el-table>
        <div class="sz56t-item-actions"><el-button size="mini" plain icon="el-icon-plus" @click="addSz56tItem">新增申报项</el-button></div>
      </template>
    </el-form>

    <div slot="footer">
      <el-button @click="dialogVisible = false">取消</el-button>
      <el-button
        v-if="canCancelSz56tWaybill"
        type="danger"
        plain
        :loading="shipping"
        @click="$emit('cancel-waybill')"
      >取消订单</el-button>
      <el-button
        type="success"
        :loading="shipping"
        @click="$emit('submit-ship')"
      >确定</el-button>
    </div>
  </el-dialog>
</template>

<script>
import {
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
import { fetchCustomsProductList } from '@/api/system'

export default {
  name: 'LeiyiShipDialog',
  props: {
    visible: { type: Boolean, default: false },
    shipForm: { type: Object, required: true },
    shipping: { type: Boolean, default: false },
    canCancelSz56tWaybill: { type: Boolean, default: false },
    sz56tProductOptions: { type: Array, default: () => [] },
    sz56tProductLoading: { type: Boolean, default: false }
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
      sz56tTaxTypeOptions: SZ56T_TAX_TYPE_OPTIONS,
      customsProducts: []
    }
  },
  computed: {
    dialogVisible: {
      get() { return this.visible },
      set(v) { this.$emit('update:visible', v) }
    },
    dialogWidth() {
      if (typeof window === 'undefined') return '1760px'
      return `${Math.min(window.innerWidth - 24, 1760)}px`
    }
  },
  watch: {
    visible(v) {
      if (!v) return
      this.leiyiFormMode = 'simple'
      this.loadCustomsProducts()
      this.randomizeSz56tItemAmounts()
      this.$emit('load-sz56t-products')
    }
  },
  methods: {
    async loadCustomsProducts() {
      try {
        const res = await fetchCustomsProductList()
        this.customsProducts = res.data.items || []
      } catch (e) {
        this.customsProducts = []
      }
    },
    randomizeSz56tItemAmounts() {
      const items = this.shipForm.sz56t_items
      if (!Array.isArray(items)) return
      items.forEach(item => {
        this.$set(item, 'invoice_amount', (Math.random() * 9 + 1).toFixed(2))
      })
    },
    addSz56tItem() {
      if (!Array.isArray(this.shipForm.sz56t_items)) {
        this.$set(this.shipForm, 'sz56t_items', [])
      }
      this.shipForm.sz56t_items.push(createDefaultSz56tItem())
    },
    removeSz56tItem(index) {
      if (!Array.isArray(this.shipForm.sz56t_items)) return
      if (this.shipForm.sz56t_items.length <= 1) {
        this.shipForm.sz56t_items.splice(0, 1, createDefaultSz56tItem())
        return
      }
      this.shipForm.sz56t_items.splice(index, 1)
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
    removeSz56tVolume(index) {
      if (!this.shipForm.sz56t_form || !Array.isArray(this.shipForm.sz56t_form.orderVolumeParam)) return
      this.shipForm.sz56t_form.orderVolumeParam.splice(index, 1)
    },
    handleCustomsNameCnChange(row) {
      const product = this.customsProducts.find(p => p.name_cn === row.sku)
      if (product) {
        this.$set(row, 'invoice_title', product.name_en)
      }
    },
    handleCustomsNameEnChange(row) {
      const product = this.customsProducts.find(p => p.name_en === row.invoice_title)
      if (product) {
        this.$set(row, 'sku', product.name_cn)
      }
    }
  }
}
</script>

<style scoped>
.sz56t-items-table { margin-top: 8px; }
.sz56t-simple-items-table { margin-top: 8px; }
.sz56t-volume-table { margin-top: 8px; }

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

.sz56t-expand-grid :deep(.el-form-item) { margin-bottom: 8px; }
.sz56t-form :deep(.el-form-item) { margin-bottom: 14px; }
.sz56t-official-top-row :deep(.el-form-item) { margin-bottom: 12px; }
.sz56t-form :deep(.el-divider--horizontal) { margin: 18px 0; }
.sz56t-form :deep(.el-textarea__inner) { min-height: 64px !important; }
.sz56t-customnote :deep(.el-textarea__inner) { min-height: 96px !important; }

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

@media (max-width: 900px) {
  .sz56t-expand-grid { grid-template-columns: 1fr; }
  .sz56t-form :deep(.el-col-5),
  .sz56t-form :deep(.el-col-4),
  .sz56t-form :deep(.el-col-6),
  .sz56t-form :deep(.el-col-8),
  .sz56t-form :deep(.el-col-16) { width: 100%; }
  .sz56t-form-tip { margin-left: 0; }
  .sz56t-form-mode-switch { align-items: flex-start; flex-direction: column; }
}

@media (max-width: 1280px) {
  .sz56t-expand-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .sz56t-form :deep(.el-col-5),
  .sz56t-form :deep(.el-col-4),
  .sz56t-form :deep(.el-col-6) { width: 50%; }
  .sz56t-form :deep(.el-col-8),
  .sz56t-form :deep(.el-col-12),
  .sz56t-form :deep(.el-col-16) { width: 100%; }
}
</style>
