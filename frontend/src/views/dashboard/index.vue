<template>
  <div class="dashboard-container">
    <div class="welcome-card">
      <el-card shadow="hover">
        <div class="welcome-content">
          <div class="welcome-left">
            <div class="greeting">{{ greeting }}，{{ name }}</div>
            <div class="current-time">{{ currentTime }}</div>
          </div>
          <div class="welcome-right">
            <img :src="avatar" class="welcome-avatar">
          </div>
        </div>
      </el-card>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex'

export default {
  name: 'Dashboard',
  data() {
    return {
      currentTime: '',
      timer: null
    }
  },
  computed: {
    ...mapGetters(['name', 'avatar']),
    greeting() {
      const hour = new Date().getHours()
      if (hour < 6) return '凌晨好'
      if (hour < 9) return '早上好'
      if (hour < 12) return '上午好'
      if (hour < 14) return '中午好'
      if (hour < 17) return '下午好'
      if (hour < 19) return '傍晚好'
      return '晚上好'
    }
  },
  created() {
    this.updateTime()
    this.timer = setInterval(this.updateTime, 1000)
  },
  beforeDestroy() {
    clearInterval(this.timer)
  },
  methods: {
    updateTime() {
      const now = new Date()
      const pad = v => String(v).padStart(2, '0')
      const weekdays = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六']
      this.currentTime = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())} ${weekdays[now.getDay()]}`
    }
  }
}
</script>

<style lang="scss" scoped>
.dashboard-container {
  padding: 30px;
  background-color: #f0f2f5;
  min-height: calc(100vh - 84px);
}

.welcome-card {
  max-width: 800px;
  margin: 60px auto 0;
}

.welcome-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 10px;
}

.welcome-left {
  .greeting {
    font-size: 26px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 12px;
  }

  .current-time {
    font-size: 16px;
    color: #909399;
    letter-spacing: 1px;
  }
}

.welcome-right {
  .welcome-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
  }
}
</style>
