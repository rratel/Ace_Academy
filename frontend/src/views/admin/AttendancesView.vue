<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'

interface Attendance {
  id: number
  student_name: string
  lesson: string
  status: string
  attended_at: string
  check_in_method: string
  notes: string | null
}

const attendances = ref<Attendance[]>([])
const loading = ref(true)
const error = ref('')
const filter = ref({
  date: new Date().toISOString().split('T')[0],
  status: ''
})

const statusLabels: Record<string, string> = {
  present: '출석',
  late: '지각',
  absent: '결석',
  excused: '인정결석',
  early_leave: '조퇴',
  makeup: '보강'
}

const statusColors: Record<string, string> = {
  present: 'badge-success',
  late: 'badge-warning',
  absent: 'badge-danger',
  excused: 'badge-info',
  early_leave: 'badge-warning',
  makeup: 'badge-info'
}

const methodLabels: Record<string, string> = {
  qr: 'QR 스캔',
  manual: '수동 입력',
  auto: '자동 체크'
}

async function loadAttendances() {
  loading.value = true
  error.value = ''
  try {
    const params: Record<string, string> = {}
    if (filter.value.date) params.date = filter.value.date
    if (filter.value.status) params.status = filter.value.status

    const response = await adminApi.attendances(params)
    attendances.value = response.data.attendances
  } catch (e: any) {
    error.value = e.response?.data?.message || '출결 정보를 불러오는데 실패했습니다.'
  } finally {
    loading.value = false
  }
}

async function updateStatus(attendance: Attendance, newStatus: string) {
  const statusText = statusLabels[newStatus] || newStatus
  if (!confirm(`이 출결을 '${statusText}'로 변경하시겠습니까?`)) return

  try {
    await adminApi.updateAttendance(attendance.id, { status: newStatus })
    await loadAttendances()
  } catch (e: any) {
    alert(e.response?.data?.message || '상태 변경에 실패했습니다.')
  }
}

function formatDate(dateStr: string): string {
  const date = new Date(dateStr)
  return date.toLocaleDateString('ko-KR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(() => {
  loadAttendances()
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">출결 관리</h1>

    <!-- Filters -->
    <div class="card mb-6">
      <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">날짜</label>
            <input
              v-model="filter.date"
              @change="loadAttendances"
              type="date"
              class="input"
            />
          </div>
          <div>
            <label class="label">상태</label>
            <select v-model="filter.status" @change="loadAttendances" class="input">
              <option value="">전체</option>
              <option value="present">출석</option>
              <option value="late">지각</option>
              <option value="absent">결석</option>
              <option value="excused">인정결석</option>
            </select>
          </div>
          <div class="flex items-end">
            <button @click="loadAttendances" class="btn-primary w-full">
              검색
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
      {{ error }}
    </div>

    <!-- Attendances Table -->
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">학생</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">수업</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">상태</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">체크인 방식</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">시간</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">비고</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">작업</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="loading">
              <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                불러오는 중...
              </td>
            </tr>
            <tr v-else-if="attendances.length === 0">
              <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                출결 기록이 없습니다.
              </td>
            </tr>
            <tr v-else v-for="att in attendances" :key="att.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="font-medium text-gray-900">{{ att.student_name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ att.lesson }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="['badge', statusColors[att.status]]">
                  {{ statusLabels[att.status] || att.status }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                {{ methodLabels[att.check_in_method] || att.check_in_method }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ formatDate(att.attended_at) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ att.notes || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <div class="flex justify-end gap-2">
                  <button
                    v-if="att.status !== 'present'"
                    @click="updateStatus(att, 'present')"
                    class="text-green-600 hover:text-green-900 text-sm font-medium"
                  >
                    출석
                  </button>
                  <button
                    v-if="att.status !== 'absent'"
                    @click="updateStatus(att, 'absent')"
                    class="text-red-600 hover:text-red-900 text-sm font-medium"
                  >
                    결석
                  </button>
                  <button
                    v-if="att.status !== 'excused'"
                    @click="updateStatus(att, 'excused')"
                    class="text-blue-600 hover:text-blue-900 text-sm font-medium"
                  >
                    인정
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Summary -->
    <div v-if="!loading && attendances.length > 0" class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="card">
        <div class="card-body text-center">
          <div class="text-2xl font-bold text-green-600">
            {{ attendances.filter(a => a.status === 'present').length }}
          </div>
          <div class="text-sm text-gray-500">출석</div>
        </div>
      </div>
      <div class="card">
        <div class="card-body text-center">
          <div class="text-2xl font-bold text-yellow-600">
            {{ attendances.filter(a => a.status === 'late').length }}
          </div>
          <div class="text-sm text-gray-500">지각</div>
        </div>
      </div>
      <div class="card">
        <div class="card-body text-center">
          <div class="text-2xl font-bold text-red-600">
            {{ attendances.filter(a => a.status === 'absent').length }}
          </div>
          <div class="text-sm text-gray-500">결석</div>
        </div>
      </div>
      <div class="card">
        <div class="card-body text-center">
          <div class="text-2xl font-bold text-blue-600">
            {{ attendances.filter(a => a.status === 'excused').length }}
          </div>
          <div class="text-sm text-gray-500">인정결석</div>
        </div>
      </div>
    </div>
  </div>
</template>
