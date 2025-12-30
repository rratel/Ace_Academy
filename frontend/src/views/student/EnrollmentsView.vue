<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { studentApi } from '@/services/api'

interface Enrollment {
  id: number
  lesson_name: string
  lesson_description: string | null
  days: string[]
  time: string
  price: number
  status: string
  enrolled_at: string
}

const enrollments = ref<Enrollment[]>([])
const loading = ref(true)
const error = ref('')

const dayLabels: Record<string, string> = {
  monday: '월', tuesday: '화', wednesday: '수',
  thursday: '목', friday: '금', saturday: '토', sunday: '일'
}

const statusLabels: Record<string, string> = {
  pending: '승인대기',
  approved: '수강중',
  rejected: '거절됨',
  cancelled: '취소됨'
}

const statusColors: Record<string, string> = {
  pending: 'badge-warning',
  approved: 'badge-success',
  rejected: 'badge-danger',
  cancelled: 'badge-info'
}

async function loadEnrollments() {
  loading.value = true
  error.value = ''
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

function formatPrice(price: number): string {
  return new Intl.NumberFormat('ko-KR').format(price) + '원'
}

onMounted(() => {
  loadEnrollments()
})
</script>

<template>
  <div class="p-4">
    <h2 class="text-xl font-bold text-gray-900 mb-4">수강 목록</h2>

    <!-- Error -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
      {{ error }}
    </div>

    <!-- Enrollments List -->
    <div class="space-y-3">
      <div v-if="loading" class="card">
        <div class="card-body text-center py-8">
          <p class="text-gray-500">불러오는 중...</p>
        </div>
      </div>
      <div v-else-if="enrollments.length === 0" class="card">
        <div class="card-body text-center py-8">
          <p class="text-gray-500">수강 중인 수업이 없습니다.</p>
          <p class="text-sm text-gray-400 mt-1">관리자에게 수강 신청을 문의해주세요.</p>
        </div>
      </div>
      <div v-else v-for="enroll in enrollments" :key="enroll.id" class="card">
        <div class="card-body">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="font-semibold text-gray-900">{{ enroll.lesson_name }}</h3>
              <p v-if="enroll.lesson_description" class="text-sm text-gray-500 mt-1">
                {{ enroll.lesson_description }}
              </p>
            </div>
            <span :class="['badge', statusColors[enroll.status]]">
              {{ statusLabels[enroll.status] || enroll.status }}
            </span>
          </div>

          <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
            <div>
              <span class="text-gray-500">수업일:</span>
              <span class="ml-1 text-gray-900">{{ formatDays(enroll.days) }}</span>
            </div>
            <div>
              <span class="text-gray-500">시간:</span>
              <span class="ml-1 text-gray-900">{{ enroll.time }}</span>
            </div>
            <div>
              <span class="text-gray-500">수강료:</span>
              <span class="ml-1 text-gray-900">{{ formatPrice(enroll.price) }}</span>
            </div>
            <div>
              <span class="text-gray-500">등록일:</span>
              <span class="ml-1 text-gray-900">{{ enroll.enrolled_at }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
