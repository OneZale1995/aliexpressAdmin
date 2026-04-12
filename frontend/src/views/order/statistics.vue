<template>
  <div class="app-container order-statistics-page" v-loading="loading">
    <div class="statistics-toolbar">
      <el-form inline size="small">
        <el-form-item label="店铺">
          <el-select v-model="filters.shop_id" clearable placeholder="全部店铺" style="width: 220px;">
            <el-option v-for="shop in shopOptions" :key="shop.id" :label="shop.name" :value="shop.id" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-refresh" @click="fetchData">刷新统计</el-button>
        </el-form-item>
      </el-form>
      <div class="statistics-tip">统计口径：已关闭订单不纳入统计，实发订单按 actual_ship_at 非空计算。</div>
    </div>

    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane label="日统计" name="daily">
        <div class="tab-toolbar">
          <el-date-picker v-model="filters.daily_date" type="date" value-format="yyyy-MM-dd" placeholder="选择日期" />
          <el-button type="primary" size="small" @click="fetchData">更新</el-button>
        </div>
        <div class="stat-section">
          <div class="section-title">当日总览</div>
          <div class="stat-grid">
            <div class="stat-card">
              <div class="stat-label">订单数</div>
              <div class="stat-value">{{ statistics.daily.totals.total_orders || 0 }}</div>
            </div>
            <div class="stat-card success">
              <div class="stat-label">销售额</div>
              <div class="stat-value">{{ formatMoney(statistics.daily.totals.total_sales) }}</div>
            </div>
            <div class="stat-card warning">
              <div class="stat-label">采购额</div>
              <div class="stat-value">{{ formatMoney(statistics.daily.totals.total_purchase_cost) }}</div>
            </div>
            <div class="stat-card info">
              <div class="stat-label">物流费</div>
              <div class="stat-value">{{ formatMoney(statistics.daily.totals.total_logistics_fee) }}</div>
            </div>
            <div class="stat-card danger">
              <div class="stat-label">利润</div>
              <div class="stat-value">{{ formatMoney(statistics.daily.totals.total_profit) }}</div>
            </div>
          </div>
        </div>
        <div class="stat-section">
          <div class="section-title">当日实发</div>
          <div class="stat-grid">
            <div class="stat-card">
              <div class="stat-label">实发订单</div>
              <div class="stat-value">{{ statistics.daily.shipped_totals.total_orders || 0 }}</div>
            </div>
            <div class="stat-card success">
              <div class="stat-label">实发销售额</div>
              <div class="stat-value">{{ formatMoney(statistics.daily.shipped_totals.total_sales) }}</div>
            </div>
            <div class="stat-card warning">
              <div class="stat-label">实发采购额</div>
              <div class="stat-value">{{ formatMoney(statistics.daily.shipped_totals.total_purchase_cost) }}</div>
            </div>
            <div class="stat-card info">
              <div class="stat-label">实发物流费</div>
              <div class="stat-value">{{ formatMoney(statistics.daily.shipped_totals.total_logistics_fee) }}</div>
            </div>
            <div class="stat-card danger">
              <div class="stat-label">实发利润</div>
              <div class="stat-value">{{ formatMoney(statistics.daily.shipped_totals.total_profit) }}</div>
            </div>
          </div>
        </div>
      </el-tab-pane>

      <el-tab-pane label="月统计" name="monthly">
        <div class="tab-toolbar">
          <el-date-picker v-model="filters.monthly_month" type="month" value-format="yyyy-MM" placeholder="选择月份" />
          <el-button type="primary" size="small" @click="fetchData">更新</el-button>
        </div>
        <div class="stat-section">
          <div class="section-title">当月总览</div>
          <div class="stat-grid">
            <div class="stat-card">
              <div class="stat-label">订单数</div>
              <div class="stat-value">{{ statistics.monthly.totals.total_orders || 0 }}</div>
            </div>
            <div class="stat-card success">
              <div class="stat-label">销售额</div>
              <div class="stat-value">{{ formatMoney(statistics.monthly.totals.total_sales) }}</div>
            </div>
            <div class="stat-card warning">
              <div class="stat-label">采购额</div>
              <div class="stat-value">{{ formatMoney(statistics.monthly.totals.total_purchase_cost) }}</div>
            </div>
            <div class="stat-card info">
              <div class="stat-label">物流费</div>
              <div class="stat-value">{{ formatMoney(statistics.monthly.totals.total_logistics_fee) }}</div>
            </div>
            <div class="stat-card danger">
              <div class="stat-label">利润</div>
              <div class="stat-value">{{ formatMoney(statistics.monthly.totals.total_profit) }}</div>
            </div>
          </div>
        </div>
        <div class="stat-section">
          <div class="section-title">当月实发</div>
          <div class="stat-grid">
            <div class="stat-card">
              <div class="stat-label">实发订单</div>
              <div class="stat-value">{{ statistics.monthly.shipped_totals.total_orders || 0 }}</div>
            </div>
            <div class="stat-card success">
              <div class="stat-label">实发销售额</div>
              <div class="stat-value">{{ formatMoney(statistics.monthly.shipped_totals.total_sales) }}</div>
            </div>
            <div class="stat-card warning">
              <div class="stat-label">实发采购额</div>
              <div class="stat-value">{{ formatMoney(statistics.monthly.shipped_totals.total_purchase_cost) }}</div>
            </div>
            <div class="stat-card info">
              <div class="stat-label">实发物流费</div>
              <div class="stat-value">{{ formatMoney(statistics.monthly.shipped_totals.total_logistics_fee) }}</div>
            </div>
            <div class="stat-card danger">
              <div class="stat-label">实发利润</div>
              <div class="stat-value">{{ formatMoney(statistics.monthly.shipped_totals.total_profit) }}</div>
            </div>
          </div>
        </div>
      </el-tab-pane>

      <el-tab-pane label="单量统计" name="order_count">
        <div class="tab-toolbar">
          <el-date-picker
            v-model="orderCountRange"
            type="daterange"
            value-format="yyyy-MM-dd"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
          />
          <el-button type="primary" size="small" @click="fetchData">更新</el-button>
        </div>
        <div class="stat-grid single-row">
          <div class="stat-card">
            <div class="stat-label">统计区间总单量</div>
            <div class="stat-value">{{ statistics.order_count.total_orders || 0 }}</div>
          </div>
        </div>
        <div class="table-grid">
          <el-card shadow="never">
            <div slot="header">按日期统计</div>
            <el-table :data="statistics.order_count.daily_stats" size="small" border>
              <el-table-column prop="date" label="日期" min-width="140" />
              <el-table-column prop="order_count" label="单量" min-width="100" />
            </el-table>
          </el-card>
          <el-card shadow="never">
            <div slot="header">按店铺统计</div>
            <el-table :data="statistics.order_count.shop_stats" size="small" border>
              <el-table-column prop="shop_name" label="店铺" min-width="180" />
              <el-table-column prop="order_count" label="单量" min-width="100" />
            </el-table>
          </el-card>
        </div>
      </el-tab-pane>

      <el-tab-pane label="实发单量统计" name="shipped_count">
        <div class="tab-toolbar">
          <el-date-picker
            v-model="shippedCountRange"
            type="daterange"
            value-format="yyyy-MM-dd"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
          />
          <el-button type="primary" size="small" @click="fetchData">更新</el-button>
        </div>
        <div class="stat-grid single-row">
          <div class="stat-card">
            <div class="stat-label">统计区间实发单量</div>
            <div class="stat-value">{{ statistics.shipped_count.total_shipped || 0 }}</div>
          </div>
        </div>
        <div class="table-grid">
          <el-card shadow="never">
            <div slot="header">按日期统计</div>
            <el-table :data="statistics.shipped_count.daily_stats" size="small" border>
              <el-table-column prop="date" label="日期" min-width="140" />
              <el-table-column prop="shipped_count" label="实发单量" min-width="100" />
            </el-table>
          </el-card>
          <el-card shadow="never">
            <div slot="header">按店铺统计</div>
            <el-table :data="statistics.shipped_count.shop_stats" size="small" border>
              <el-table-column prop="shop_name" label="店铺" min-width="180" />
              <el-table-column prop="shipped_count" label="实发单量" min-width="100" />
            </el-table>
          </el-card>
        </div>
      </el-tab-pane>

      <el-tab-pane label="利润统计" name="profit">
        <div class="tab-toolbar">
          <el-date-picker
            v-model="profitRange"
            type="daterange"
            value-format="yyyy-MM-dd"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
          />
          <el-button type="primary" size="small" @click="fetchData">更新</el-button>
        </div>
        <div class="stat-grid">
          <div class="stat-card">
            <div class="stat-label">实发订单</div>
            <div class="stat-value">{{ statistics.profit.totals.total_orders || 0 }}</div>
          </div>
          <div class="stat-card success">
            <div class="stat-label">销售额</div>
            <div class="stat-value">{{ formatMoney(statistics.profit.totals.total_sales) }}</div>
          </div>
          <div class="stat-card warning">
            <div class="stat-label">采购额</div>
            <div class="stat-value">{{ formatMoney(statistics.profit.totals.total_purchase_cost) }}</div>
          </div>
          <div class="stat-card info">
            <div class="stat-label">物流费</div>
            <div class="stat-value">{{ formatMoney(statistics.profit.totals.total_logistics_fee) }}</div>
          </div>
          <div class="stat-card danger">
            <div class="stat-label">利润</div>
            <div class="stat-value">{{ formatMoney(statistics.profit.totals.total_profit) }}</div>
          </div>
        </div>
        <div class="table-grid">
          <el-card shadow="never">
            <div slot="header">按日期统计</div>
            <el-table :data="statistics.profit.daily_stats" size="small" border>
              <el-table-column prop="date" label="日期" min-width="140" />
              <el-table-column prop="total_orders" label="实发订单" min-width="100" />
              <el-table-column label="销售额" min-width="120">
                <template slot-scope="scope">{{ formatMoney(scope.row.total_sales) }}</template>
              </el-table-column>
              <el-table-column label="采购额" min-width="120">
                <template slot-scope="scope">{{ formatMoney(scope.row.total_purchase_cost) }}</template>
              </el-table-column>
              <el-table-column label="物流费" min-width="120">
                <template slot-scope="scope">{{ formatMoney(scope.row.total_logistics_fee) }}</template>
              </el-table-column>
              <el-table-column label="利润" min-width="120">
                <template slot-scope="scope">{{ formatMoney(scope.row.total_profit) }}</template>
              </el-table-column>
            </el-table>
          </el-card>
          <el-card shadow="never">
            <div slot="header">按店铺统计</div>
            <el-table :data="statistics.profit.shop_stats" size="small" border>
              <el-table-column prop="shop_name" label="店铺" min-width="180" />
              <el-table-column prop="total_orders" label="实发订单" min-width="100" />
              <el-table-column label="销售额" min-width="120">
                <template slot-scope="scope">{{ formatMoney(scope.row.total_sales) }}</template>
              </el-table-column>
              <el-table-column label="采购额" min-width="120">
                <template slot-scope="scope">{{ formatMoney(scope.row.total_purchase_cost) }}</template>
              </el-table-column>
              <el-table-column label="物流费" min-width="120">
                <template slot-scope="scope">{{ formatMoney(scope.row.total_logistics_fee) }}</template>
              </el-table-column>
              <el-table-column label="利润" min-width="120">
                <template slot-scope="scope">{{ formatMoney(scope.row.total_profit) }}</template>
              </el-table-column>
            </el-table>
          </el-card>
        </div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script>
