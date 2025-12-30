<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Html5Qrcode } from 'html5-qrcode'
import { qrApi } from '@/services/api'

const scanResult = ref<{
  success: boolean
  student_name?: string
  lesson?: string
  message?: string
} | null>(null)

const scanning = ref(false)
const cameraReady = ref(false)
const cameraError = ref('')
const lastScannedToken = ref('')

let html5Qrcode: Html5Qrcode | null = null

async function startCamera() {
  try {
    cameraError.value = ''
    html5Qrcode = new Html5Qrcode('qr-reader')

    await html5Qrcode.start(
      { facingMode: 'environment' },
      {
        fps: 10,
        qrbox: { width: 250, height: 250 },
      },
      onScanSuccess,
      onScanFailure
    )

    cameraReady.value = true
  } catch (err: any) {
    console.error('Camera error:', err)
    if (err.name === 'NotAllowedError') {
      cameraError.value = '카메라 권한이 거부되었습니다. 브라우저 설정에서 카메라 권한을 허용해주세요.'
    } else if (err.name === 'NotFoundError') {
      cameraError.value = '카메라를 찾을 수 없습니다.'
    } else {
      cameraError.value = `카메라 시작 오류: ${err.message || err}`
    }
  }
}

async function stopCamera() {
  if (html5Qrcode && cameraReady.value) {
    try {
      await html5Qrcode.stop()
      cameraReady.value = false
    } catch (err) {
      console.error('Stop camera error:', err)
    }
  }
}

async function onScanSuccess(decodedText: string) {
  // Prevent duplicate scans of the same token
  if (decodedText === lastScannedToken.value || scanning.value) {
    return
  }

  lastScannedToken.value = decodedText
  scanning.value = true

  try {
    const response = await qrApi.validate(decodedText)
    scanResult.value = response.data

    // Play success sound (optional)
    playBeep(true)

    // Clear result after 3 seconds
    setTimeout(() => {
      scanResult.value = null
      lastScannedToken.value = ''
    }, 3000)
  } catch (e: any) {
    scanResult.value = {
      success: false,
      message: e.response?.data?.message || '스캔에 실패했습니다.',
    }

    playBeep(false)

    setTimeout(() => {
      scanResult.value = null
      lastScannedToken.value = ''
    }, 3000)
  } finally {
    scanning.value = false
  }
}

function onScanFailure(_error: string) {
  // Silently ignore scan failures (no QR code in view)
}

function playBeep(success: boolean) {
  try {
    const audioContext = new AudioContext()
    const oscillator = audioContext.createOscillator()
    const gainNode = audioContext.createGain()

    oscillator.connect(gainNode)
    gainNode.connect(audioContext.destination)

    oscillator.frequency.value = success ? 800 : 300
    oscillator.type = 'sine'
    gainNode.gain.value = 0.3

    oscillator.start()
    oscillator.stop(audioContext.currentTime + 0.15)
  } catch (e) {
    // Audio not supported
  }
}

async function requestCameraPermission() {
  try {
    await navigator.mediaDevices.getUserMedia({ video: true })
    await startCamera()
  } catch (err: any) {
    if (err.name === 'NotAllowedError') {
      cameraError.value = '카메라 권한이 거부되었습니다. 브라우저 설정에서 카메라 권한을 허용해주세요.'
    } else {
      cameraError.value = `카메라 접근 오류: ${err.message || err}`
    }
  }
}

onMounted(() => {
  requestCameraPermission()
})

onUnmounted(() => {
  stopCamera()
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">QR 리더기</h1>

    <div class="max-w-lg mx-auto">
      <!-- Scanner Area -->
      <div class="card">
        <div class="card-body">
          <!-- Camera Error -->
          <div v-if="cameraError" class="text-center py-8">
            <svg class="mx-auto h-16 w-16 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="mt-4 text-red-600">{{ cameraError }}</p>
            <button
              @click="requestCameraPermission"
              class="mt-4 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
            >
              다시 시도
            </button>
          </div>

          <!-- QR Scanner -->
          <div v-else class="relative">
            <div id="qr-reader" class="w-full rounded-lg overflow-hidden"></div>

            <!-- Loading overlay -->
            <div v-if="!cameraReady" class="absolute inset-0 bg-gray-900 flex items-center justify-center rounded-lg">
              <div class="text-center text-white">
                <svg class="animate-spin mx-auto h-10 w-10" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-4 text-sm">카메라 연결 중...</p>
              </div>
            </div>

            <!-- Scanning indicator -->
            <div v-if="scanning" class="absolute inset-0 bg-black/50 flex items-center justify-center rounded-lg">
              <div class="text-white text-center">
                <svg class="animate-spin mx-auto h-8 w-8" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <p class="mt-2 text-sm">확인 중...</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Result Display -->
      <div
        v-if="scanResult"
        class="mt-4 p-4 rounded-lg transition-all"
        :class="scanResult.success ? 'bg-green-100' : 'bg-red-100'"
      >
        <div class="flex items-center">
          <svg
            v-if="scanResult.success"
            class="h-10 w-10 text-green-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <svg v-else class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="ml-4">
            <p
              class="text-lg font-bold"
              :class="scanResult.success ? 'text-green-800' : 'text-red-800'"
            >
              {{ scanResult.success ? '출석 완료!' : '스캔 실패' }}
            </p>
            <p
              class="text-sm"
              :class="scanResult.success ? 'text-green-600' : 'text-red-600'"
            >
              {{ scanResult.success ? `${scanResult.student_name} - ${scanResult.lesson}` : scanResult.message }}
            </p>
          </div>
        </div>
      </div>

      <!-- Instructions -->
      <div class="mt-4 text-center text-sm text-gray-500">
        <p>학생의 QR 코드를 카메라에 비춰주세요</p>
        <p class="mt-1 text-xs text-gray-400">자동으로 스캔됩니다</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
#qr-reader {
  border: none !important;
}

#qr-reader video {
  border-radius: 0.5rem;
}

:deep(#qr-reader__scan_region) {
  background: transparent !important;
}

:deep(#qr-reader__dashboard) {
  display: none !important;
}
</style>
