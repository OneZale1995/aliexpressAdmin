 <template>
  <div class="app-container order-page">
    <!-- Tab 状态栏 -->
    <div class="status-tabs">
      <span
        v-for="tab in backendStatusTabs"
        :key="tab.key"
        :class="['status-tab', { active: listQuery.backend_status === tab.key }]"
        @click="switchBackendStatusTab(tab.key)"
      >
        {{ tab.label }}（{{ backendStatusCounts[tab.countKey] || 0 }}）
      </span>
    </div>

    <div class="status-tabs backend-tabs">
      <span
        v-for="tab in statusTabs"
        :key="tab.key"
        :class="['status-tab', { active: listQuery.display_status === tab.key }]"
        @click="switchTab(tab.key)"
      >
        {{ tab.label }}（{{ statusCounts[tab.countKey] || 0 }}）
      </span>
    </div>

    <!-- 筛选搜索 -->
    <el-collapse v-model="showFilter">
      <el-collapse-item name="filter">
        <template slot="title"><i class="el-icon-search" /> 筛选搜索</template>
        <el-form :model="listQuery" inline class="filter-container" style="padding: 10px 0;">
          <el-form-item label="店铺">
            <el-select v-model="listQuery.shop_id" placeholder="选择店铺" clearable style="width: 180px;">
              <el-option v-for="s in shopOptions" :key="s.id" :label="s.name" :value="s.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="店铺关键词">
            <el-input v-model="listQuery.shop_keyword" placeholder="店铺名/邮箱" style="width: 180px;" />
          </el-form-item>
          <el-form-item label="国内单号">
            <el-input v-model="listQuery.ae_order_id" placeholder="国内单号" style="width: 180px;" />
          </el-form-item>
          <el-form-item label="国际单号">
            <el-input v-model="listQuery.tracking_number" placeholder="国际单号/运单号" style="width: 180px;" />
          </el-form-item>
          <el-form-item label="下单日期">
            <el-date-picker
              v-model="dateRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="yyyy-MM-dd"
              style="width: 240px;"
            />
          </el-form-item>
          <el-form-item label="收件人">
            <el-input v-model="listQuery.receiver_name" placeholder="收件人姓名" style="width: 140px;" />
          </el-form-item>
          <el-form-item label="电话">
            <el-input v-model="listQuery.receiver_phone" placeholder="收件人电话" style="width: 140px;" />
          </el-form-item>
          <el-form-item label="订单号">
            <el-input v-model="listQuery.ae_order_id" placeholder="订单号" style="width: 180px;" />
          </el-form-item>
          <el-form-item label="备注">
            <el-input v-model="listQuery.seller_comment" placeholder="备注关键词" style="width: 140px;" />
          </el-form-item>
          <el-form-item label="后台备注">
            <el-input v-model="listQuery.admin_remark" placeholder="后台备注关键词" style="width: 160px;" />
          </el-form-item>
          <el-form-item label="买家姓名">
            <el-input v-model="listQuery.buyer_name" placeholder="买家姓名" style="width: 140px;" />
          </el-form-item>
          <el-form-item label="买家电话">
            <el-input v-model="listQuery.buyer_phone" placeholder="买家电话" style="width: 140px;" />
          </el-form-item>
          <el-form-item label="地址关键词">
            <el-input v-model="listQuery.address_keyword" placeholder="地址关键词" style="width: 180px;" />
          </el-form-item>
          <el-form-item label="采购图">
            <el-select v-model="listQuery.has_purchase_image" placeholder="全部" clearable style="width: 100px;">
              <el-option label="有" value="1" />
              <el-option label="无" value="0" />
            </el-select>
          </el-form-item>
          <el-form-item label="发货图">
            <el-select v-model="listQuery.has_shipping_image" placeholder="全部" clearable style="width: 100px;">
              <el-option label="有" value="1" />
              <el-option label="无" value="0" />
            </el-select>
          </el-form-item>
          <el-form-item label="采购日期">
            <el-date-picker
              v-model="purchaseDateRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="yyyy-MM-dd"
              style="width: 240px;"
            />
          </el-form-item>
          <el-form-item label="发货日期">
            <el-date-picker
              v-model="shippingDateRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="yyyy-MM-dd"
              style="width: 240px;"
            />
          </el-form-item>
          <el-form-item label="争议">
            <el-select v-model="listQuery.issue_status" placeholder="争议状态" clearable style="width: 140px;">
              <el-option v-for="item in issueStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="handleFilter">查询</el-button>
            <el-button @click="resetFilter">重置</el-button>
          </el-form-item>
        </el-form>
      </el-collapse-item>
    </el-collapse>

    <!-- 操作按钮 -->
    <div style="margin: 12px 0; display: flex; align-items: center; gap: 12px;">
      <el-button type="danger" size="small" icon="el-icon-refresh" :loading="syncing" @click="handleSync">同步订单</el-button>
      <el-button type="primary" plain size="small" icon="el-icon-download" :loading="exporting" @click="handleExport">导出订单</el-button>
      <el-select v-model="batchBackendStatus" clearable size="small" placeholder="批量修改后台状态" style="width: 180px;">
        <el-option v-for="item in backendStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
      </el-select>
      <el-button
        type="warning"
        plain
        size="small"
        :disabled="selectedOrders.length === 0 || !batchBackendStatus"
        @click="handleBatchUpdateBackendStatus"
      >批量改后台状态</el-button>
      <span style="color: #999; font-size: 12px;">已选 {{ selectedOrders.length }} 条</span>
      <span style="color: #999; font-size: 12px;">共 {{ total }} 条</span>
      <div style="margin-left: auto;">
        <el-select v-model="listQuery.limit" size="small" style="width: 100px;" @change="handleFilter">
          <el-option :value="20" label="20条/页" />
          <el-option :value="50" label="50条/页" />
          <el-option :value="100" label="100条/页" />
        </el-select>
      </div>
    </div>

    <!-- 订单列表 -->
    <div v-loading="listLoading">
      <div v-if="list.length === 0 && !listLoading" style="text-align: center; padding: 60px; color: #999;">
        暂无订单数据，请先同步
      </div>

      <div v-if="list.length > 0" class="order-list-header">
        <div class="col-check header-check">
          <el-checkbox
            :value="isAllCurrentPageSelected"
            :indeterminate="isCurrentPageIndeterminate"
            @change="toggleSelectAllCurrentPage"
          />
        </div>
        <div class="col-images">商品图片</div>
        <div class="col-goods">商品标题</div>
        <div class="col-basic">订单基本信息</div>
        <div class="col-logistics">物流/收货信息</div>
        <div class="col-amount">金额信息</div>
        <div class="col-backend">后台字段信息</div>
        <div class="col-ops">操作</div>
      </div>

      <div v-for="order in list" :key="order.id" class="order-card">
        <div class="order-row">
          <div class="col-check cell-check">
            <el-checkbox :value="selectedOrders.includes(order.id)" @change="toggleSelect(order.id)" />
          </div>

          <div class="col-images cell-block">
            <div v-for="item in (order.items || []).slice(0, 2)" :key="item.id" class="image-item">
              <el-image
                v-if="item.img_url"
                :src="item.img_url"
                :preview-src-list="[item.img_url]"
                fit="cover"
                class="goods-thumb"
              />
              <div v-else class="goods-thumb goods-thumb--empty">无图</div>
            </div>
            <div v-if="(order.items || []).length === 0" class="empty-text">暂无图片</div>
          </div>

          <div class="col-goods cell-block">
            <div v-for="item in (order.items || []).slice(0, 2)" :key="item.id" class="goods-item">
              <div class="goods-main">
                <a v-if="getItemLink(item)" :href="getItemLink(item)" target="_blank" rel="noopener noreferrer" class="goods-title goods-title-link">{{ item.name || '-' }}</a>
                <div v-else class="goods-title">{{ item.name || '-' }}</div>
                <div class="goods-category">分类：{{ getCategoryFromSku(item) }}</div>
                <div class="goods-meta">{{ item.sku_code || '-' }} | {{ item.item_price || 0 }} x {{ item.quantity || 1 }}</div>
              </div>
            </div>
            <div v-if="(order.items || []).length === 0" class="empty-text">暂无商品</div>
            <div v-if="(order.items || []).length > 2" class="more-text">等 {{ order.items.length }} 件商品</div>
          </div>

          <div class="col-basic cell-block">
            <div class="meta-line"><span class="label">店铺名称</span><span class="clip-text">{{ order.shop ? order.shop.name : '-' }}</span></div>
            <div class="meta-line"><span class="label">店铺邮箱</span><span class="clip-text">{{ order.shop ? order.shop.email : '-' }}</span></div>
            <div class="meta-line"><span class="label">订单号</span>{{ order.ae_order_id }}</div>
            <div class="meta-line"><span class="label">下单</span>{{ formatDate(order.ae_created_at) }}</div>
            <div class="meta-line"><span class="label">买家</span>{{ order.buyer_name || '-' }}</div>
            <div class="meta-line"><span class="label">状态</span><el-tag :type="getStatusTagType(order.order_display_status)" size="mini">{{ getStatusLabel(order.order_display_status) }}</el-tag></div>
          </div>

          <div class="col-logistics cell-block">
            <div class="meta-line"><span class="label">收件人</span>{{ order.receiver_name || order.buyer_name || '-' }}</div>
            <div class="meta-line"><span class="label">电话</span>{{ order.receiver_phone || order.buyer_phone || '-' }}</div>
            <div class="meta-line"><span class="label">地址</span><span class="clip-text">{{ formatAddress(order) }}</span></div>
            <div class="meta-line"><span class="label">物流</span>{{ getLogisticsTypeLabel(order.logistics_type) }}</div>
            <div class="meta-line tracking-line">
              <span class="label">运单号</span>
              <span class="tracking-value">{{ order.tracking_number || '-' }}</span>
              <el-button
                v-if="order.tracking_number"
                type="text"
                size="mini"
                icon="el-icon-copy-document"
                class="tracking-copy-btn"
                @click="copyText(order.tracking_number)"
              />
            </div>
          </div>

          <div class="col-amount cell-block">
            <div class="meta-line"><span class="label">销售额</span><span class="strong">{{ Number(order.total_amount || 0).toFixed(2) }}</span></div>
            <div class="meta-line"><span class="label">手续费</span>{{ calcFee(order) }}</div>
            <div class="meta-line"><span class="label">回款</span>{{ calcTotalBack(order) }}</div>
            <div class="meta-line"><span class="label">采购</span>{{ Number(order.purchase_amount || 0).toFixed(2) }}</div>
            <div class="meta-line"><span class="label">物流费</span>{{ Number(order.logistics_fee || 0).toFixed(2) }}</div>
            <div class="meta-line"><span class="label">利润</span><span :class="calcProfit(order) >= 0 ? 'text-success' : 'text-danger'">{{ Number(calcProfit(order) || 0).toFixed(2) }}</span></div>
            <div class="meta-line"><span class="label">利润率</span><span :class="calcProfit(order) >= 0 ? 'text-success' : 'text-danger'">{{ calcProfitRate(order) }}%</span></div>
            <div class="meta-line"><span class="label">人民币利润</span><span :class="calcProfit(order) >= 0 ? 'text-success' : 'text-danger'">{{ calcProfitCny(order) }}</span></div>
          </div>

          <div class="col-backend cell-block">
            <div class="meta-line"><span class="label">后台状态</span>{{ getBackendStatusLabel(order.backend_status) }}</div>
            <div class="meta-line"><span class="label">物流模板</span>{{ getLogisticsTemplateLabel(order.logistics_template) }}</div>
            <div class="meta-line"><span class="label">采购日期</span>{{ order.purchase_date || '-' }}</div>
            <div class="meta-line"><span class="label">发货日期</span>{{ order.shipping_date || '-' }}</div>
            <div class="meta-line"><span class="label">后台备注</span><span class="clip-text">{{ order.admin_remark || '-' }}</span></div>
            <div class="meta-line image-line"><span class="label">采购图片</span>
              <el-image
                v-if="order.purchase_image"
                :src="order.purchase_image"
                :preview-src-list="[order.purchase_image]"
                class="backend-thumb"
                fit="cover"
              />
              <span v-else>-</span>
            </div>
            <div class="meta-line image-line"><span class="label">上传图片</span>
              <el-image
                v-if="order.shipping_image"
                :src="order.shipping_image"
                :preview-src-list="[order.shipping_image]"
                class="backend-thumb"
                fit="cover"
              />
              <span v-else>-</span>
            </div>
          </div>

          <div class="col-ops cell-ops">
            <el-button
              v-if="canPrintLabel(order)"
              type="primary" size="mini"
              @click="handlePrintLabel(order)"
            >打印面单</el-button>
            <el-button
              v-if="order.order_display_status === 'WaitSendGoods'"
              type="success" size="mini"
              @click="handleShip(order)"
            >实际发货</el-button>
            <el-button type="warning" size="mini" @click="handleMarkShip(order)">更新订单</el-button>
            <el-button type="info" size="mini" @click="openCommentDialog(order)">后台更新</el-button>
          </div>
        </div>

      </div>
    </div>

    <!-- 分页 -->
    <pagination v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit" @pagination="getList" />

    <!-- 后台更新对话框 -->
    <el-dialog title="订单后台更新" :visible.sync="commentDialogVisible" width="700px">
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
              <el-input v-model.number="commentTemp.purchase_amount" type="number" min="0" @input="recalcEubLogisticsFee" />
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
              <el-input v-model.number="commentTemp.logistics_fee" type="number" min="0" @input="markLogisticsFeeManualEdit" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="24">
            <el-form-item label="物流模板">
              <el-radio-group v-model="commentTemp.logistics_template" @change="handleTemplateChange">
                <el-radio-button label="online">线上</el-radio-button>
                <el-radio-button label="offline_leiyi">线下-雷翼/邮政</el-radio-button>
                <el-radio-button label="offline_epacket">线下-E邮宝</el-radio-button>
              </el-radio-group>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row v-if="commentTemp.logistics_template === 'offline_epacket'" :gutter="12">
          <el-col :span="12">
            <el-form-item label="亚马逊比例%">
              <el-input v-model.number="commentTemp.eub_amazon_ratio" type="number" min="0" @input="recalcEubLogisticsFee" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="固定附加费">
              <el-input v-model.number="commentTemp.eub_base_fee" type="number" min="0" @input="recalcEubLogisticsFee" />
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
            <el-button size="mini" @click="resetLogisticsFeeToCalculated">按计算值回填物流费</el-button>
          </el-col>
        </el-row>
        <el-form-item label="采购图片">
          <el-upload
            :action="uploadUrl"
            :headers="uploadHeaders"
            :show-file-list="false"
            :on-success="res => handleImageUploadSuccess(res, 'purchase_image')"
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
            :on-success="res => handleImageUploadSuccess(res, 'shipping_image')"
          >
            <el-button size="small" type="success">上传发货图片</el-button>
          </el-upload>
          <div v-if="commentTemp.shipping_image" style="margin-top: 8px;">
            <el-image :src="commentTemp.shipping_image" :preview-src-list="[commentTemp.shipping_image]" style="width: 100px; height: 100px; border: 1px solid #eee;" fit="cover" />
          </div>
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="commentDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitComment">保存</el-button>
      </div>
    </el-dialog>

    <!-- 发货对话框 -->
    <el-dialog :title="shipDialogTitle" :visible.sync="shipDialogVisible" width="520px" @close="shipDialogVisible=false">
      <el-form label-width="100px">
        <el-form-item label="物流类型">
          <el-tag :type="shipForm.logistics_type === 'DBS' ? 'warning' : 'success'" size="small">{{ shipForm.logistics_type || '-' }}</el-tag>
        </el-form-item>
        <el-form-item v-if="shipForm.logistics_type === 'DBS'" label="发货渠道" required>
          <el-radio-group v-model="shipForm.ship_provider">
            <el-radio label="chinapost">中国邮政(E邮宝)</el-radio>
            <el-radio label="leiyi">雷翼(sz56t)</el-radio>
            <el-radio label="manual">手动填写单号</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="shipForm.ship_provider === 'chinapost'" label="业务产品">
          <el-select v-model="shipForm.biz_product_no" style="width:100%;">
            <el-option label="E邮宝 (001)" value="001" />
            <el-option label="挂号小包 (002)" value="002" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="shipForm.ship_provider === 'chinapost'" label="重量(克)">
          <el-input-number v-model="shipForm.weight" :min="1" :max="50000" :step="10" style="width:100%;" />
        </el-form-item>
        <el-form-item v-if="shipForm.ship_provider === 'leiyi'" label="重量(克)">
          <el-input-number v-model="shipForm.weight" :min="1" :max="50000" :step="10" style="width:100%;" />
        </el-form-item>
        <el-form-item v-if="shipForm.ship_provider !== 'chinapost' && shipForm.ship_provider !== 'leiyi' || shipForm.logistics_type !== 'DBS'" label="运单号" required>
          <el-input v-model="shipForm.track_number" placeholder="请输入运单号" clearable />
        </el-form-item>
        <el-form-item v-if="shipForm.logistics_type !== 'DBS'" label="物流方式">
          <el-select v-model="shipForm.logistic_method" placeholder="可选，不填则使用订单默认" clearable style="width:100%;">
            <el-option
              v-for="opt in logisticMethodOptions"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item v-if="shipForm.logistics_type === 'DBS' && shipForm.ship_provider === 'manual'" label="物流商名称">
          <el-input v-model="shipForm.provider_name" placeholder="例如: China Post, YANWEN" />
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="shipDialogVisible = false">取消</el-button>
        <el-button v-if="shipForm.ship_provider === 'chinapost'" type="primary" :loading="shipping" @click="submitChinaPostCreate">邮政下单</el-button>
        <el-button v-if="shipForm.ship_provider === 'leiyi'" type="primary" :loading="shipping" @click="submitSz56tCreate">雷翼下单</el-button>
        <el-button type="success" :loading="shipping" @click="submitShip">确认发货</el-button>
      </div>
    </el-dialog>

    <!-- 同步对话框 -->
    <el-dialog title="同步订单" :visible.sync="syncDialogVisible" width="460px">
      <el-form label-width="100px">
        <el-form-item label="指定店铺">
          <el-select v-model="syncForm.shop_id" placeholder="不选则同步所有店铺" clearable style="width: 100%;">
            <el-option v-for="s in shopOptions" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="更新时间范围">
          <el-date-picker
            v-model="syncDateRange"
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
        <el-button @click="syncDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="syncing" @click="submitSync">开始同步</el-button>
      </div>
    </el-dialog>

    <el-dialog title="同步进度" :visible.sync="syncProgressDialogVisible" width="620px" :close-on-click-modal="false">
      <el-progress :percentage="syncProgress.progress" :status="syncProgress.status === 'failed' ? 'exception' : 'success'" />
      <div style="margin-top: 12px; line-height: 1.8; color: #666; font-size: 13px;">
        <div>状态：{{ getSyncStatusLabel(syncProgress.status) }}</div>
        <div>店铺进度：{{ syncProgress.processed_shops }}/{{ syncProgress.total_shops }}</div>
        <div>已同步订单：{{ syncProgress.synced_orders }}</div>
        <div>失败店铺：{{ syncProgress.failed_shops }}</div>
        <div v-if="syncProgress.current_shop_name">当前店铺：{{ syncProgress.current_shop_name }}</div>
      </div>

      <el-table :data="syncProgress.details || []" size="mini" border style="margin-top: 14px;">
        <el-table-column label="店铺" prop="shop_name" min-width="120" />
        <el-table-column label="同步条数" prop="synced" width="100" align="center" />
        <el-table-column label="结果" min-width="220">
          <template slot-scope="{row}">
            <span v-if="row.error" style="color: #f56c6c;">{{ row.error }}</span>
            <span v-else style="color: #67c23a;">成功</span>
          </template>
        </el-table-column>
      </el-table>

      <div slot="footer">
        <el-button @click="syncProgressDialogVisible = false">关闭</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {fetchOrderList, fetchOrderStatusCounts, fetchOrderBackendStatusCounts, batchUpdateOrderBackendStatus, syncOrdersStart, fetchSyncProgress, updateOrderBackendFields, shipOrder, getOrderLabel, exportOrders, chinaPostCreateOrder, chinaPostGetLabel, sz56tCreateOrder, sz56tGetLabel, sz56tGetTrackingNumber} from '@/api/order'