import { fetchOrderStatistics } from '@/api/order'
import { fetchShopList } from '@/api/shop'

function createEmptyTotals() {
  return {
    total_orders: 0,
    total_sales: 0,
    total_purchase_cost: 0,
    total_logistics_fee: 0,
    total_lianlian_fee: 0,
    total_platform_fee: 0,
    total_profit: 0
  }
}

function createEmptyStatistics() {
  return {
    daily: { totals: createEmptyTotals(), shipped_totals: createEmptyTotals() },
    monthly: { totals: createEmptyTotals(), shipped_totals: createEmptyTotals() },
    order_count: { total_orders: 0, daily_stats: [], shop_stats: [] },
    shipped_count: { total_shipped: 0, daily_stats: [], shop_stats: [] },
    profit: { totals: createEmptyTotals(), daily_stats: [], shop_stats: [] }
  }
}

export default {
  name: 'OrderStatistics',
  data() {
    const today = this.formatDate(new Date())
    const month = today.slice(0, 7)
    const start = this.formatDate(new Date(Date.now() - 30 * 24 * 60 * 60 * 1000))

    return {
      loading: false,
      activeTab: 'daily',
      shopOptions: [],
      statistics: createEmptyStatistics(),
      filters: {
        shop_id: '',
        daily_date: today,
        monthly_month: month,
        order_count_start_date: start,
        order_count_end_date: today,
        shipped_start_date: start,
        shipped_end_date: today,
        profit_start_date: start,
        profit_end_date: today
      }
    }
  },
  computed: {
    orderCountRange: {
      get() {
        return [this.filters.order_count_start_date, this.filters.order_count_end_date]
      },
      set(value) {
        this.filters.order_count_start_date = value && value[0] ? value[0] : ''
        this.filters.order_count_end_date = value && value[1] ? value[1] : ''
      }
    },
    shippedCountRange: {
      get() {
        return [this.filters.shipped_start_date, this.filters.shipped_end_date]
      },
      set(value) {
        this.filters.shipped_start_date = value && value[0] ? value[0] : ''
        this.filters.shipped_end_date = value && value[1] ? value[1] : ''
      }
    },
    profitRange: {
      get() {
        return [this.filters.profit_start_date, this.filters.profit_end_date]
      },
      set(value) {
        this.filters.profit_start_date = value && value[0] ? value[0] : ''
        this.filters.profit_end_date = value && value[1] ? value[1] : ''
      }
    }
  },
  created() {
    this.loadShops()
    this.fetchData()
  },
  methods: {
    loadShops() {
      fetchShopList({ page: 1, limit: 200 }).then(res => {
        this.shopOptions = res.data.items || []
      })
    },
    fetchData() {
      this.loading = true
      fetchOrderStatistics(this.filters).then(res => {
        this.statistics = Object.assign(createEmptyStatistics(), res.data || {})
      }).finally(() => {
        this.loading = false
      })
    },
    formatMoney(value) {
      return Number(value || 0).toFixed(2)
    },
    formatDate(date) {
      const year = date.getFullYear()
      const month = String(date.getMonth() + 1).padStart(2, '0')
      const day = String(date.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }
  }
}
</script>

<style scoped>
.order-statistics-page {
  background: #f5f7fa;
}

.statistics-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
  padding: 16px 18px;
  background: #fff;
  border-radius: 8px;
}

