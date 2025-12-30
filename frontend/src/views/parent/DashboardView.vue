<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { parentApi } from '@/services/api'

const authStore = useAuthStore()

interface Child {
  id: number
  name: string
  email: string
  phone: string
  enrollments_count: number
  recent_attendance: {
    date: string
    status: string
  } | null
}

const children = ref<Child[]>([])
const loading = ref(true)
const error = ref('')

const statusLabels: Record<string, string> = {
  present: '출석',
  late: '지각',
  absent: '결석',
  excused: '인정결석'
}

const statusColors: Record<string, string> = {
  present: 'text-green-600',
  late: 'text-yellow-600',
  absent: 'text-red-600',
  excused: 'text-blue-600'
}

async function loadChildren() {
  loading.value = true
  error.value = ''
  try {
    const response = await parentApi.children()
    children.value = response.data.children || []
  } catch (e: any) {
    error.value = e.response?.data?.message || '자녀 정보를 불러오는데 실패했습니다.'
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr: string): string {
  const date = new Date(dateStr)
  return `${date.getMonth() + 1}/${date.getDate()}`
}

onMounted(() => {
  loadChildren()
})
</script>

<template>
  <div class="p-4 space-y-4">
    <!-- Welcome -->
    <div class="card">
      <div class="card-body">
        <h2 class="text-xl font-bold text-gray-900">안녕하세요, {{ authStore.user?.name }}님!</h2>
        <p class="text-sm text-gray-600 mt-1">자녀의 학원 생활을 확인해보세요</p>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
      {{ error }}
    </div>

    <!-- Children List -->
    <div class="card">
      <div class="card-header">
        <h3 class="text-sm font-semibold text-gray-900">내 자녀</h3>
      </div>
      <div class="divide-y divide-gray-100">
        <div v-if="loading" class="p-4 text-center text-gray-500">
          불러오는 중...
        </div>
        <div v-else-if="children.length === 0" class="p-4 text-center text-gray-500">
          <p>등록된 자녀가 없습니다.</p>
          <p class="text-xs text-gray-400 mt-1">관리자에게 자녀 연결을 요청해주세요.</p>
        </div>
        <RouterLink
          v-else
          v-for="child in children"
          :key="child.id"
          :to="`/parent/children/${child.id}`"
          class="block p-4 hover:bg-gray-50 transition-colors"
        >
          <div class="flex justify-between items-center">
            <div class="flex items-center space-x-3">
              <div class="h-10 w-10 bg-primary-100 rounded-full flex items-center justify-center">
                <span class="text-primary-600 font-semibold">{{ child.name.charAt(0) }}</span>
              </div>
              <div>
                <p class="font-medium text-gray-900">{{ child.name }}</p>
                <p class="text-xs text-gray-500">수강 {{ child.enrollments_count }}개</p>
              </div>
            </div>
            <div class="text-right">
              <div v-if="child.recent_attendance" class="text-xs">
                <p class="text-gray-400">{{ formatDate(child.recent_attendance.date) }}</p>
                <p :class="statusColors[child.recent_attendance.status]">
                  {{ statusLabels[child.recent_attendance.status] || child.recent_attendance.status }}
                </p>
              </div>
              <div v-else class="text-xs text-gray-400">
                출석 기록 없음
              </div>
              <svg class="h-5 w-5 text-gray-400 ml-auto mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </div>
          </div>
        </RouterLink>
      </div>
    </div>

    <!-- Quick Info -->
    <div class="card">
      <div class="card-body">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">이용 안내</h3>
        <ul class="space-y-2 text-sm text-gray-600">
          <li class="flex items-start">
            <svg class="h-4 w-4 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            자녀를 선택하면 수강 및 출석 현황을 확인할 수 있습니다.
          </li>
          <li class="flex items-start">
            <svg class="h-4 w-4 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            출석 알림은 자녀가 QR 체크인 시 자동으로 발송됩니다.
          </li>
          <li class="flex items-start">
            <svg class="h-4 w-4 text-primary-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            문의사항은 학원으로 연락해주세요.
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
