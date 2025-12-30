<script setup lang="ts">
import { RouterView, RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()
</script>

<template>
  <div class="min-h-screen bg-gray-50 safe-area-top safe-area-bottom">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
      <div class="max-w-lg mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-bold text-gray-900">Ace Academy</h1>
        <button
          @click="authStore.logout()"
          class="text-sm text-gray-500 hover:text-gray-700"
        >
          로그아웃
        </button>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-lg mx-auto">
      <RouterView />
    </main>
  </div>
</template>
