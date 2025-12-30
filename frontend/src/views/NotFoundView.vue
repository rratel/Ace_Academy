<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
    <div class="max-w-md w-full text-center">
      <div class="text-9xl font-bold text-gray-200">404</div>
      <h2 class="mt-4 text-2xl font-bold text-gray-900">페이지를 찾을 수 없습니다</h2>
      <p class="mt-2 text-gray-600">요청하신 페이지가 존재하지 않거나 이동되었습니다.</p>
      <RouterLink to="/" class="btn-primary mt-8 inline-block">
        홈으로 돌아가기
      </RouterLink>
    </div>
  </div>
</template>
