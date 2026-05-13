<template>
  <div class="app-container">
    <!-- 筛选区 -->
    <div class="filter-container">
      <el-select
        v-model="listQuery.shop_id"
        placeholder="选择店铺"
        clearable
        filterable
        class="filter-item"
        style="width: 200px;"
        @change="handleFilter"
      >
        <el-option v-for="s in shopOptions" :key="s.id" :label="s.name" :value="s.id" />
      </el-select>
      <el-input
        v-model="listQuery.keyword"
        placeholder="商品ID / 标题"
        style="width: 220px;"
        class="filter-item"
        @keyup.enter.native="handleFilter"
      />
      <el-input
        v-model="listQuery.category_id"
        placeholder="类目ID"
        style="width: 140px;"
        class="filter-item"
        @keyup.enter.native="handleFilter"
      />
      <el-select
        v-model="listQuery.status_type"
        placeholder="状态筛选"
        clearable
        class="filter-item"
        style="width: 140px;"
      >
        <el-option label="上架 (onSelling)" value="onSelling" />
        <el-option label="下架 (offline)" value="offline" />
        <el-option label="编辑中 (editing)" value="editing" />
      </el-select>
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索</el-button>
      <el-button class="filter-item" icon="el-icon-refresh" @click="handleRefresh">同步商品</el-button>
    </div>

    <el-alert
      v-if="syncTip"
      :title="syncTip"
      type="success"
      show-icon
      :closable="true"
      style="margin-bottom: 12px;"
      @close="syncTip = ''"
    />

    <!-- 表格 -->
    <el-table v-loading="listLoading" :data="list" row-key="ae_item_id" border fit highlight-current-row style="width: 100%">
      <el-table-column label="序号" type="index" :index="i => (listQuery.page - 1) * listQuery.limit + i + 1" align="center" width="60" />
      <el-table-column label="主图" align="center" width="80">
        <template slot-scope="{row}">
          <img
            v-if="resolveMainImage(row)"
            :key="`${row.ae_item_id || 'unknown'}-${resolveMainImage(row)}`"
            :src="resolveMainImage(row)"
            referrerpolicy="no-referrer"
            style="width:48px;height:48px;object-fit:cover;cursor:pointer;"
            @click="previewImg = resolveMainImage(row); previewVisible = true"
          >
          <span v-else>-</span>
        </template>
      </el-table-column>
      <el-table-column label="商品 ID" prop="ae_item_id" align="center" width="140" />
      <el-table-column label="类目" align="left" width="160" show-overflow-tooltip>
        <template slot-scope="{row}">{{ row.category_name || row.category_id || '-' }}</template>
      </el-table-column>
      <el-table-column label="标题" min-width="240">
        <template slot-scope="{row}">
          <div v-if="row.title_en" style="font-size:13px;line-height:1.4;">
            <span style="color:#606266;font-weight:600;">英文：</span>{{ row.title_en }}
          </div>
          <div v-if="row.title_ru" style="font-size:12px;color:#888;line-height:1.4;margin-top:2px;">
            <span style="color:#606266;font-weight:600;">俄文：</span>{{ row.title_ru }}
          </div>
          <span v-if="!row.title_en && !row.title_ru">-</span>
        </template>
      </el-table-column>
      <el-table-column label="价格" prop="price" align="center" width="90" />
      <el-table-column label="状态" align="center" width="90">
        <template slot-scope="{row}">
          <el-tag :type="statusTagType(row.status_type)" size="small">{{ statusLabel(row.status_type) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="店铺" align="center" width="140">
        <template slot-scope="{row}">{{ row.shop ? row.shop.name : '-' }}</template>
      </el-table-column>
      <el-table-column label="创建时间" prop="ae_created_at" align="center" width="160" />
    </el-table>

    <pagination
      v-show="total > 0"
      :total="total"
      :page.sync="listQuery.page"
      :limit.sync="listQuery.limit"
      @pagination="getList"
    />

    <!-- 图片大图预览 -->
    <el-dialog :visible.sync="previewVisible" width="500px" :show-close="true">
      <img v-if="previewImg" :src="previewImg" referrerpolicy="no-referrer" style="width:100%;display:block;">
    </el-dialog>
  </div>
</template>

<script>
import Pagination from '@/components/Pagination'
import { fetchShopList } from '@/api/shop'
import { getProductList, syncShopProducts } from '@/api/product'

export default {
  name: 'ProductListManage',
  components: { Pagination },
  data() {
    return {
      listLoading: false,
      syncTip: '',
      previewImg: '',
      previewVisible: false,
      list: [],
      total: 0,
      shopOptions: [],
      listQuery: {
        page: 1,
        limit: 20,
        shop_id: undefined,
        keyword: '',
        category_id: '',
        status_type: undefined
      }
    }
  },
  created() {
    this.loadShopOptions()
    this.getList()
  },
  methods: {
    loadShopOptions() {
      fetchShopList({ page: 1, limit: 200 }).then(res => {
        this.shopOptions = (res.data && res.data.items) || []
      }).catch(() => {})
    },
    getList() {
      this.listLoading = true
      const query = { ...this.listQuery }
      if (!query.keyword) delete query.keyword
      if (!query.category_id) delete query.category_id
      if (!query.shop_id) delete query.shop_id
      if (!query.status_type) delete query.status_type

      getProductList(query).then(res => {
        this.list = (res.data && res.data.items) || []
        this.total = (res.data && res.data.total) || 0
      }).finally(() => {
        this.listLoading = false
      }).finally(() => {
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
    },
    handleRefresh() {
      if (!this.listQuery.shop_id) {
        this.$message.warning('请先选择一个店铺再同步')
        return
      }
      syncShopProducts({ shop_id: this.listQuery.shop_id }).then(res => {
        this.syncTip = res.message || '同步任务已提交，完成后刷新列表'
      }).catch(err => {
        this.$message.error((err && err.message) || '同步任务提交失败')
      })
    },
    resolveMainImage(row) {
      if (!row) {
        return ''
      }

      if (row.main_image_url) {
        return row.main_image_url
      }

      const mediaSources = []
      if (Array.isArray(row.media)) mediaSources.push(row.media)
      if (Array.isArray(row.marketing_images)) mediaSources.push(row.marketing_images)

      for (const source of mediaSources) {
        for (const item of source) {
          if (!item) continue
          if (typeof item === 'string' && item) return item
          if (typeof item.url === 'string' && item.url) return item.url
          if (typeof item.image_url === 'string' && item.image_url) return item.image_url
          if (typeof item.imageUrl === 'string' && item.imageUrl) return item.imageUrl
        }
      }

      return ''
    },
    statusTagType(status) {
      const map = { onSelling: 'success', offline: 'danger', editing: 'warning' }
      return map[status] || 'info'
    },
    statusLabel(status) {
      const map = { onSelling: '上架', offline: '下架', editing: '编辑中' }
      return map[status] || status || '-'
    }
  }
}
</script>