import {fetchShopList} from '@/api/shop'
import {fetchConfigList, fetchDictByCode} from '@/api/system'
import Pagination from '@/components/Pagination'
import { getToken } from '@/utils/auth'

const ORDER_DISPLAY_STATUS_LABELS = {
  Unknown: '未知',
  PlaceOrderSuccess: '待付款',
  PaymentPending: '付款处理中',
  WaitExamineMoney: '等待确认收款',
  WaitGroup: '团购中',
  WaitSendGoods: '待发货',
  PartialSendGoods: '部分发货',
  WaitAcceptGoods: '待收货',
  InCancel: '申请取消',
  Complete: '已完成',
  Close: '已关闭',
  InFrozen: '冻结中',
  InIssue: '争议中',
}

const ORDER_STATUS_LABELS = {
  Created: '已创建',
  Cancelled: '已取消',
  Finished: '已完成',
  Closed: '已关闭',
}

const PAYMENT_STATUS_LABELS = {
  Hold: '待付款',
  Paid: '已付款',
  Refunded: '已退款',
  PartiallyRefunded: '部分退款',
  PaymentProcessing: '付款处理中',
}

const DELIVERY_STATUS_LABELS = {
  Init: '待发货',
  Processing: '处理中',
  Shipped: '已发货',
  Delivered: '已送达',
  Returned: '已退回',
  Cancelled: '已取消',
}

