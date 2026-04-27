<template>
  <el-dialog title="订单后台更新" :visible.sync="dialogVisible" width="700px">
    <el-form label-width="100px">
      <el-form-item label="后台备注">
        <el-input v-model="commentTemp.admin_remark" type="textarea" :rows="3" placeholder="请输入后台备注" />
      </el-form-item>
      <el-form-item label="后台状态">
        <el-select v-model="commentTemp.backend_status" clearable placeholder="请选择后台状态" style="width: 100%;">
          <el-option v-for="item in backendStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
        </el-select>
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
            <el-input v-model.number="commentTemp.purchase_amount" type="number" min="0" @input="$emit('recalc-eub-logistics-fee')" />
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
            <el-input v-model.number="commentTemp.logistics_fee" type="number" min="0" @input="$emit('mark-logistics-fee-manual-edit')" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="物流模板">
        <div class="readonly-text">{{ logisticsTemplateLabel }}</div>
        <div class="form-tip">物流模板由发货流程自动决定，这里不再手动选择。</div>
      </el-form-item>
      <el-row v-if="commentTemp.logistics_template === 'offline_epacket'" :gutter="12">
        <el-col :span="12">
          <el-form-item label="亚马逊比例%">
            <el-input v-model.number="commentTemp.eub_amazon_ratio" type="number" min="0" @input="$emit('recalc-eub-logistics-fee')" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="固定附加费">
            <el-input v-model.number="commentTemp.eub_base_fee" type="number" min="0" @input="$emit('recalc-eub-logistics-fee')" />
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
          <el-button size="mini" @click="$emit('reset-logistics-fee-to-calculated')">按计算值回填物流费</el-button>
        </el-col>
      </el-row>
      <el-form-item label="采购图片">
        <el-upload
          :action="uploadUrl"
          :headers="uploadHeaders"
          :show-file-list="false"
          :on-success="handlePurchaseImageUploadSuccess"
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
          :on-success="handleShippingImageUploadSuccess"
        >
          <el-button size="small" type="success">上传发货图片</el-button>
        </el-upload>
        <div v-if="commentTemp.shipping_image" style="margin-top: 8px;">
          <el-image :src="commentTemp.shipping_image" :preview-src-list="[commentTemp.shipping_image]" style="width: 100px; height: 100px; border: 1px solid #eee;" fit="cover" />
        </div>
      </el-form-item>
    </el-form>
    <div slot="footer">
      <el-button @click="dialogVisible = false">取消</el-button>
      <el-button type="primary" @click="$emit('save')">保存</el-button>
    </div>
  </el-dialog>
</template>

<script>
export default {
  name: 'OrderCommentDialog',
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    commentTemp: {
      type: Object,
      required: true
    },
    backendStatusOptions: {
      type: Array,
      default: () => []
    },
    uploadUrl: {
      type: String,
      required: true
    },
    uploadHeaders: {
      type: Object,
      default: () => ({})
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
    logisticsTemplateLabel() {
      if (this.commentTemp.logistics_template === 'offline_epacket') {
        return '线下-E邮宝'
      }

      if (this.commentTemp.logistics_template === 'offline_leiyi') {
        return '线下-雷翼/邮政'
      }

      if (this.commentTemp.logistics_template === 'online') {
        return '线上'
      }

      return this.commentTemp.logistics_template || '-'
    }
  },
  methods: {
    handlePurchaseImageUploadSuccess(response) {
      this.$emit('image-upload-success', response, 'purchase_image')
    },
    handleShippingImageUploadSuccess(response) {
      this.$emit('image-upload-success', response, 'shipping_image')
    }
  }
}
</script>

<style scoped>
.readonly-text {
  color: #606266;
  line-height: 32px;
}

.form-tip {
  color: #909399;
  font-size: 12px;
  line-height: 1.6;
}
</style>
