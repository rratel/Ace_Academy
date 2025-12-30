<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { RouterLink } from 'vue-router'

const authStore = useAuthStore()

const email = ref('')
const password = ref('')

async function handleSubmit() {
  await authStore.login(email.value, password.value)
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
        <h2 class="mt-6 text-3xl font-bold text-gray-900">Ace Academy</h2>
        <p class="mt-2 text-sm text-gray-600">스마트 학원 출결 관리 시스템</p>
      </div>

      <!-- Login Form -->
      <form class="mt-8 space-y-6" @submit.prevent="handleSubmit">
        <div v-if="authStore.error" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
          {{ authStore.error }}
        </div>

        <div class="space-y-4">
          <div>
            <label for="email" class="label">이메일</label>
            <input
              id="email"
              v-model="email"
              type="email"
              autocomplete="email"
              required
              class="input"
              placeholder="example@email.com"
            />
          </div>

          <div>
            <label for="password" class="label">비밀번호</label>
            <input
              id="password"
              v-model="password"
              type="password"
              autocomplete="current-password"
              required
              class="input"
              placeholder="••••••••"
            />
          </div>
        </div>

        <button
          type="submit"
          :disabled="authStore.loading"
          class="btn-primary w-full py-3"
        >
          <svg
            v-if="authStore.loading"
            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          {{ authStore.loading ? '로그인 중...' : '로그인' }}
        </button>

        <div class="text-center">
          <p class="text-sm text-gray-600">
            계정이 없으신가요?
            <RouterLink to="/register" class="font-medium text-primary-600 hover:text-primary-500">
              회원가입
            </RouterLink>
          </p>
        </div>
      </form>
    </div>
  </div>
</template>
