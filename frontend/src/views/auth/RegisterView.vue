<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { RouterLink, useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

const form = ref({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
})

const success = ref(false)

async function handleSubmit() {
  const result = await authStore.register(form.value)
  if (result) {
    success.value = true
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <!-- Logo and Title -->
      <div class="text-center">
        <div class="mx-auto h-16 w-16 bg-primary-600 rounded-2xl flex items-center justify-center">
          <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
        </div>
        <h2 class="mt-6 text-3xl font-bold text-gray-900">회원가입</h2>
        <p class="mt-2 text-sm text-gray-600">Ace Academy에 가입하세요</p>
      </div>

      <!-- Success Message -->
      <div v-if="success" class="rounded-lg bg-green-50 p-6 text-center">
        <svg class="mx-auto h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-green-800">가입 신청이 완료되었습니다</h3>
        <p class="mt-2 text-sm text-green-600">관리자 승인 후 로그인이 가능합니다.</p>
        <RouterLink to="/login" class="btn-primary mt-4 inline-block">
          로그인 페이지로
        </RouterLink>
      </div>

      <!-- Register Form -->
      <form v-else class="mt-8 space-y-6" @submit.prevent="handleSubmit">
        <div v-if="authStore.error" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
          {{ authStore.error }}
        </div>

        <div class="space-y-4">
          <div>
            <label for="name" class="label">이름</label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              required
              class="input"
              placeholder="홍길동"
            />
          </div>

          <div>
            <label for="email" class="label">이메일</label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              class="input"
              placeholder="example@email.com"
            />
          </div>

          <div>
            <label for="phone" class="label">연락처</label>
            <input
              id="phone"
              v-model="form.phone"
              type="tel"
              required
              class="input"
              placeholder="010-1234-5678"
            />
          </div>

          <div>
            <label for="password" class="label">비밀번호</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              minlength="8"
              class="input"
              placeholder="8자 이상"
            />
          </div>

          <div>
            <label for="password_confirmation" class="label">비밀번호 확인</label>
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              required
              class="input"
              placeholder="비밀번호 재입력"
            />
          </div>
        </div>

        <button
          type="submit"
          :disabled="authStore.loading"
          class="btn-primary w-full py-3"
        >
          {{ authStore.loading ? '처리 중...' : '가입 신청' }}
        </button>

        <div class="text-center">
          <p class="text-sm text-gray-600">
            이미 계정이 있으신가요?
            <RouterLink to="/login" class="font-medium text-primary-600 hover:text-primary-500">
              로그인
            </RouterLink>
          </p>
        </div>
      </form>
    </div>
  </div>
</template>
