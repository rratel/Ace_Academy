<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const authStore = useAuthStore()

interface DashboardData {
  statistics: {
    total_students: number
    pending_approvals: number
    active_lessons: number
    today_attendances: number
    active_enrollments: number
  }
  recent_attendances: Array<{
    student_name: string
    lesson: string
    status: string
    time: string
  }>
  pending_enrollments: Array<{
    id: number
    student_name: string
    lesson: string
    requested_at: string
  }>
}

const data = ref<DashboardData | null>(null)
const loading = ref(true)
const error = ref('')

const statusLabels: Record<string, string> = {
  present: '출석',
  late: '지각',
  absent: '결석',
  excused: '인정결석'
}

async function loadDashboard() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/admin/dashboard')
    data.value = response.data
  } catch (e: any) {
    error.value = e.response?.data?.message || '대시보드 데이터를 불러오는데 실패했습니다.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadDashboard()
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">대시보드</h1>

    <!-- Error -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
      {{ error }}
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="card">
        <div class="card-body">
          <p class="text-sm text-gray-500">총 학생 수</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">
            {{ loading ? '-' : data?.statistics.total_students ?? 0 }}명
          </p>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <p class="text-sm text-gray-500">오늘 출석</p>
          <p class="text-2xl font-bold text-green-600 mt-1">
            {{ loading ? '-' : data?.statistics.today_attendances ?? 0 }}명
          </p>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <p class="text-sm text-gray-500">승인 대기</p>
          <p class="text-2xl font-bold mt-1" :class="(data?.statistics.pending_approvals ?? 0) > 0 ? 'text-yellow-600' : 'text-gray-900'">
            {{ loading ? '-' : data?.statistics.pending_approvals ?? 0 }}명
          </p>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <p class="text-sm text-gray-500">활성 수업</p>
          <p class="text-2xl font-bold text-primary-600 mt-1">
            {{ loading ? '-' : data?.statistics.active_lessons ?? 0 }}개
          </p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Recent Attendances -->
      <div class="card">
        <div class="card-header flex justify-between items-center">
          <h3 class="text-sm font-semibold text-gray-900">최근 출석</h3>
          <RouterLink to="/admin/attendances" class="text-xs text-primary-600 hover:text-primary-700">전체보기</RouterLink>
        </div>
        <div class="divide-y divide-gray-100">
          <div v-if="loading" class="p-4 text-center text-gray-500">불러오는 중...</div>
          <div v-else-if="!data?.recent_attendances?.length" class="p-4 text-center text-gray-500">
            오늘 출석 기록이 없습니다.
          </div>
          <div v-else v-for="(att, idx) in data.recent_attendances" :key="idx" class="p-3 flex justify-between items-center">
            <div>
              <p class="text-sm font-medium text-gray-900">{{ att.student_name }}</p>
              <p class="text-xs text-gray-500">{{ att.lesson }}</p>
            </div>
            <div class="text-right">
              <span class="text-xs" :class="{
                'text-green-600': att.status === 'present',
                'text-yellow-600': att.status === 'late',
                'text-red-600': att.status === 'absent'
              }">{{ statusLabels[att.status] || att.status }}</span>
              <p class="text-xs text-gray-400">{{ att.time }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Pending Enrollments -->
      <div class="card">
        <div class="card-header flex justify-between items-center">
          <h3 class="text-sm font-semibold text-gray-900">수강 신청 대기</h3>
          <RouterLink to="/admin/users?status=pending" class="text-xs text-primary-600 hover:text-primary-700">전체보기</RouterLink>
        </div>
        <div class="divide-y divide-gray-100">
          <div v-if="loading" class="p-4 text-center text-gray-500">불러오는 중...</div>
          <div v-else-if="!data?.pending_enrollments?.length" class="p-4 text-center text-gray-500">
            대기 중인 수강 신청이 없습니다.
          </div>
          <div v-else v-for="enroll in data.pending_enrollments" :key="enroll.id" class="p-3 flex justify-between items-center">
            <div>
              <p class="text-sm font-medium text-gray-900">{{ enroll.student_name }}</p>
              <p class="text-xs text-gray-500">{{ enroll.lesson }}</p>
            </div>
            <p class="text-xs text-gray-400">{{ enroll.requested_at }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="card mt-6">
      <div class="card-header">
        <h3 class="text-sm font-semibold text-gray-900">빠른 작업</h3>
      </div>
      <div class="card-body">
        <div class="flex flex-wrap gap-2">
          <RouterLink to="/admin/qr-reader" class="btn-primary">
            QR 리더기 열기
          </RouterLink>
          <RouterLink to="/admin/users" class="btn-secondary">
            사용자 관리
          </RouterLink>
          <RouterLink to="/admin/lessons" class="btn-secondary">
            수업 관리
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>
