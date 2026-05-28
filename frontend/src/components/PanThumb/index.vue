<template>
  <div class="pan-thumb" :style="thumbStyle">
    <img class="pan-thumb-image" :src="image" alt="avatar" />
    <div v-if="hoverable" class="pan-thumb-mask">
      <slot />
    </div>
  </div>
</template>

<script>
export default {
  name: 'PanThumb',
  props: {
    image: {
      type: String,
      default: ''
    },
    width: {
      type: String,
      default: '100px'
    },
    height: {
      type: String,
      default: '100px'
    },
    hoverable: {
      type: Boolean,
      default: true
    }
  },
  computed: {
    thumbStyle() {
      return {
        width: this.width,
        height: this.height
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.pan-thumb {
  position: relative;
  border-radius: 50%;
  overflow: hidden;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #f5f7fa;
}

.pan-thumb-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pan-thumb-mask {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  color: #fff;
  background: rgba(0, 0, 0, 0.35);
  opacity: 0;
  transition: opacity 0.2s ease;
}

.pan-thumb:hover .pan-thumb-mask {
  opacity: 1;
}
</style>
