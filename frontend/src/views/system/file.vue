<template>
  <div class="app-container">
    <div class="filter-container">
      <el-input v-model="listQuery.original_name" placeholder="文件名" style="width: 200px;" class="filter-item" clearable @keyup.enter.native="handleFilter" />
      <el-select v-model="listQuery.mime_type" placeholder="文件类型" clearable class="filter-item" style="width: 140px;">
        <el-option label="图片" value="image" />
        <el-option label="文档" value="application" />
      </el-select>
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索</el-button>
      <el-upload
        class="filter-item"
        :action="uploadUrl"
        :headers="uploadHeaders"
        :show-file-list="false"
        :on-success="handleUploadSuccess"
        :on-error="handleUploadError"
        :before-upload="beforeUpload"
        style="display: inline-block; margin-left: 10px;"
      >
        <el-button type="success" icon="el-icon-upload2">上传文件</el-button>
      </el-upload>
    </div>

    <el-table v-loading="listLoading" :data="list" border fit>
      <el-table-column label="ID" prop="id" width="80" align="center" />
      <el-table-column label="预览" width="80" align="center">
        <template slot-scope="{row}">
          <el-image v-if="isImage(row.mime_type)" :src="row.url" :preview-src-list="[row.url]" style="width: 40px; height: 40px;" fit="cover" />
          <i v-else class="el-icon-document" style="font-size: 24px; color: #909399;" />
        </template>
      </el-table-column>
      <el-table-column label="文件名" prop="original_name" show-overflow-tooltip />
      <el-table-column label="类型" prop="mime_type" width="160" show-overflow-tooltip />
      <el-table-column label="大小" width="100" align="center">
        <template slot-scope="{row}">{{ formatSize(row.size) }}</template>
      </el-table-column>
      <el-table-column label="上传时间" prop="created_at" width="170" />
      <el-table-column label="操作" align="center" width="160">
        <template slot-scope="{row}">
          <el-button type="primary" size="mini" icon="el-icon-link" @click="copyUrl(row)">复制</el-button>
          <el-button type="danger" size="mini" icon="el-icon-delete" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <pagination v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit" @pagination="getList" />
  </div>
</template>

<script>
import { fetchFileList, deleteFile } from '@/api/system'
import { getToken } from '@/utils/auth'
import Pagination from '@/components/Pagination'

export default {
  name: 'FileManage',
  components: { Pagination },
  data() {
    return {
      list: [],
      total: 0,
      listLoading: true,
      listQuery: { page: 1, limit: 20, original_name: '', mime_type: '' },
      uploadUrl: process.env.VUE_APP_BASE_API + '/files/upload',
      uploadHeaders: { Authorization: 'Bearer ' + getToken() }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    getList() {
      this.listLoading = true
      fetchFileList(this.listQuery).then(response => {
        this.list = response.data.items
        this.total = response.data.total
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.getList()
    },
    isImage(mime) {
      return mime && mime.startsWith('image/')
    },
    formatSize(bytes) {
      if (!bytes) return '0 B'
      const units = ['B', 'KB', 'MB', 'GB']
      let i = 0
      let size = bytes
      while (size >= 1024 && i < units.length - 1) {
        size /= 1024
        i++
      }
      return size.toFixed(1) + ' ' + units[i]
    },
    beforeUpload(file) {
      const isLt10M = file.size / 1024 / 1024 < 10
      if (!isLt10M) {
        this.$message.error('文件大小不能超过 10MB')
      }
      return isLt10M
    },
    handleUploadSuccess(response) {
      if (response.code === 20000) {
        this.$message.success('上传成功')
        this.getList()
      } else {
        this.$message.error(response.message || '上传失败')
      }
    },
    handleUploadError() {
      this.$message.error('上传失败')
    },
    copyUrl(row) {
      const input = document.createElement('input')
      input.value = row.url
      document.body.appendChild(input)
      input.select()
      document.execCommand('copy')
      document.body.removeChild(input)
      this.$message.success('链接已复制')
    },
    handleDelete(row) {
      this.$confirm('确定删除该文件?', '提示', { type: 'warning' }).then(() => {
        deleteFile(row.id).then(() => {
          this.getList()
          this.$message.success('删除成功')
        })
      })
    }
  }
}
</script>