const ANTIFRAUD_STATUS_LABELS = {
  Passed: '通过',
  InReview: '审核中',
  Rejected: '未通过',
}

const ISSUE_STATUS_LABELS = {
  NoDispute: '无争议',
  InProcess: '争议处理中',
  Finished: '争议已解决',
}

const LOGISTICS_TYPE_LABELS = {
  DBS: '卖家配送',
  FBS: '平台配送',
}

const SYNC_STATUS_LABELS = {
  pending: '排队中',
  running: '同步中',
  completed: '已完成',
  failed: '失败',
}

const FINISH_REASON_LABELS = {
  PaymentTimeout: '付款时间已过',
  ShippingTimeout: '发货时间已过',
  BuyerNotPickUpPosting: '买家未按时取货',
  CancelledByBuyer: '买家取消已付款订单',
  SecurityClose: '平台安全风控关闭',
  LogisticOrderToPostingMapFailed: '创建发货单失败',
  CancelledBySeller: '卖家取消订单',
  BuyerDoesNotWantOrder: '买家不想要订单',
  BuyerWantChangeProduct: '买家想更换商品',
  BuyerChangeCoupon: '买家想更换优惠券',
  BuyerChangeMailAddress: '买家更改收货地址',
  BuyerChangeLogistic: '买家更改配送方式',
  BuyerCannotPayment: '买家未付款',
  BuyerOtherReasons: '买家其他原因',
  ProductNotEnough: '库存不足',
  SellerDidNotUseBuyerLogisticType: '卖家未使用买家配送方式',
  BuyerCannotContactSeller: '买家无法联系卖家',
  SellerRiseOrderAmount: '卖家提高订单价格',
  CancelGroupBuyAfterPay: '付款后团购取消',
  GroupBuyFailure: '团购失败',
  FreightCommitDayNotMatch: '未按承诺时间发货',
  ConfirmedByBuyer: '买家确认收货',
  AutoConfirm: '系统自动确认收货',
  ConfirmedByLogistic: '物流确认收货',
}

