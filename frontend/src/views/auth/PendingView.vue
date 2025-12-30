<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
    <div class="max-w-md w-full text-center">
      <div class="mx-auto h-20 w-20 bg-yellow-100 rounded-full flex items-center justify-center">
        <svg class="h-12 w-12 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>

      <h2 class="mt-6 text-2xl font-bold text-gray-900">승인 대기 중</h2>
      <p class="mt-4 text-gray-600">
        {{ authStore.user?.name }}님의 가입 신청이 접수되었습니다.<br />
        관리자 승인 후 서비스를 이용하실 수 있습니다.
      </p>

      <div class="mt-8 p-4 bg-gray-100 rounded-lg">
        <p class="text-sm text-gray-600">
          문의사항이 있으시면 학원으로 연락해 주세요.
        </p>
      </div>

      <button
        @click="authStore.logout()"
        class="btn-secondary mt-8"
      >
        로그아웃
      </button>
    </div>
  </div>
</template>
