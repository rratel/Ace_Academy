<script setup lang="ts">
import { RouterView, RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()

const navItems = [
  { name: '홈', path: '/student', icon: 'home' },
  { name: 'QR 출석', path: '/student/qr', icon: 'qr' },
  { name: '수강', path: '/student/enrollments', icon: 'book' },
  { name: '출석', path: '/student/attendances', icon: 'calendar' },
]

function isActive(path: string) {
  return route.path === path
}
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
    <main class="max-w-lg mx-auto pb-20">
      <RouterView />
    </main>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-40 safe-area-bottom">
      <div class="max-w-lg mx-auto px-4 py-2 flex justify-around">
        <RouterLink
          v-for="item in navItems"
          :key="item.path"
          :to="item.path"
          class="flex flex-col items-center py-1 px-3"
          :class="[
            isActive(item.path)
              ? 'text-primary-600'
              : 'text-gray-400 hover:text-gray-600'
          ]"
        >
          <!-- Home Icon -->
          <svg v-if="item.icon === 'home'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <!-- QR Icon -->
          <svg v-else-if="item.icon === 'qr'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
          </svg>
          <!-- Book Icon -->
          <svg v-else-if="item.icon === 'book'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
          <!-- Calendar Icon -->
          <svg v-else-if="item.icon === 'calendar'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <span class="text-xs mt-1">{{ item.name }}</span>
        </RouterLink>
      </div>
    </nav>
  </div>
</template>