const STATUS_TAG_TYPE = {
  WaitSendGoods: 'warning',
  Complete: 'success',
  Close: 'info',
  InIssue: 'danger',
  InFrozen: 'danger',
  WaitAcceptGoods: '',
}

const ORDER_DICT_CODE = {
  orderDisplayStatus: 'ae_order_display_status',
  backendStatus: 'order_backend_status',
  orderStatus: 'ae_order_status',
  paymentStatus: 'ae_payment_status',
  deliveryStatus: 'ae_delivery_status',
  antifraudStatus: 'ae_antifraud_status',
  issueStatus: 'ae_issue_status',
  logisticsType: 'ae_logistics_type',
  finishReason: 'ae_finish_reason',
  syncStatus: 'order_sync_status',
}

export default {
  name: 'OrderManage',
  components: {Pagination},
  data() {
    return {
      list: [],
      total: 0,
      listLoading: false,
      selectedOrders: [],
      statusCounts: {},
      backendStatusCounts: {},
      shopOptions: [],
      dateRange: [],
      purchaseDateRange: [],
      shippingDateRange: [],
      listQuery: {
        page: 1,
        limit: 20,
        display_status: '',
        backend_status: '',
        shop_id: '',
        shop_keyword: '',
        ae_order_id: '',
        tracking_number: '',
        receiver_name: '',
        receiver_phone: '',
        buyer_name: '',
        buyer_phone: '',
        address_keyword: '',
        seller_comment: '',
        admin_remark: '',
        has_purchase_image: '',
        has_shipping_image: '',
        issue_status: '',
        date_start: '',
        date_end: '',
        purchase_date_start: '',
        purchase_date_end: '',
        shipping_date_start: '',
        shipping_date_end: '',
      },
      showFilter: [],
      statusTabs: [
        {key: '', label: '所有订单', countKey: 'all'},
        {key: 'Unknown', label: '状态未知', countKey: 'Unknown'},
        {key: 'PlaceOrderSuccess', label: '等待付款', countKey: 'PlaceOrderSuccess'},
        {key: 'PaymentPending', label: '付款处理中', countKey: 'PaymentPending'},
        {key: 'WaitExamineMoney', label: '等待付款确认', countKey: 'WaitExamineMoney'},
        {key: 'WaitGroup', label: '拼团中', countKey: 'WaitGroup'},
        {key: 'WaitSendGoods', label: '等待发货', countKey: 'WaitSendGoods'},
        {key: 'PartialSendGoods', label: '部分发货', countKey: 'PartialSendGoods'},
        {key: 'WaitAcceptGoods', label: '等待收货', countKey: 'WaitAcceptGoods'},
        {key: 'InCancel', label: '买家申请取消', countKey: 'InCancel'},
        {key: 'Complete', label: '已完成', countKey: 'Complete'},
        {key: 'Close', label: '已关闭', countKey: 'Close'},
        {key: 'InFrozen', label: '挂起中', countKey: 'InFrozen'},
        {key: 'InIssue', label: '订单争议', countKey: 'InIssue'},
      ],
      backendStatusTabs: [
        {key: '', label: '全部后台状态', countKey: 'all'},
      ],
      backendStatusOptions: [],
      batchBackendStatus: '',
      // 发货对话框
      shipDialogVisible: false,
      shipping: false,
      labelLoading: false,
      shipForm: {
        id: null,
        track_number: '',
        logistic_method: '',
        logistics_type: '',
        ship_provider: 'manual',
        provider_name: 'China Post',
        biz_product_no: '001',
        weight: 100
      },
      logisticMethodOptions: [],
      // 备注
      commentDialogVisible: false,
      commentTemp: {
        id: null,
        admin_remark: '',
        backend_status: '',
        purchase_image: '',
        shipping_image: '',
        purchase_date: '',
        shipping_date: '',
        lianlian_fee: 0,
        purchase_amount: 0,
        express_fee: 0,
        logistics_fee: 0,
        logistics_template: 'online',
        eub_amazon_ratio: 0,
        eub_base_fee: 0,
        calculated_logistics_fee: 0,
        logistics_fee_override: false,
        apply_qianze_at: '',
        ship_qianze_at: '',
      },
      uploadUrl: process.env.VUE_APP_BASE_API + '/files/upload',
      uploadHeaders: { Authorization: 'Bearer ' + getToken() },
      // 同步
      syncDialogVisible: false,
      syncForm: {shop_id: ''},
      syncDateRange: [],
      syncing: false,
      exporting: false,
      syncTaskId: null,
      syncProgressDialogVisible: false,
      syncProgress: {
        status: '',
        progress: 0,
        total_shops: 0,
        processed_shops: 0,
        failed_shops: 0,
        synced_orders: 0,
        current_shop_name: '',
        details: [],
      },
      syncPollTimer: null,
      dictLabelMap: {},
      issueStatusOptions: [],
      cnyExchangeRate: 7.2,
    }
  },
  computed: {
    shipDialogTitle() {
      const type = (this.shipForm.logistics_type || '').toUpperCase()
      return type === 'DBS' ? 'DBS 线下发货' : '实际发货'
    },
    currentPageOrderIds() {
      return this.list.map(order => order.id)
    },
    isAllCurrentPageSelected() {
      if (!this.currentPageOrderIds.length) return false
      return this.currentPageOrderIds.every(id => this.selectedOrders.includes(id))
    },
    isCurrentPageIndeterminate() {
      if (!this.currentPageOrderIds.length) return false
      const selectedCount = this.currentPageOrderIds.filter(id => this.selectedOrders.includes(id)).length
      return selectedCount > 0 && selectedCount < this.currentPageOrderIds.length
    }
  },
  created() {
    this.loadShops()
    this.loadOrderDicts()
    this.loadFinanceConfig()
    this.getStatusCounts()
    this.getBackendStatusCounts()
    this.getList()
  },
  methods: {
    loadFinanceConfig() {
      fetchConfigList({ group: 'finance' }).then(res => {
        const configs = res.data || []
        const rateConfig = configs.find(item => item.key === 'cny_exchange_rate')
        const rate = parseFloat(rateConfig && rateConfig.value)
        this.cnyExchangeRate = rate > 0 ? rate : 7.2
      }).catch(() => {
        this.cnyExchangeRate = 7.2
      })
    },
    loadShops() {
      fetchShopList({page: 1, limit: 200}).then(res => {
        this.shopOptions = res.data.items || []
      })
    },
    async loadOrderDicts() {
      const targets = Object.values(ORDER_DICT_CODE)
      const map = {}
      await Promise.all(targets.map(code => {
        return fetchDictByCode(code).then(res => {
          const items = (res.data || []).filter(i => Number(i.status) === 1)
          map[code] = items.reduce((acc, item) => {
            acc[String(item.value)] = item.label
            return acc
          }, {})
        }).catch(() => {
          map[code] = {}
        })
      }))
      this.dictLabelMap = map

      const issueFromDict = Object.entries(map[ORDER_DICT_CODE.issueStatus] || {}).map(([value, label]) => ({ value, label }))
      this.issueStatusOptions = issueFromDict.length > 0
        ? issueFromDict
        : Object.entries(ISSUE_STATUS_LABELS).map(([value, label]) => ({ value, label }))

      const backendFromDict = Object.entries(map[ORDER_DICT_CODE.backendStatus] || {}).map(([value, label]) => ({ value, label }))
      this.backendStatusOptions = backendFromDict
      this.backendStatusTabs = [
        { key: '', label: '全部后台状态', countKey: 'all' },
        ...backendFromDict.map(item => ({ key: item.value, label: item.label, countKey: item.value }))
      ]
    },
    getStatusCounts() {
      fetchOrderStatusCounts({
        shop_id: this.listQuery.shop_id,
        backend_status: this.listQuery.backend_status,
      }).then(res => {
        this.statusCounts = res.data || {}
      })
    },
    getBackendStatusCounts() {
      fetchOrderBackendStatusCounts({
        shop_id: this.listQuery.shop_id,
      }).then(res => {
        this.backendStatusCounts = res.data || {}
      })
    },
    getList() {
      this.listLoading = true
      const query = Object.assign({}, this.listQuery)
      if (this.dateRange && this.dateRange.length === 2) {
        query.date_start = this.dateRange[0]
        query.date_end = this.dateRange[1]
      }
      if (this.purchaseDateRange && this.purchaseDateRange.length === 2) {
        query.purchase_date_start = this.purchaseDateRange[0]
        query.purchase_date_end = this.purchaseDateRange[1]
      }
      if (this.shippingDateRange && this.shippingDateRange.length === 2) {
        query.shipping_date_start = this.shippingDateRange[0]
        query.shipping_date_end = this.shippingDateRange[1]
      }
      fetchOrderList(query).then(res => {
        this.list = res.data.items || []
        this.total = res.data.total || 0
        this.selectedOrders = this.selectedOrders.filter(id => this.list.some(o => o.id === id))
        this.listLoading = false
      }).catch(() => { this.listLoading = false })
    },
    switchTab(key) {
      this.listQuery.display_status = key
      this.listQuery.page = 1
      this.getList()
    },
    switchBackendStatusTab(key) {
      this.listQuery.backend_status = key
      this.listQuery.page = 1
      this.getList()
      this.getStatusCounts()
    },
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
      this.getStatusCounts()
      this.getBackendStatusCounts()
    },
    resetFilter() {
      this.dateRange = []
      this.purchaseDateRange = []
      this.shippingDateRange = []
      this.listQuery = {
        page: 1, limit: 20, display_status: this.listQuery.display_status,
        backend_status: this.listQuery.backend_status,
        shop_id: '', shop_keyword: '', ae_order_id: '', tracking_number: '',
        receiver_name: '', receiver_phone: '', buyer_name: '', buyer_phone: '',
        address_keyword: '', seller_comment: '', admin_remark: '',
        has_purchase_image: '', has_shipping_image: '',
        issue_status: '', date_start: '', date_end: '',
        purchase_date_start: '', purchase_date_end: '',
        shipping_date_start: '', shipping_date_end: '',
      }
      this.getList()
      this.getStatusCounts()
      this.getBackendStatusCounts()
    },
    handleSync() {
      this.syncDialogVisible = true
    },
    submitSync() {
      this.syncing = true
      const params = {shop_id: this.syncForm.shop_id || undefined}
      if (this.syncDateRange && this.syncDateRange.length === 2) {
        params.date_start = this.syncDateRange[0]
        params.date_end = this.syncDateRange[1]
      }
      syncOrdersStart(params).then(res => {
        this.syncing = false
        this.syncDialogVisible = false
        this.syncTaskId = res.data.task_id
        this.syncProgressDialogVisible = true
        this.syncProgress.status = 'running'
        this.syncProgress.progress = 0
        this.startSyncPolling()
      }).catch(() => { this.syncing = false })
    },
    startSyncPolling() {
      this.stopSyncPolling()
      this.pollSyncProgress()
      this.syncPollTimer = setInterval(() => {
        this.pollSyncProgress()
      }, 1500)
    },
    stopSyncPolling() {
      if (this.syncPollTimer) {
        clearInterval(this.syncPollTimer)
        this.syncPollTimer = null
      }
    },
    pollSyncProgress() {
      if (!this.syncTaskId) return
      fetchSyncProgress({task_id: this.syncTaskId}).then(res => {
        const d = res.data || {}
        this.syncProgress = {
          status: d.status || '',
          progress: d.progress || 0,
          total_shops: d.total_shops || 0,
          processed_shops: d.processed_shops || 0,
          failed_shops: d.failed_shops || 0,
          synced_orders: d.synced_orders || 0,
          current_shop_name: d.current_shop_name || '',
          details: d.details || [],
        }

        if (d.status === 'completed' || d.status === 'failed') {
          this.stopSyncPolling()
          if (d.status === 'completed') {
            this.$notify({title: '同步完成', message: `共同步 ${d.synced_orders || 0} 条订单`, type: 'success', duration: 3000})
          } else {
            this.$notify({title: '同步失败', message: d.message || '请查看同步明细', type: 'error', duration: 4000})
          }
          this.getStatusCounts()
          this.getList()
        }
      })
    },
    openCommentDialog(order) {
      this.commentTemp = {
        id: order.id,
        admin_remark: order.admin_remark || '',
        backend_status: order.backend_status || '',
        purchase_image: order.purchase_image || '',
        shipping_image: order.shipping_image || '',
        purchase_date: order.purchase_date || '',
        shipping_date: order.shipping_date || '',
        lianlian_fee: order.lianlian_fee || 0,
        purchase_amount: order.purchase_amount || 0,
        express_fee: order.express_fee || 0,
        logistics_fee: order.logistics_fee || 0,
        logistics_template: order.logistics_template || 'online',
        eub_amazon_ratio: Number(order.eub_amazon_ratio || 0),
        eub_base_fee: Number(order.eub_base_fee || 0),
        calculated_logistics_fee: Number(order.calculated_logistics_fee || 0),
        logistics_fee_override: Boolean(order.logistics_fee_override),
        apply_qianze_at: order.apply_qianze_at || '',
        ship_qianze_at: order.ship_qianze_at || '',
      }
      if (this.commentTemp.logistics_template === 'offline_epacket') {
        this.recalcEubLogisticsFee()
      }
      this.commentDialogVisible = true
    },
    handleTemplateChange() {
      if (this.commentTemp.logistics_template === 'offline_epacket') {
        this.recalcEubLogisticsFee()
      }
    },
    recalcEubLogisticsFee() {
      const purchase = Number(this.commentTemp.purchase_amount || 0)
      const ratio = Number(this.commentTemp.eub_amazon_ratio || 0)
      const base = Number(this.commentTemp.eub_base_fee || 0)
      const calc = Number((purchase * ratio / 100 + base).toFixed(2))
      this.commentTemp.calculated_logistics_fee = calc
      if (!this.commentTemp.logistics_fee_override) {
        this.commentTemp.logistics_fee = calc
      }
    },
    markLogisticsFeeManualEdit() {
      this.commentTemp.logistics_fee_override = true
    },
    resetLogisticsFeeToCalculated() {
      this.commentTemp.logistics_fee_override = false
      this.commentTemp.logistics_fee = Number(this.commentTemp.calculated_logistics_fee || 0)
    },
    submitComment() {
      if (this.commentTemp.logistics_template === 'offline_epacket') {
        this.recalcEubLogisticsFee()
      }
      updateOrderBackendFields(this.commentTemp).then(() => {
        this.commentDialogVisible = false
        const order = this.list.find(o => o.id === this.commentTemp.id)
        if (order) {
          order.admin_remark = this.commentTemp.admin_remark
          order.backend_status = this.commentTemp.backend_status
          order.purchase_image = this.commentTemp.purchase_image
          order.shipping_image = this.commentTemp.shipping_image
          order.purchase_date = this.commentTemp.purchase_date
          order.shipping_date = this.commentTemp.shipping_date
          order.lianlian_fee = this.commentTemp.lianlian_fee
          order.purchase_amount = this.commentTemp.purchase_amount
          order.express_fee = this.commentTemp.express_fee
          order.logistics_fee = this.commentTemp.logistics_fee
          order.logistics_template = this.commentTemp.logistics_template
          order.eub_amazon_ratio = this.commentTemp.eub_amazon_ratio
          order.eub_base_fee = this.commentTemp.eub_base_fee
          order.calculated_logistics_fee = this.commentTemp.calculated_logistics_fee
          order.logistics_fee_override = this.commentTemp.logistics_fee_override
          order.apply_qianze_at = this.commentTemp.apply_qianze_at
          order.ship_qianze_at = this.commentTemp.ship_qianze_at
        }
        this.getBackendStatusCounts()
        this.$notify({title: '成功', message: '订单后台信息已更新', type: 'success', duration: 2000})
      })
    },
    handleBatchUpdateBackendStatus() {
      if (!this.batchBackendStatus) {
        this.$message.warning('请选择要更新的后台状态')
        return
      }
      if (!this.selectedOrders.length) {
        this.$message.warning('请先选择订单')
        return
      }

      const targetLabel = this.getBackendStatusLabel(this.batchBackendStatus)
      this.$confirm(`确定将选中的 ${this.selectedOrders.length} 条订单更新为“${targetLabel}”吗？`, '批量更新后台状态', { type: 'warning' })
        .then(() => {
          return batchUpdateOrderBackendStatus({
            ids: this.selectedOrders,
            backend_status: this.batchBackendStatus,
          })
        })
        .then(res => {
          this.$message.success(res.message || '批量更新成功')
          this.getList()
          this.getBackendStatusCounts()
        })
        .catch(() => {})
    },
    buildExportQuery() {
      const query = Object.assign({}, this.listQuery)
      if (this.dateRange && this.dateRange.length === 2) {
        query.date_start = this.dateRange[0]
        query.date_end = this.dateRange[1]
      }
      return query
    },
    handleExport() {
      this.exporting = true
      exportOrders(this.buildExportQuery()).then(res => {
        const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8;' })
        const link = document.createElement('a')
        const url = window.URL.createObjectURL(blob)
        link.href = url
        link.download = `orders_${new Date().getTime()}.csv`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
        this.$message.success('导出成功')
      }).catch(err => {
        this.$message.error(err.message || '导出失败')
      }).finally(() => {
        this.exporting = false
      })
    },
    handleImageUploadSuccess(response, field) {
      if (response.code === 20000 && response.data && response.data.url) {
        this.commentTemp[field] = response.data.url
        this.$message.success('上传成功')
      } else {
        this.$message.error(response.message || '上传失败')
      }
    },
    canPrintLabel(order) {
      return order.sz56t_order_id || (order.tracking_number && order.logistics_type === 'DBS') || order.logistic_order_id
    },
    handlePrintLabel(order) {
      // 优先级：雷翼 > 邮政 > AliExpress FBS
      if (order.sz56t_order_id) {
        this.labelLoading = true
        sz56tGetLabel({ id: order.id }).then(res => {
          const data = res.data || {}
          if (data.label_url) {
            window.open(data.label_url, '_blank')
            return
          }
          this.$message.warning(res.message || '雷翼面单暂不可用')
        }).catch(err => {
          this.$message.error(err.message || '雷翼面单获取失败')
        }).finally(() => { this.labelLoading = false })
      } else if (order.tracking_number && (order.logistics_type || '').toUpperCase() === 'DBS') {
        this.labelLoading = true
        chinaPostGetLabel({ id: order.id }).then(res => {
          const data = res.data || {}
          if (data.pdf_base64) {
            const binary = atob(data.pdf_base64)
            const bytes = new Uint8Array(binary.length)
            for (let i = 0; i < binary.length; i++) { bytes[i] = binary.charCodeAt(i) }
            const blob = new Blob([bytes], { type: 'application/pdf' })
            const url = window.URL.createObjectURL(blob)
            window.open(url, '_blank')
            setTimeout(() => window.URL.revokeObjectURL(url), 60000)
            return
          }
          this.$message.warning(res.message || '邮政面单暂不可用')
        }).catch(err => {
          this.$message.error(err.message || '邮政面单获取失败')
        }).finally(() => { this.labelLoading = false })
      } else if (order.logistic_order_id) {
        this.labelLoading = true
        getOrderLabel({ id: order.id }).then(res => {
          const data = res.data || {}
          if (data.label_url) {
            window.open(data.label_url, '_blank')
            return
          }
          this.$message.warning(res.message || '面单暂不可用')
        }).catch(err => {
          this.$message.error(err.message || '面单获取失败')
        }).finally(() => { this.labelLoading = false })
      }
    },
    handleShip(order) {
      const isDBS = (order.logistics_type || '').toUpperCase() === 'DBS'
      this.shipForm = {
        id: order.id,
        track_number: order.tracking_number || '',
        logistic_method: order.logistics_type || '',
        logistics_type: order.logistics_type || '',
        ship_provider: isDBS ? 'chinapost' : 'aliexpress',
        provider_name: 'China Post',
        biz_product_no: '001',
        weight: 100
      }
      // 从字典加载物流方式选项
      this.logisticMethodOptions = Object.entries(
        this.dictLabelMap['ae_logistic_method'] || {}
      ).map(([value, label]) => ({ value, label }))
      this.shipDialogVisible = true
    },
    submitChinaPostCreate() {
      this.shipping = true
      chinaPostCreateOrder({
        id: this.shipForm.id,
        biz_product_no: this.shipForm.biz_product_no,
        weight: this.shipForm.weight
      }).then(res => {
        const waybillNo = res.data && res.data.waybill_no
        this.$message.success(res.message || '邮政下单成功')
        if (waybillNo) {
          this.shipForm.track_number = waybillNo
          // 更新列表
          const target = this.list.find(o => o.id === this.shipForm.id)
          if (target) {
            target.tracking_number = waybillNo
            target.logistics_template = 'offline_epacket'
          }
        }
      }).catch(err => {
        this.$message.error(err.message || '邮政下单失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    submitSz56tCreate() {
      this.shipping = true
      sz56tCreateOrder({
        id: this.shipForm.id,
        weight: this.shipForm.weight
      }).then(res => {
        const data = res.data || {}
        this.$message.success(res.message || '雷翼下单成功')
        if (data.tracking_number) {
          this.shipForm.track_number = data.tracking_number
        }
        // 更新列表
        const target = this.list.find(o => o.id === this.shipForm.id)
        if (target) {
          if (data.tracking_number) target.tracking_number = data.tracking_number
          if (data.order_id) target.sz56t_order_id = data.order_id
          target.logistics_template = 'offline_leiyi'
        }
        if (data.is_delay) {
          this.$message.info('单号延迟获取，稍后可点击"获取跟踪号"')
        }
      }).catch(err => {
        this.$message.error(err.message || '雷翼下单失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    submitShip() {
      if (!this.shipForm.track_number) {
        this.$message.warning('请输入运单号')
        return
      }
      this.shipping = true
      const payload = {
        id: this.shipForm.id,
        track_number: this.shipForm.track_number,
        logistic_method: this.shipForm.logistic_method,
        ship_provider: this.shipForm.ship_provider,
        provider_name: this.shipForm.provider_name
      }
      shipOrder(payload).then(res => {
        this.$message.success(res.message || '发货成功')
        this.shipDialogVisible = false
        // 更新本地列表中该订单的运单号
        const target = this.list.find(o => o.id === this.shipForm.id)
        if (target) {
          target.tracking_number = this.shipForm.track_number
          target.actual_ship_at = res.data && res.data.actual_ship_at || new Date().toISOString()
        }
      }).catch(err => {
        this.$message.error(err.message || '发货失败')
      }).finally(() => {
        this.shipping = false
      })
    },
    handleMarkShip(order) {
      this.$message.info('更新订单：' + order.ae_order_id)
    },
    getStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.orderDisplayStatus, ORDER_DISPLAY_STATUS_LABELS)
    },
    translateByCode(value, code, fallbackDict = {}) {
      const dict = this.dictLabelMap[code] || {}
      return dict[String(value)] || fallbackDict[String(value)] || value || '-'
    },
    getOrderStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.orderStatus, ORDER_STATUS_LABELS)
    },
    getPaymentStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.paymentStatus, PAYMENT_STATUS_LABELS)
    },
    getDeliveryStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.deliveryStatus, DELIVERY_STATUS_LABELS)
    },
    getBackendStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.backendStatus, {})
    },
    getAntifraudStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.antifraudStatus, ANTIFRAUD_STATUS_LABELS)
    },
    getIssueStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.issueStatus, ISSUE_STATUS_LABELS)
    },
    getFinishReasonLabel(reason) {
      return this.translateByCode(reason, ORDER_DICT_CODE.finishReason, FINISH_REASON_LABELS)
    },
    getLogisticsTypeLabel(type) {
      return this.translateByCode(type, ORDER_DICT_CODE.logisticsType, LOGISTICS_TYPE_LABELS)
    },
    getSyncStatusLabel(status) {
      return this.translateByCode(status, ORDER_DICT_CODE.syncStatus, SYNC_STATUS_LABELS)
    },
    getLogisticsTemplateLabel(template) {
      if (template === 'offline_leiyi') return '线下-雷翼/邮政'
      if (template === 'offline_epacket') return '线下-E邮宝'
      if (template === 'online') return '线上'
      return template || '-'
    },
    getStatusTagType(status) {
      return STATUS_TAG_TYPE[status] || ''
    },
    formatDate(d) {
      if (!d) return '-'
      return d.replace('T', ' ').substring(0, 16)
    },
    formatAddress(order) {
      const parts = [order.receiver_country, order.receiver_region, order.receiver_city, order.receiver_street, order.receiver_zip]
      return parts.filter(Boolean).join(', ') || order.delivery_address || '-'
    },
    getItemLink(item) {
      if (!item || typeof item !== 'object') return ''

      const candidates = [
        item.item_url,
        item.product_url,
        item.detail_url,
        item.ae_item_url,
        item.url,
        item.link,
      ]

      if (item.properties && typeof item.properties === 'object') {
        candidates.push(
          item.properties.item_url,
          item.properties.product_url,
          item.properties.detail_url,
          item.properties.url,
          item.properties.link
        )
      }

      const raw = candidates.find(v => typeof v === 'string' && v.trim())
      if (!raw) {
        const itemIdRaw = [
          item.ae_item_id,
          item.item_id,
          item.product_id,
          item.properties && item.properties.ae_item_id,
          item.properties && item.properties.item_id,
          item.properties && item.properties.product_id,
        ].find(v => v !== undefined && v !== null && String(v).trim() !== '')

        if (!itemIdRaw) return ''

        const itemId = String(itemIdRaw).trim()
        const itemPath = itemId.includes('_') ? itemId : `1_${itemId}`
        const skuIdRaw = [
          item.ae_sku_id,
          item.sku_id,
          item.properties && item.properties.ae_sku_id,
          item.properties && item.properties.sku_id,
        ].find(v => v !== undefined && v !== null && String(v).trim() !== '')

        let builtUrl = `https://aliexpress.ru/item/${itemPath}.html`
        if (skuIdRaw) {
          builtUrl += `?sku_id=${encodeURIComponent(String(skuIdRaw).trim())}`
        }
        return builtUrl
      }

      const value = raw.trim()
      if (/^https?:\/\//i.test(value)) return value
      if (/^\/\//.test(value)) return `https:${value}`
      return `https://${value}`
    },
    getCategoryFromSku(item) {
      if (item.properties && typeof item.properties === 'object') {
        return Object.values(item.properties).slice(0, 2).join(' / ')
      }
      return item.name ? item.name.substring(0, 30) : '-'
    },
    calcItemRevenue(item) {
      return (parseFloat(item.item_estimate_revenue || 0)).toFixed(2)
    },
    calcProfitRate(order) {
      const profit = this.calcProfit(order)
      const base = parseFloat(order.total_amount || 0)
      if (!base) return '0.00'
      return ((profit / base) * 100).toFixed(2)
    },
    calcProfitCny(order) {
      const profit = this.calcProfit(order)
      const rate = parseFloat(this.cnyExchangeRate || 0)
      if (!(rate > 0)) return '0.00'
      return (profit / rate).toFixed(2)
    },
    calcFee(order) {
      return (parseFloat(order.platform_fee || 0) + parseFloat(order.affiliate_fee || 0)).toFixed(2)
    },
    calcTotalBack(order) {
      return parseFloat(order.estimate_revenue || 0).toFixed(2)
    },
    calcProfit(order) {
      const total = parseFloat(order.total_amount || 0)
      const fee = parseFloat(order.platform_fee || 0) + parseFloat(order.affiliate_fee || 0)
      const lianlian = parseFloat(order.lianlian_fee || 0)
      const purchase = parseFloat(order.purchase_amount || 0)
      const logistics = parseFloat(order.logistics_fee || 0)
      return +(total - fee - lianlian - purchase - logistics).toFixed(2)
    },
    getItemCount(order) {
      return order.items ? order.items.reduce((s, i) => s + (i.quantity || 1), 0) : 0
    },
    copyText(text) {
      navigator.clipboard.writeText(text).then(() => {
        this.$message.success('已复制')
      })
    },
    toggleSelect(id) {
      const idx = this.selectedOrders.indexOf(id)
      if (idx > -1) {
        this.selectedOrders.splice(idx, 1)
      } else {
        this.selectedOrders.push(id)
      }
    },
    toggleSelectAllCurrentPage(checked) {
      const pageIds = this.currentPageOrderIds
      if (checked) {
        this.selectedOrders = Array.from(new Set([...this.selectedOrders, ...pageIds]))
        return
      }
      this.selectedOrders = this.selectedOrders.filter(id => !pageIds.includes(id))
    },
    onImgError(e) {
      e.target.style.display = 'none'
    },
  },
  beforeDestroy() {
    this.stopSyncPolling()
  },
}
</script>

