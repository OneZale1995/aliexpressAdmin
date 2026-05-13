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
          <el-form-item label="采购额">
            <el-input v-model.number="commentTemp.purchase_amount" type="number" min="0" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="重量(kg)">
            <el-input-number v-model="commentTemp.weight" :step="0.01" style="width: 100%;" @change="calcLogisticsFee" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item label="物流费">
            <el-input v-model.number="commentTemp.logistics_fee" type="number" min="0" />
            <span class="text-muted" style="font-size:11px;">公式: 重量x35+15，可手动修改</span>
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="采购图片">
        <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-start;">
          <div v-for="(url, index) in purchaseImages" :key="index" class="image-item">
            <el-image :src="url" :preview-src-list="purchaseImages" style="width: 100px; height: 100px; border: 1px solid #eee;" fit="cover" />
            <i class="el-icon-close image-delete" @click="deleteImage('purchase_image', url)" />
          </div>
          <el-upload
            :action="uploadUrl"
            :headers="uploadHeaders"
            :show-file-list="false"
            :on-success="handlePurchaseUploadSuccess"
            style="display: inline-block;"
          >
            <div class="upload-trigger"><i class="el-icon-plus" /></div>
          </el-upload>
        </div>
      </el-form-item>
      <el-form-item label="发货图片">
        <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-start;">
          <div v-for="(url, index) in shippingImages" :key="index" class="image-item">
            <el-image :src="url" :preview-src-list="shippingImages" style="width: 100px; height: 100px; border: 1px solid #eee;" fit="cover" />
            <i class="el-icon-close image-delete" @click="deleteImage('shipping_image', url)" />
          </div>
          <el-upload
            :action="uploadUrl"
            :headers="uploadHeaders"
            :show-file-list="false"
            :on-success="handleShippingUploadSuccess"
            style="display: inline-block;"
          >
            <div class="upload-trigger"><i class="el-icon-plus" /></div>
          </el-upload>
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
import { deleteOrderImage } from '@/api/order'

function ensureArray(val) {
  if (Array.isArray(val)) return val
  if (typeof val === 'string' && val) {
    try { const d = JSON.parse(val); if (Array.isArray(d)) return d } catch (_) { /* ignore */ }
    return [val]
  }
  return []
}

export default {
  name: 'OrderCommentDialog',
  props: {
    visible: { type: Boolean, default: false },
    commentTemp: { type: Object, required: true },
    backendStatusOptions: { type: Array, default: () => [] },
    uploadUrl: { type: String, required: true },
    uploadHeaders: { type: Object, default: () => ({}) }
  },
  data() {
    return {
      purchaseImages: [],
      shippingImages: []
    }
  },
  computed: {
    dialogVisible: {
      get() { return this.visible },
      set(value) { this.$emit('update:visible', value) }
    }
  },
  watch: {
    visible(v) {
      if (v) {
        this.purchaseImages = ensureArray(this.commentTemp.purchase_image)
        this.shippingImages = ensureArray(this.commentTemp.shipping_image)
      }
    }
  },
  methods: {
    calcLogisticsFee() {
      const w = Number(this.commentTemp.weight)
      if (w > 0) {
        this.commentTemp.logistics_fee = Number((w * 35 + 15).toFixed(2))
      }
    },
    handlePurchaseUploadSuccess(response) {
      const url = (response && response.data && response.data.url) || (response && response.url) || ''
      if (url) {
        this.purchaseImages.push(url)
        this.syncToTemp()
      }
    },
    handleShippingUploadSuccess(response) {
      const url = (response && response.data && response.data.url) || (response && response.url) || ''
      if (url) {
        this.shippingImages.push(url)
        this.syncToTemp()
      }
    },
    syncToTemp() {
      this.commentTemp.purchase_image = this.purchaseImages.length ? [...this.purchaseImages] : null
      this.commentTemp.shipping_image = this.shippingImages.length ? [...this.shippingImages] : null
    },
    deleteImage(field, url) {
      const images = field === 'purchase_image' ? this.purchaseImages : this.shippingImages
      const idx = images.indexOf(url)
      if (idx > -1) images.splice(idx, 1)

      deleteOrderImage({ id: this.commentTemp.id, type: field, url }).catch(() => {})
      this.syncToTemp()
    }
  }
}
</script>

<style scoped>
.image-item {
  position: relative;
  display: inline-block;
}
.image-delete {
  position: absolute;
  top: -6px;
  right: -6px;
  width: 18px;
  height: 18px;
  line-height: 18px;
  text-align: center;
  background: #f56c6c;
  color: #fff;
  border-radius: 50%;
  font-size: 12px;
  cursor: pointer;
}
.upload-trigger {
  width: 100px;
  height: 100px;
  border: 1px dashed #d9d9d9;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #999;
  font-size: 28px;
}
.upload-trigger:hover {
  border-color: #409EFF;
  color: #409EFF;
}
</style>
