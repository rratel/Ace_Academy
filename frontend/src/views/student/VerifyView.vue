<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useStudentStore } from '@/stores/student'

const router = useRouter()
const studentStore = useStudentStore()

const name = ref('')
const phone = ref('')
const loading = ref(false)
const error = ref('')

async function handleVerify() {
  if (!name.value.trim() || !phone.value.trim()) {
    error.value = '이름과 핸드폰 번호를 입력해주세요.'
    return
  }

  loading.value = true
  error.value = ''

  try {
    await studentStore.verify(name.value.trim(), phone.value.trim())
    router.push('/student/qr')
  } catch (e: any) {
    error.value = e.response?.data?.message || '인증에 실패했습니다.'
  } finally {
    loading.value = false
  }
}

function formatPhone(e: Event) {
  const input = e.target as HTMLInputElement
  let value = input.value.replace(/[^0-9]/g, '')

  if (value.length > 11) {
    value = value.slice(0, 11)
  }

  if (value.length >= 8) {
    value = value.replace(/(\d{3})(\d{4})(\d{0,4})/, '$1-$2-$3')
  } else if (value.length >= 4) {
    value = value.replace(/(\d{3})(\d{0,4})/, '$1-$2')
  }

  phone.value = value
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-sm">
      <!-- Logo/Header -->
      <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Ace Academy</h1>
        <p class="text-gray-500 mt-2">QR 출석 인증</p>
      </div>

      <!-- Verify Form -->
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <form @submit.prevent="handleVerify" class="space-y-4">
          <!-- Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              이름
            </label>
            <input
              v-model="name"
              type="text"
              placeholder="홍길동"
              class="input-field"
              :disabled="loading"
            />
          </div>

          <!-- Phone -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              핸드폰 번호
            </label>
            <input
              :value="phone"
              @input="formatPhone"
              type="tel"
              placeholder="010-1234-5678"
              class="input-field"
              :disabled="loading"
            />
          </div>

          <!-- Error -->
          <div v-if="error" class="text-sm text-red-600 text-center">
            {{ error }}
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="loading"
            class="w-full btn-primary"
          >
            <span v-if="loading">확인 중...</span>
            <span v-else>QR 코드 발급</span>
          </button>
        </form>
      </div>

      <!-- Info -->
      <p class="text-center text-xs text-gray-400 mt-6">
        등록된 학생 정보로 인증이 필요합니다.<br/>
        문의: 학원 관리자에게 연락해주세요.
      </p>
    </div>
  </div>
</template>

<style scoped>
.input-field {
  @apply w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none transition-all;
}

.btn-primary {
  @apply px-4 py-3 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors;
}
</style>
