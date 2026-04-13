<template>
  <el-dialog title="同步订单" :visible.sync="dialogVisible" width="460px">
    <el-form label-width="100px">
      <el-form-item label="指定店铺">
        <el-select v-model="syncForm.shop_id" placeholder="不选则同步所有店铺" clearable style="width: 100%;">
          <el-option v-for="shop in shopOptions" :key="shop.id" :label="shop.name" :value="shop.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="更新时间范围">
        <el-date-picker
          v-model="currentSyncDateRange"
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
      <el-button @click="dialogVisible = false">取消</el-button>
      <el-button type="primary" :loading="syncing" @click="$emit('submit')">开始同步</el-button>
    </div>
  </el-dialog>
</template>

<script>
export default {
  name: 'OrderSyncDialog',
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    syncForm: {
      type: Object,
      required: true
    },
    syncDateRange: {
      type: Array,
      default: () => []
    },
    syncing: {
      type: Boolean,
      default: false
    },
    shopOptions: {
      type: Array,
      default: () => []
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
    currentSyncDateRange: {
      get() {
        return this.syncDateRange
      },
      set(value) {
        this.$emit('update:syncDateRange', value)
      }
    }
  }
}
</script>
