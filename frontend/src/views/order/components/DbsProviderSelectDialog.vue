<template>
  <el-dialog title="选择发货渠道" :visible.sync="dialogVisible" width="480px" top="20vh">
    <div class="dbs-provider-grid">
      <div
        class="dbs-provider-card"
        :class="{ 'is-active': selected === 'chinapost' }"
        @click="selected = 'chinapost'"
      >
        <div class="dbs-provider-card__name">中国邮政</div>
        <div class="dbs-provider-card__desc">E邮宝特惠 · 三步录入</div>
      </div>
      <div
        class="dbs-provider-card"
        :class="{ 'is-active': selected === 'leiyi' }"
        @click="selected = 'leiyi'"
      >
        <div class="dbs-provider-card__name">雷翼</div>
        <div class="dbs-provider-card__desc">sz56t · 精简/完整模式</div>
      </div>
    </div>
    <div slot="footer">
      <el-button @click="dialogVisible = false">取消</el-button>
      <el-button type="primary" :disabled="!selected" @click="confirm">确定</el-button>
    </div>
  </el-dialog>
</template>

<script>
export default {
  name: 'DbsProviderSelectDialog',
  props: {
    visible: { type: Boolean, default: false },
    defaultProvider: { type: String, default: 'chinapost' }
  },
  data() {
    return { selected: this.defaultProvider || 'chinapost' }
  },
  computed: {
    dialogVisible: {
      get() { return this.visible },
      set(v) { this.$emit('update:visible', v) }
    }
  },
  watch: {
    visible(v) {
      if (v) this.selected = this.defaultProvider || 'chinapost'
    }
  },
  methods: {
    confirm() {
      this.$emit('select', this.selected)
      this.dialogVisible = false
    }
  }
}
</script>

<style scoped>
.dbs-provider-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.dbs-provider-card {
  border: 2px solid #ebeef5;
  border-radius: 10px;
  padding: 24px 16px;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.dbs-provider-card:hover {
  border-color: #c0c4cc;
}

.dbs-provider-card.is-active {
  border-color: #e6a23c;
  box-shadow: 0 0 0 2px rgba(230, 162, 60, 0.18);
}

.dbs-provider-card__name {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 6px;
}

.dbs-provider-card__desc {
  font-size: 12px;
  color: #909399;
}
</style>
