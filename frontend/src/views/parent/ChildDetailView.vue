<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { parentApi } from '@/services/api'

const route = useRoute()
const router = useRouter()
const childId = Number(route.params.childId)

interface Enrollment {
  id: number
  lesson_name: string
  lesson_description: string | null
  days: string[]
  time: string
  price: number
  status: string
}

interface Attendance {
  id: number
  lesson_name: string
  date: string
  status: 'present' | 'late' | 'absent' | 'excused'
  check_in_time: string | null
}

interface ChildInfo {
  id: number
  name: string
  email: string
  phone: string
}

const child = ref<ChildInfo | null>(null)
const enrollments = ref<Enrollment[]>([])
const attendances = ref<Attendance[]>([])
const loading = ref(true)
const error = ref('')
const activeTab = ref<'enrollments' | 'attendances'>('enrollments')
const selectedMonth = ref(new Date().toISOString().slice(0, 7))

const dayLabels: Record<string, string> = {
  monday: '월', tuesday: '화', wednesday: '수',
  thursday: '목', friday: '금', saturday: '토', sunday: '일'
}

const statusLabels: Record<string, string> = {
  present: '출석',
  late: '지각',
  absent: '결석',
  excused: '인정결석',
  pending: '승인대기',
  approved: '수강중',
  rejected: '거절됨',
  cancelled: '취소됨'
}

const statusColors: Record<string, string> = {
  present: 'badge-success',
  late: 'badge-warning',
  absent: 'badge-danger',
  excused: 'badge-info',
  pending: 'badge-warning',
  approved: 'badge-success',
  rejected: 'badge-danger',
  cancelled: 'badge-info'
}

const attendanceStats = computed(() => {
  const stats = { present: 0, late: 0, absent: 0, excused: 0, total: 0 }
  attendances.value.forEach(att => {
    if (stats[att.status] !== undefined) {
      stats[att.status]++
    }
    stats.total++
  })
  return stats
})

const attendanceRate = computed(() => {
  if (attendanceStats.value.total === 0) return 0
  return Math.round((attendanceStats.value.present / attendanceStats.value.total) * 100)
})

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [enrollmentsRes, attendancesRes] = await Promise.all([
      parentApi.childEnrollments(childId),
      parentApi.childAttendances(childId, { month: selectedMonth.value })
    ])

    child.value = enrollmentsRes.data.child || null
    enrollments.value = enrollmentsRes.data.enrollments || []
    attendances.value = attendancesRes.data.attendances || []
  } catch (e: any) {
    error.value = e.response?.data?.message || '정보를 불러오는데 실패했습니다.'
  } finally {
    loading.value = false
  }
}

async function loadAttendances() {
  try {
    const response = await parentApi.childAttendances(childId, { month: selectedMonth.value })
    attendances.value = response.data.attendances || []
  } catch (e: any) {
    console.error('Failed to load attendances:', e)
  }
}

function formatDays(days: string[]): string {
  if (!days) return '-'
  return days.map(d => dayLabels[d] || d).join(', ')
}

function formatDate(dateStr: string): string {
  const date = new Date(dateStr)
  const days = ['일', '월', '화', '수', '목', '금', '토']
  return `${date.getMonth() + 1}/${date.getDate()}(${days[date.getDay()]})`
}

function formatPrice(price: number): string {
  return new Intl.NumberFormat('ko-KR').format(price) + '원'
}

function formatTime(timeStr: string | null): string {
  if (!timeStr) return '-'
  return timeStr.slice(0, 5)
}

function goBack() {
  router.push('/parent')
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="p-4">
    <!-- Header -->
    <div class="flex items-center mb-4">
      <button @click="goBack" class="mr-3 p-1.5 rounded-lg hover:bg-gray-100">
        <svg class="h-5 w-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h2 class="text-xl font-bold text-gray-900">{{ child?.name || '자녀' }} 상세 정보</h2>
    </div>

    <!-- Error -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
      {{ error }}
    </div>

    <!-- Loading -->
    <div v-if="loading" class="card">
      <div class="card-body text-center py-8">
        <p class="text-gray-500">불러오는 중...</p>
      </div>
    </div>

    <template v-else>
      <!-- Child Info Card -->
      <div class="card mb-4">
        <div class="card-body">
          <div class="flex items-center space-x-3">
            <div class="h-12 w-12 bg-primary-100 rounded-full flex items-center justify-center">
              <span class="text-primary-600 font-bold text-lg">{{ child?.name?.charAt(0) || '?' }}</span>
            </div>
            <div>
              <p class="font-semibold text-gray-900">{{ child?.name }}</p>
              <p class="text-sm text-gray-500">{{ child?.email }}</p>
              <p v-if="child?.phone" class="text-sm text-gray-500">{{ child?.phone }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-4 gap-2 mb-4">
        <div class="card">
          <div class="card-body text-center py-2">
            <p class="text-lg font-bold text-primary-600">{{ attendanceRate }}%</p>
            <p class="text-xs text-gray-500">출석률</p>
          </div>
        </div>
        <div class="card">
          <div class="card-body text-center py-2">
            <p class="text-lg font-bold text-green-600">{{ attendanceStats.present }}</p>
            <p class="text-xs text-gray-500">출석</p>
          </div>
        </div>
        <div class="card">
          <div class="card-body text-center py-2">
            <p class="text-lg font-bold text-yellow-600">{{ attendanceStats.late }}</p>
            <p class="text-xs text-gray-500">지각</p>
          </div>
        </div>
        <div class="card">
          <div class="card-body text-center py-2">
            <p class="text-lg font-bold text-red-600">{{ attendanceStats.absent }}</p>
            <p class="text-xs text-gray-500">결석</p>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex border-b border-gray-200 mb-4">
        <button
          @click="activeTab = 'enrollments'"
          :class="[
            'flex-1 py-3 text-sm font-medium text-center border-b-2 transition-colors',
            activeTab === 'enrollments'
              ? 'border-primary-600 text-primary-600'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          ]"
        >
          수강 목록 ({{ enrollments.length }})
        </button>
        <button
          @click="activeTab = 'attendances'"
          :class="[
            'flex-1 py-3 text-sm font-medium text-center border-b-2 transition-colors',
            activeTab === 'attendances'
              ? 'border-primary-600 text-primary-600'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          ]"
        >
          출석 기록 ({{ attendances.length }})
        </button>
      </div>

      <!-- Enrollments Tab -->
      <div v-if="activeTab === 'enrollments'" class="space-y-3">
        <div v-if="enrollments.length === 0" class="card">
          <div class="card-body text-center py-8">
            <p class="text-gray-500">수강 중인 수업이 없습니다.</p>
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
              <div class="col-span-2">
                <span class="text-gray-500">수강료:</span>
                <span class="ml-1 text-gray-900">{{ formatPrice(enroll.price) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Attendances Tab -->
      <div v-if="activeTab === 'attendances'">
        <!-- Month Selector -->
        <div class="mb-4">
          <input
            type="month"
            v-model="selectedMonth"
            @change="loadAttendances"
            class="input"
          />
        </div>

        <div class="card">
          <div class="divide-y divide-gray-100">
            <div v-if="attendances.length === 0" class="p-4 text-center text-gray-500">
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
      </div>
    </template>
  </div>
</template>
