<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { studentApi } from '@/services/api'

const authStore = useAuthStore()

interface Enrollment {
  id: number
  lesson_name: string
  days: string[]
  time: string
  status: string
}

const enrollments = ref<Enrollment[]>([])
const loading = ref(true)
const error = ref('')

const dayLabels: Record<string, string> = {
  monday: '월', tuesday: '화', wednesday: '수',
  thursday: '목', friday: '금', saturday: '토', sunday: '일'
}

async function loadEnrollments() {
  loading.value = true
  try {
    const response = await studentApi.enrollments()
    enrollments.value = response.data.enrollments || []
  } catch (e: any) {
    error.value = e.response?.data?.message || '수강 정보를 불러오는데 실패했습니다.'
  } finally {
    loading.value = false
  }
}

function formatDays(days: string[]): string {
  if (!days) return '-'
  return days.map(d => dayLabels[d] || d).join(', ')
}

onMounted(() => {
  loadEnrollments()
})
</script>

<template>
  <div class="p-4 space-y-4">
    <!-- Welcome -->
    <div class="card">
      <div class="card-body">
        <h2 class="text-xl font-bold text-gray-900">안녕하세요, {{ authStore.user?.name }}님!</h2>
        <p class="text-sm text-gray-600 mt-1">오늘도 화이팅하세요</p>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 gap-4">
      <RouterLink to="/student/qr" class="card hover:shadow-md transition-shadow">
        <div class="card-body text-center py-6">
          <div class="mx-auto h-12 w-12 bg-primary-100 rounded-full flex items-center justify-center">
            <svg class="h-6 w-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
            </svg>
          </div>
          <p class="mt-2 text-sm font-medium text-gray-900">QR 출석</p>
        </div>
      </RouterLink>

      <RouterLink to="/student/attendances" class="card hover:shadow-md transition-shadow">
        <div class="card-body text-center py-6">
          <div class="mx-auto h-12 w-12 bg-green-100 rounded-full flex items-center justify-center">
            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
          </div>
          <p class="mt-2 text-sm font-medium text-gray-900">출석 현황</p>
        </div>
      </RouterLink>
    </div>

    <!-- Current Enrollments -->
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h3 class="text-sm font-semibold text-gray-900">수강 중인 수업</h3>
        <RouterLink to="/student/enrollments" class="text-xs text-primary-600 hover:text-primary-700">전체보기</RouterLink>
      </div>
      <div class="divide-y divide-gray-100">
        <div v-if="loading" class="p-4 text-center text-gray-500">불러오는 중...</div>
        <div v-else-if="error" class="p-4 text-center text-red-500">{{ error }}</div>
        <div v-else-if="enrollments.length === 0" class="p-4 text-center text-gray-500">
          수강 중인 수업이 없습니다.
        </div>
        <div v-else v-for="enroll in enrollments.slice(0, 3)" :key="enroll.id" class="p-3 flex justify-between items-center">
          <div>
            <p class="text-sm font-medium text-gray-900">{{ enroll.lesson_name }}</p>
            <p class="text-xs text-gray-500">{{ formatDays(enroll.days) }} {{ enroll.time }}</p>
          </div>
          <span class="badge badge-success">수강중</span>
        </div>
      </div>
    </div>
  </div>
</template>
