<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useStudentStore } from '@/stores/student'
import QRCode from 'qrcode'

const router = useRouter()
const studentStore = useStudentStore()

const qrDataUrl = ref('')
const remainingTime = ref(30)
const loading = ref(false)
const error = ref('')
let refreshInterval: number | null = null
let countdownInterval: number | null = null

async function generateQr() {
  loading.value = true
  error.value = ''

  try {
    const response = await studentStore.getQrToken()
    const token = response.token

    // Generate QR code image
    qrDataUrl.value = await QRCode.toDataURL(token, {
      width: 256,
      margin: 2,
      color: {
        dark: '#1e3a8a',
        light: '#ffffff',
      },
    })

    remainingTime.value = response.expires_in || 30
  } catch (e: any) {
    if (e.response?.status === 401) {
      // Session expired, redirect to verify
      router.push('/student/verify')
      return
    }
    error.value = e.response?.data?.message || 'QR 코드 생성에 실패했습니다.'
  } finally {
    loading.value = false
  }
}

function startRefreshCycle() {
  generateQr()

  // Refresh QR every 30 seconds
  refreshInterval = window.setInterval(() => {
    generateQr()
  }, 30000)

  // Countdown timer
  countdownInterval = window.setInterval(() => {
    remainingTime.value = Math.max(0, remainingTime.value - 1)
  }, 1000)
}

function handleLogout() {
  studentStore.logout()
  router.push('/student/verify')
}

onMounted(() => {
  if (!studentStore.isAuthenticated) {
    router.push('/student/verify')
    return
  }
  startRefreshCycle()
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
  if (countdownInterval) clearInterval(countdownInterval)
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 safe-area-top safe-area-bottom">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
      <div class="max-w-lg mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-bold text-gray-900">Ace Academy</h1>
        <button
          @click="handleLogout"
          class="text-sm text-gray-500 hover:text-gray-700"
        >
          나가기
        </button>
      </div>
    </header>

    <!-- Main Content -->
    <main class="p-4 flex flex-col items-center justify-center min-h-[calc(100vh-120px)]">
      <!-- Student Name -->
      <p v-if="studentStore.student" class="text-lg font-medium text-gray-900 mb-2">
        {{ studentStore.student.name }} 학생
      </p>

      <h2 class="text-xl font-bold text-gray-900 mb-6">QR 출석</h2>

      <!-- QR Container -->
      <div class="bg-white rounded-2xl shadow-sm p-6 w-72 h-72 flex flex-col items-center justify-center relative">
        <div v-if="loading" class="animate-pulse bg-gray-200 w-64 h-64 rounded-lg"></div>
        <img v-else-if="qrDataUrl" :src="qrDataUrl" alt="QR Code" class="w-64 h-64" />
        <div v-else class="text-gray-400">QR 코드 생성 중...</div>

        <!-- Timer -->
        <div class="absolute bottom-2 right-2 bg-gray-100 px-3 py-1 rounded-full text-sm text-gray-600">
          {{ remainingTime }}초 후 갱신
        </div>
      </div>

      <!-- Error -->
      <div v-if="error" class="mt-4 text-sm text-red-600 text-center">
        {{ error }}
      </div>

      <!-- Instructions -->
      <div class="mt-6 text-center">
        <p class="text-sm text-gray-600">
          리더기에 QR 코드를 스캔해주세요
        </p>
        <p class="text-xs text-gray-400 mt-1">
          30초마다 자동으로 새로운 QR 코드가 생성됩니다
        </p>
      </div>

      <!-- Manual Refresh -->
      <button
        @click="generateQr"
        :disabled="loading"
        class="mt-4 px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50"
      >
        새로 고침
      </button>
    </main>
  </div>
</template>
