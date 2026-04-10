<template>
  <div class="app-container">
    <el-row :gutter="20">
      <!-- 左侧：头像和基本信息 -->
      <el-col :span="8" :xs="24">
        <el-card>
          <div style="text-align: center; padding: 20px 0;">
            <div class="avatar-wrapper" @click="triggerAvatarUpload">
              <el-avatar :size="100" :src="userInfo.avatar" />
              <div class="avatar-hover">
                <i class="el-icon-camera" />
                <span>更换头像</span>
              </div>
            </div>
            <input ref="avatarInput" type="file" accept="image/*" style="display: none;" @change="handleAvatarChange">
            <h3 style="margin: 15px 0 5px;">{{ userInfo.nickname || userInfo.name }}</h3>
            <div style="color: #909399;">
              <el-tag v-for="role in roles" :key="role" size="small" style="margin: 2px;">{{ role }}</el-tag>
            </div>
          </div>
        </el-card>
      </el-col>

      <!-- 右侧：编辑表单 -->
      <el-col :span="16" :xs="24">
        <el-card>
          <el-tabs v-model="activeTab">
            <el-tab-pane label="基本信息" name="info">
              <el-form ref="infoForm" :model="infoForm" :rules="infoRules" label-width="80px" style="max-width: 500px; padding: 20px 0;">
                <el-form-item label="昵称" prop="nickname">
                  <el-input v-model="infoForm.nickname" />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" :loading="infoLoading" @click="submitInfo">保存修改</el-button>
                </el-form-item>
              </el-form>
            </el-tab-pane>

            <el-tab-pane label="修改密码" name="password">
              <el-form ref="pwdForm" :model="pwdForm" :rules="pwdRules" label-width="100px" style="max-width: 500px; padding: 20px 0;">
                <el-form-item label="原密码" prop="old_password">
                  <el-input v-model="pwdForm.old_password" type="password" show-password />
                </el-form-item>
                <el-form-item label="新密码" prop="new_password">
                  <el-input v-model="pwdForm.new_password" type="password" show-password />
                </el-form-item>
                <el-form-item label="确认密码" prop="new_password_confirmation">
                  <el-input v-model="pwdForm.new_password_confirmation" type="password" show-password />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" :loading="pwdLoading" @click="submitPassword">修改密码</el-button>
                </el-form-item>
              </el-form>
            </el-tab-pane>
          </el-tabs>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import { mapGetters } from 'vuex'
import { updateProfile, updatePassword, uploadAvatar } from '@/api/system'

export default {
  name: 'Profile',
  data() {
    const validateConfirm = (rule, value, callback) => {
      if (value !== this.pwdForm.new_password) {
        callback(new Error('两次输入密码不一致'))
      } else {
        callback()
      }
    }
    return {
      activeTab: 'info',
      userInfo: { name: '', nickname: '', avatar: '' },
      infoForm: { nickname: '' },
      infoLoading: false,
      infoRules: {
        nickname: [{ required: true, message: '请输入昵称', trigger: 'blur' }]
      },
      pwdForm: { old_password: '', new_password: '', new_password_confirmation: '' },
      pwdLoading: false,
      pwdRules: {
        old_password: [{ required: true, message: '请输入原密码', trigger: 'blur' }],
        new_password: [{ required: true, message: '请输入新密码', trigger: 'blur' }, { min: 6, message: '密码长度不少于6位', trigger: 'blur' }],
        new_password_confirmation: [{ required: true, message: '请确认密码', trigger: 'blur' }, { validator: validateConfirm, trigger: 'blur' }]
      }
    }
  },
  computed: {
    ...mapGetters(['name', 'avatar', 'roles'])
  },
  created() {
    this.userInfo = { name: this.name, nickname: this.name, avatar: this.avatar }
    this.infoForm = { nickname: this.name }
    // 从 store 获取 info
    this.$store.dispatch('user/getInfo').then(data => {
      this.userInfo.name = data.name
      this.userInfo.nickname = data.name
      this.userInfo.avatar = data.avatar
      this.infoForm.nickname = data.name
    })
  },
  methods: {
    submitInfo() {
      this.$refs.infoForm.validate(valid => {
        if (valid) {
          this.infoLoading = true
          updateProfile(this.infoForm).then(() => {
            this.$message.success('修改成功')
            this.$store.dispatch('user/getInfo')
            this.userInfo.nickname = this.infoForm.nickname
          }).finally(() => { this.infoLoading = false })
        }
      })
    },
    submitPassword() {
      this.$refs.pwdForm.validate(valid => {
        if (valid) {
          this.pwdLoading = true
          updatePassword(this.pwdForm).then(() => {
            this.$message.success('密码修改成功，请重新登录')
            this.pwdForm = { old_password: '', new_password: '', new_password_confirmation: '' }
            setTimeout(() => {
              this.$store.dispatch('user/logout').then(() => {
                this.$router.push('/login')
              })
            }, 1500)
          }).finally(() => { this.pwdLoading = false })
        }
      })
    },
    triggerAvatarUpload() {
      this.$refs.avatarInput.click()
    },
    handleAvatarChange(e) {
      const file = e.target.files[0]
      if (!file) return
      if (file.size > 2 * 1024 * 1024) {
        this.$message.error('头像大小不能超过 2MB')
        return
      }
      const formData = new FormData()
      formData.append('avatar', file)
      uploadAvatar(formData).then(response => {
        this.userInfo.avatar = response.data.url
        this.$store.dispatch('user/getInfo')
        this.$message.success('头像更新成功')
      })
      e.target.value = ''
    }
  }
}
</script>

<style scoped>
.avatar-wrapper {
  position: relative;
  display: inline-block;
  cursor: pointer;
  border-radius: 50%;
  overflow: hidden;
}
.avatar-hover {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  color: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s;
  border-radius: 50%;
}
.avatar-wrapper:hover .avatar-hover {
  opacity: 1;
}
.avatar-hover i {
  font-size: 24px;
  margin-bottom: 4px;
}
.avatar-hover span {
  font-size: 12px;
}
</style>
