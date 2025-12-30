<script setup lang="ts">
import { ref } from 'vue'
import { RouterView, RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()
const sidebarOpen = ref(false)

const navItems = [
  { name: '대시보드', path: '/admin', icon: 'home' },
  { name: '학생 관리', path: '/admin/students', icon: 'users' },
  { name: '사용자 관리', path: '/admin/users', icon: 'user-group' },
  { name: '수업 관리', path: '/admin/lessons', icon: 'book' },
  { name: '출결 관리', path: '/admin/attendances', icon: 'calendar' },
  { name: '환급 관리', path: '/admin/refunds', icon: 'cash' },
  { name: 'QR 리더기', path: '/admin/qr-reader', icon: 'qr' },
]

const superAdminItems = [
  { name: '지점 관리', path: '/admin/branches', icon: 'office' },
]

function isActive(path: string) {
  return route.path === path
}
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Mobile Header -->
    <header class="lg:hidden bg-white border-b border-gray-200 sticky top-0 z-40">
      <div class="px-4 py-3 flex items-center justify-between">
        <button @click="sidebarOpen = true" class="p-2 -ml-2">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <h1 class="text-lg font-bold text-gray-900">Ace Academy</h1>
        <div class="w-10"></div>
      </div>
    </header>

    <!-- Sidebar Overlay -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 bg-black/50 z-40 lg:hidden"
      @click="sidebarOpen = false"
    ></div>

    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 w-64 bg-white border-r border-gray-200 z-50 transform transition-transform lg:translate-x-0"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="h-full flex flex-col">
        <!-- Logo -->
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex items-center space-x-3">
            <div class="h-10 w-10 bg-primary-600 rounded-xl flex items-center justify-center">
              <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
            </div>
            <div>
              <h1 class="font-bold text-gray-900">Ace Academy</h1>
              <p class="text-xs text-gray-500">관리자</p>
            </div>
          </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
          <RouterLink
            v-for="item in navItems"
            :key="item.path"
            :to="item.path"
            @click="sidebarOpen = false"
            class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors"
            :class="[
              isActive(item.path)
                ? 'bg-primary-50 text-primary-700'
                : 'text-gray-600 hover:bg-gray-50'
            ]"
          >
            {{ item.name }}
          </RouterLink>

          <!-- Super Admin Items -->
          <template v-if="authStore.isSuperAdmin">
            <div class="pt-4 mt-4 border-t border-gray-200">
              <p class="px-3 mb-2 text-xs font-semibold text-gray-400 uppercase">최고 관리자</p>
              <RouterLink
                v-for="item in superAdminItems"
                :key="item.path"
                :to="item.path"
                @click="sidebarOpen = false"
                class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                :class="[
                  isActive(item.path)
                    ? 'bg-primary-50 text-primary-700'
                    : 'text-gray-600 hover:bg-gray-50'
                ]"
              >
                {{ item.name }}
              </RouterLink>
            </div>
          </template>
        </nav>

        <!-- User Info -->
        <div class="px-4 py-4 border-t border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-900">{{ authStore.user?.name }}</p>
              <p class="text-xs text-gray-500">{{ authStore.user?.branch?.name || '본사' }}</p>
            </div>
            <button
              @click="authStore.logout()"
              class="text-sm text-gray-500 hover:text-gray-700"
            >
              로그아웃
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="lg:pl-64">
      <div class="p-6">
        <RouterView />
      </div>
    </main>
  </div>
</template>