<style scoped>
.order-page { padding-bottom: 30px; }
.status-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 12px;
  border-bottom: 1px solid #e8e8e8;
  padding-bottom: 8px;
}
.status-tab {
  padding: 6px 14px;
  cursor: pointer;
  font-size: 13px;
  color: #666;
  border-bottom: 2px solid transparent;
  white-space: nowrap;
}
.status-tab.active {
  color: #409eff;
  border-bottom-color: #409eff;
  font-weight: 600;
}
.status-tab:hover { color: #409eff; }

.backend-tabs {
  margin-top: -4px;
}

.order-list-header {
  display: grid;
  grid-template-columns: 50px 100px 1.05fr 0.85fr 1.2fr 0.95fr 1.05fr 130px;
  gap: 8px;
  align-items: center;
  background: #f5f7fa;
  border: 1px solid #e4e7ed;
  border-radius: 6px;
  padding: 8px 10px;
  margin-bottom: 8px;
  color: #606266;
  font-size: 12px;
  font-weight: 600;
}

.order-card {
  border: none;
  border-bottom: 1px solid #ebeef5;
  border-radius: 0;
  background: #fff;
  margin-bottom: 0;
  overflow: hidden;
}

.order-card:first-child {
  border-top: 1px solid #ebeef5;
}

.order-row {
  display: grid;
  grid-template-columns: 50px 100px 1.05fr 0.85fr 1.2fr 0.95fr 1.05fr 130px;
  gap: 8px;
  align-items: stretch;
  padding: 10px;
}

.cell-check {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding-top: 2px;
}

.header-check {
  display: flex;
  justify-content: center;
  align-items: center;
}

.header-check .el-checkbox {
  margin-right: 0;
}

.cell-block {
  min-width: 0;
  border-right: 1px dashed #eef1f6;
  padding-right: 6px;
}

.cell-ops {
  border-right: none;
  padding-right: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: stretch;
}

.cell-ops .el-button {
  width: 100%;
  margin-left: 0;
}

.cell-ops .el-button + .el-button {
  margin-left: 0;
}

.cell-ops .text-link {
  display: inline-flex;
  justify-content: center;
  width: 100%;
}

.meta-line {
  display: flex;
  align-items: flex-start;
  gap: 4px;
  font-size: 12px;
  color: #606266;
  line-height: 1.35;
  margin-bottom: 2px;
}

.label {
  color: #909399;
  width: 64px;
  flex-shrink: 0;
  white-space: nowrap;
}

.tracking-line {
  align-items: center;
}

.tracking-value {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tracking-copy-btn {
  margin-left: 2px;
  padding: 0;
}

.goods-item {
  display: block;
  margin-bottom: 6px;
}

.image-item {
  margin-bottom: 6px;
}

.goods-thumb {
  width: 56px;
  height: 56px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  object-fit: cover;
  flex-shrink: 0;
}

.goods-thumb--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: #b0b6bf;
  background: #fafafa;
}

.goods-main {
  min-width: 0;
}

.goods-title {
  font-size: 12px;
  color: #303133;
  line-height: 1.4;
  white-space: normal;
  word-break: break-word;
}

.goods-category {
  margin-top: 2px;
  font-size: 12px;
  color: #8d96a3;
}

.goods-title-link {
  color: #409eff;
  text-decoration: underline;
}

.goods-title-link:hover {
  color: #1f78d1;
  text-decoration: underline;
}

.goods-meta {
  font-size: 12px;
  color: #909399;
  margin-top: 2px;
}

.empty-text,
.more-text {
  color: #909399;
  font-size: 12px;
}

.clip-text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.image-line {
  align-items: center;
}

.backend-thumb {
  width: 36px;
  height: 36px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
}

.strong {
  font-weight: 700;
  color: #303133;
}

.text-link {
  color: #409eff;
  font-size: 12px;
  text-decoration: none;
}

.text-link:hover {
  text-decoration: underline;
}

@media (max-width: 1400px) {
  .order-list-header,
  .order-row {
    grid-template-columns: 44px 86px 1fr 0.8fr 1.05fr 0.9fr 0.95fr 120px;
    gap: 8px;
  }
}

@media (max-width: 1100px) {
  .order-list-header {
    display: none;
  }

  .order-row {
    grid-template-columns: 40px 1fr;
    gap: 8px;
  }

  .cell-block,
  .cell-ops {
    grid-column: 2;
    border-right: none;
    border-top: 1px dashed #f0f2f5;
    padding-top: 6px;
    padding-right: 0;
  }
}
</style>
