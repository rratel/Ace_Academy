<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { studentApi } from '@/services/api'

interface Attendance {
  id: number
  lesson_name: string
  date: string
  status: 'present' | 'late' | 'absent' | 'excused'
  check_in_time: string | null
  note: string | null
}

interface MonthlyStats {
  present: number
  late: number
  absent: number
  excused: number
  total: number
}

const attendances = ref<Attendance[]>([])
const loading = ref(true)
const error = ref('')
const selectedMonth = ref(new Date().toISOString().slice(0, 7))

const statusLabels: Record<string, string> = {
  present: '출석',
  late: '지각',
  absent: '결석',
  excused: '인정결석'
}

const statusColors: Record<string, string> = {
  present: 'badge-success',
  late: 'badge-warning',
  absent: 'badge-danger',
  excused: 'badge-info'
}

const monthlyStats = computed<MonthlyStats>(() => {
  const stats: MonthlyStats = { present: 0, late: 0, absent: 0, excused: 0, total: 0 }
  attendances.value.forEach(att => {
    if (stats[att.status] !== undefined) {
      stats[att.status]++
    }
    stats.total++
  })
  return stats
})

const attendanceRate = computed(() => {
  if (monthlyStats.value.total === 0) return 0
  return Math.round((monthlyStats.value.present / monthlyStats.value.total) * 100)
})

async function loadAttendances() {
  loading.value = true
  error.value = ''
  try {
    const response = await studentApi.attendances({ month: selectedMonth.value })
    attendances.value = response.data.attendances || []
  } catch (e: any) {
    error.value = e.response?.data?.message || '출석 정보를 불러오는데 실패했습니다.'
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr: string): string {
  const date = new Date(dateStr)
  const days = ['일', '월', '화', '수', '목', '금', '토']
  return `${date.getMonth() + 1}/${date.getDate()}(${days[date.getDay()]})`
}

function formatTime(timeStr: string | null): string {
  if (!timeStr) return '-'
  return timeStr.slice(0, 5)
}

onMounted(() => {
  loadAttendances()
})
</script>

<template>
  <div class="p-4">
    <h2 class="text-xl font-bold text-gray-900 mb-4">출석 현황</h2>

    <!-- Month Selector -->
    <div class="mb-4">
      <input
        type="month"
        v-model="selectedMonth"
        @change="loadAttendances"
        class="input"
      />
    </div>

    <!-- Error -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
      {{ error }}
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 gap-3 mb-4">
      <div class="card">
        <div class="card-body text-center py-3">
          <p class="text-2xl font-bold text-primary-600">{{ attendanceRate }}%</p>
          <p class="text-xs text-gray-500">출석률</p>
        </div>
      </div>
      <div class="card">
        <div class="card-body text-center py-3">
          <p class="text-2xl font-bold text-green-600">{{ monthlyStats.present }}</p>
          <p class="text-xs text-gray-500">출석</p>
        </div>
      </div>
      <div class="card">
        <div class="card-body text-center py-3">
          <p class="text-2xl font-bold text-yellow-600">{{ monthlyStats.late }}</p>
          <p class="text-xs text-gray-500">지각</p>
        </div>
      </div>
      <div class="card">
        <div class="card-body text-center py-3">
          <p class="text-2xl font-bold text-red-600">{{ monthlyStats.absent }}</p>
          <p class="text-xs text-gray-500">결석</p>
        </div>
      </div>
    </div>

    <!-- Attendance List -->
    <div class="card">
      <div class="card-header">
        <h3 class="text-sm font-semibold text-gray-900">상세 기록</h3>
      </div>
      <div class="divide-y divide-gray-100">
        <div v-if="loading" class="p-4 text-center text-gray-500">
          불러오는 중...
        </div>
        <div v-else-if="attendances.length === 0" class="p-4 text-center text-gray-500">
          이번 달 출석 기록이 없습니다.
        </div>
        <div v-else v-for="att in attendances" :key="att.id" class="p-3 flex justify-between items-center">
          <div>
            <p class="text-sm font-medium text-gray-900">{{ formatDate(att.date) }}</p>
            <p class="text-xs text-gray-500">{{ att.lesson_name }}</p>
          </div>
          <div class="text-right">
            <span :class="['badge', statusColors[att.status]]">
              {{ statusLabels[att.status] || att.status }}
            </span>
            <p v-if="att.check_in_time" class="text-xs text-gray-400 mt-1">
              {{ formatTime(att.check_in_time) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Note -->
    <p class="text-xs text-gray-400 mt-4 text-center">
      출석 기록에 오류가 있으면 관리자에게 문의해주세요.
    </p>
  </div>
</template>