.statistics-tip {
  color: #909399;
  font-size: 12px;
}

.tab-toolbar {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-bottom: 16px;
}

.stat-section {
  margin-bottom: 20px;
}

.section-title {
  margin-bottom: 12px;
  font-size: 14px;
  font-weight: 600;
  color: #303133;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.stat-grid.single-row {
  grid-template-columns: minmax(200px, 320px);
}

.stat-card {
  padding: 18px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ffffff 0%, #f6f8fc 100%);
  border: 1px solid #ebeef5;
}

.stat-card.success {
  background: linear-gradient(135deg, #f0f9eb 0%, #ffffff 100%);
}

.stat-card.warning {
  background: linear-gradient(135deg, #fdf6ec 0%, #ffffff 100%);
}

.stat-card.info {
  background: linear-gradient(135deg, #ecf5ff 0%, #ffffff 100%);
}

.stat-card.danger {
  background: linear-gradient(135deg, #fef0f0 0%, #ffffff 100%);
}

.stat-label {
  color: #909399;
  font-size: 13px;
  margin-bottom: 8px;
}

.stat-value {
  color: #303133;
  font-size: 24px;
  font-weight: 700;
}

.table-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
  gap: 16px;
}

@media (max-width: 768px) {
  .statistics-toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .tab-toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .table-grid {
    grid-template-columns: 1fr;
  }
}
</style>