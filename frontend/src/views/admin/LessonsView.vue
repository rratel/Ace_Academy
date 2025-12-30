<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

interface Enrollment {
  id: number
  student_id: number
  student_name: string
  status: string
  remaining_sessions: number
  expires_at: string
}

interface Student {
  id: number
  name: string
  phone: string
  status: string
}

interface Lesson {
  id: number
  name: string
  description: string | null
  days: string[]
  start_time: string
  end_time: string
  price: number
  max_students: number
  current_students: number
  is_active: boolean
}

const lessons = ref<Lesson[]>([])
const students = ref<Student[]>([])
const loading = ref(true)
const error = ref('')
const showModal = ref(false)
const showDetailModal = ref(false)
const editingLesson = ref<Lesson | null>(null)
const selectedLesson = ref<Lesson | null>(null)
const lessonEnrollments = ref<Enrollment[]>([])
const enrollmentsLoading = ref(false)
const studentSearch = ref('')
const filteredStudents = ref<Student[]>([])
const showStudentDropdown = ref(false)

const dayLabels: Record<string, string> = {
  monday: '월',
  tuesday: '화',
  wednesday: '수',
  thursday: '목',
  friday: '금',
  saturday: '토',
  sunday: '일'
}

const form = ref({
  name: '',
  description: '',
  days: [] as string[],
  start_time: '',
  end_time: '',
  price: 0,
  max_students: 10,
  branch_id: authStore.user?.branch_id || 0
})

const enrollFormData = ref({
  student_id: 0,
  student_name: '',
  remaining_sessions: 12,
  expires_at: '',
})

const formError = ref('')
const formLoading = ref(false)

// 만료일 기본값 (1개월 후)
const defaultExpiresAt = computed(() => {
  const date = new Date()
  date.setMonth(date.getMonth() + 1)
  return date.toISOString().split('T')[0]
})

async function loadLessons() {
  loading.value = true
  error.value = ''
  try {
    const response = await adminApi.lessons()
    lessons.value = response.data.lessons
  } catch (e: any) {
    error.value = e.response?.data?.message || '수업 목록을 불러오는데 실패했습니다.'
  } finally {
    loading.value = false
  }
}

async function loadStudents() {
  try {
    const response = await adminApi.students()
    students.value = response.data.students || []
  } catch (e) {
    console.error(e)
  }
}

async function loadLessonEnrollments(lessonId: number) {
  enrollmentsLoading.value = true
  try {
    const response = await adminApi.enrollments({ lesson_id: lessonId })
    lessonEnrollments.value = response.data.enrollments || []
  } catch (e) {
    console.error(e)
    lessonEnrollments.value = []
  } finally {
    enrollmentsLoading.value = false
  }
}

function openCreateModal() {
  editingLesson.value = null
  form.value = {
    name: '',
    description: '',
    days: [],
    start_time: '',
    end_time: '',
    price: 0,
    max_students: 10,
    branch_id: authStore.user?.branch_id || 0
  }
  showModal.value = true
}

function openEditModal(lesson: Lesson) {
  editingLesson.value = lesson
  form.value = {
    name: lesson.name,
    description: lesson.description || '',
    days: [...lesson.days],
    start_time: formatTimeForInput(lesson.start_time),
    end_time: formatTimeForInput(lesson.end_time),
    price: lesson.price,
    max_students: lesson.max_students,
    branch_id: authStore.user?.branch_id || 0
  }
  showModal.value = true
}

function openDetailModal(lesson: Lesson) {
  selectedLesson.value = lesson
  enrollFormData.value = {
    student_id: 0,
    student_name: '',
    remaining_sessions: 12,
    expires_at: defaultExpiresAt.value || '',
  }
  studentSearch.value = ''
  formError.value = ''
  loadLessonEnrollments(lesson.id)
  showDetailModal.value = true
}

async function saveLesson() {
  formLoading.value = true
  formError.value = ''
  try {
    if (editingLesson.value) {
      await adminApi.updateLesson(editingLesson.value.id, form.value)
    } else {
      await adminApi.createLesson(form.value as any)
    }
    showModal.value = false
    await loadLessons()
  } catch (e: any) {
    formError.value = e.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    formLoading.value = false
  }
}

async function toggleActive(lesson: Lesson) {
  const action = lesson.is_active ? '비활성화' : '활성화'
  if (!confirm(`이 수업을 ${action}하시겠습니까?`)) return

  try {
    await adminApi.updateLesson(lesson.id, { is_active: !lesson.is_active })
    await loadLessons()
  } catch (e: any) {
    alert(e.response?.data?.message || '상태 변경에 실패했습니다.')
  }
}

async function deleteLesson(lesson: Lesson) {
  if (!confirm('이 수업을 삭제하시겠습니까? 수강 중인 학생이 있으면 삭제할 수 없습니다.')) return

  try {
    await adminApi.deleteLesson(lesson.id)
    await loadLessons()
  } catch (e: any) {
    alert(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function searchStudents() {
  if (studentSearch.value.length < 1) {
    filteredStudents.value = []
    showStudentDropdown.value = false
    return
  }

  const search = studentSearch.value.toLowerCase()
  filteredStudents.value = students.value.filter(s =>
    s.name.toLowerCase().includes(search) || s.phone.includes(search)
  ).slice(0, 10)
  showStudentDropdown.value = filteredStudents.value.length > 0
}

function selectStudent(student: Student) {
  enrollFormData.value.student_id = student.id
  enrollFormData.value.student_name = student.name
  studentSearch.value = student.name
  showStudentDropdown.value = false
}

async function handleEnrollStudent() {
  if (!selectedLesson.value || !enrollFormData.value.student_id) {
    formError.value = '학생을 선택해주세요.'
    return
  }

  formLoading.value = true
  formError.value = ''

  try {
    await adminApi.createEnrollment({
      student_id: enrollFormData.value.student_id,
      lesson_id: selectedLesson.value.id,
      remaining_sessions: enrollFormData.value.remaining_sessions,
      expires_at: enrollFormData.value.expires_at,
    })
    loadLessonEnrollments(selectedLesson.value.id)
    loadLessons() // 수강생 수 갱신
    enrollFormData.value = {
      student_id: 0,
      student_name: '',
      remaining_sessions: 12,
      expires_at: defaultExpiresAt.value || '',
    }
    studentSearch.value = ''
    alert('수강 등록이 완료되었습니다.')
  } catch (e: any) {
    formError.value = e.response?.data?.message || '수강 등록에 실패했습니다.'
  } finally {
    formLoading.value = false
  }
}

function formatDays(days: string[] | undefined): string {
  if (!days || days.length === 0) return '-'
  return days.map(d => dayLabels[d] || d).join(', ')
}

function formatPrice(price: number): string {
  return new Intl.NumberFormat('ko-KR').format(price) + '원'
}

function formatTime(time: string | undefined): string {
  if (!time) return '-'
  // Handle ISO datetime format (2025-12-30T17:02:00.000000Z)
  // Extract time part directly without timezone conversion
  if (time.includes('T')) {
    const timePart = time.split('T')[1] ?? ''
    return timePart.substring(0, 5)
  }
  // Handle HH:mm:ss or HH:mm format
  return time.substring(0, 5)
}

function formatTimeForInput(time: string | undefined): string {
  if (!time) return ''
  // Extract time part directly without timezone conversion
  if (time.includes('T')) {
    const timePart = time.split('T')[1] ?? ''
    return timePart.substring(0, 5)
  }
  return time.substring(0, 5)
}

function getStatusBadge(status: string) {
  const badges: Record<string, string> = {
    approved: 'bg-green-100 text-green-800',
    pending: 'bg-yellow-100 text-yellow-800',
    rejected: 'bg-red-100 text-red-800',
    expired: 'bg-gray-100 text-gray-800',
  }
  return badges[status] || 'bg-gray-100 text-gray-800'
}

function getStatusText(status: string) {
  const texts: Record<string, string> = {
    approved: '수강중',
    pending: '대기',
    rejected: '거절',
    expired: '만료',
  }
  return texts[status] || status
}

onMounted(() => {
  loadLessons()
  loadStudents()
})
</script>

<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900">수업 관리</h1>
      <button @click="openCreateModal" class="btn-primary">
        + 새 수업 추가
      </button>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
      {{ error }}
    </div>

    <!-- Lessons Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-if="loading" class="col-span-full text-center py-8 text-gray-500">
        불러오는 중...
      </div>
      <div v-else-if="lessons.length === 0" class="col-span-full text-center py-8 text-gray-500">
        등록된 수업이 없습니다.
      </div>
      <div
        v-else
        v-for="lesson in lessons"
        :key="lesson.id"
        class="card cursor-pointer hover:shadow-lg transition-shadow"
        @click="openDetailModal(lesson)"
      >
        <div class="card-body">
          <div class="flex justify-between items-start mb-3">
            <h3 class="font-semibold text-lg">{{ lesson.name }}</h3>
            <span :class="['badge', lesson.is_active ? 'badge-success' : 'badge-info']">
              {{ lesson.is_active ? '활성' : '비활성' }}
            </span>
          </div>

          <p v-if="lesson.description" class="text-sm text-gray-500 mb-3">
            {{ lesson.description }}
          </p>

          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-500">요일</span>
              <span class="font-medium">{{ formatDays(lesson.days) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">시간</span>
              <span class="font-medium">{{ formatTime(lesson.start_time) }} - {{ formatTime(lesson.end_time) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">수강료</span>
              <span class="font-medium text-primary-600">{{ formatPrice(lesson.price) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">정원</span>
              <span class="font-medium">{{ lesson.current_students }} / {{ lesson.max_students }}명</span>
            </div>
          </div>

          <div class="flex gap-2 mt-4 pt-4 border-t border-gray-100" @click.stop>
            <button @click="openEditModal(lesson)" class="flex-1 btn-secondary text-sm py-1.5">
              수정
            </button>
            <button @click="toggleActive(lesson)"
                    :class="['flex-1 text-sm py-1.5', lesson.is_active ? 'btn-secondary' : 'btn-primary']">
              {{ lesson.is_active ? '비활성화' : '활성화' }}
            </button>
            <button @click="deleteLesson(lesson)" class="btn-danger text-sm py-1.5 px-3">
              삭제
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-xl font-bold">{{ editingLesson ? '수업 수정' : '새 수업 추가' }}</h2>
        </div>

        <form @submit.prevent="saveLesson" class="p-6 space-y-4">
          <div>
            <label class="label">수업명</label>
            <input v-model="form.name" type="text" class="input" required />
          </div>

          <div>
            <label class="label">설명</label>
            <textarea v-model="form.description" class="input" rows="2"></textarea>
          </div>

          <div>
            <label class="label">수업 요일</label>
            <div class="flex flex-wrap gap-2">
              <label v-for="(label, key) in dayLabels" :key="key" class="flex items-center gap-1">
                <input type="checkbox" :value="key" v-model="form.days" class="rounded" />
                <span class="text-sm">{{ label }}</span>
              </label>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">시작 시간</label>
              <input v-model="form.start_time" type="time" class="input" required />
            </div>
            <div>
              <label class="label">종료 시간</label>
              <input v-model="form.end_time" type="time" class="input" required />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">수강료 (원)</label>
              <input v-model.number="form.price" type="number" class="input" min="0" required />
            </div>
            <div>
              <label class="label">정원</label>
              <input v-model.number="form.max_students" type="number" class="input" min="1" required />
            </div>
          </div>

          <div v-if="formError" class="text-sm text-red-600">{{ formError }}</div>

          <div class="flex gap-3 pt-4">
            <button type="button" @click="showModal = false" class="flex-1 btn-secondary">
              취소
            </button>
            <button type="submit" :disabled="formLoading" class="flex-1 btn-primary">
              {{ formLoading ? '저장 중...' : (editingLesson ? '수정' : '추가') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Lesson Detail Modal -->
    <div v-if="showDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-6 border-b border-gray-200">
          <div class="flex justify-between items-start">
            <div>
              <h2 class="text-xl font-bold">{{ selectedLesson?.name }}</h2>
              <p class="text-sm text-gray-500 mt-1">
                {{ formatDays(selectedLesson?.days) }} |
                {{ formatTime(selectedLesson?.start_time) }} - {{ formatTime(selectedLesson?.end_time) }}
              </p>
            </div>
            <span :class="['badge', selectedLesson?.is_active ? 'badge-success' : 'badge-info']">
              {{ selectedLesson?.is_active ? '활성' : '비활성' }}
            </span>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
          <!-- Lesson Info -->
          <div class="grid grid-cols-3 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
            <div class="text-center">
              <div class="text-2xl font-bold text-primary-600">{{ selectedLesson?.current_students }}</div>
              <div class="text-sm text-gray-500">현재 수강생</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold">{{ selectedLesson?.max_students }}</div>
              <div class="text-sm text-gray-500">정원</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-green-600">{{ formatPrice(selectedLesson?.price || 0) }}</div>
              <div class="text-sm text-gray-500">수강료</div>
            </div>
          </div>

          <!-- Enrolled Students -->
          <div class="mb-6">
            <h3 class="text-lg font-semibold mb-3">수강생 목록</h3>
            <div v-if="enrollmentsLoading" class="text-center text-gray-500 py-4">
              로딩 중...
            </div>
            <div v-else-if="lessonEnrollments.length === 0" class="text-center text-gray-500 py-4 bg-gray-50 rounded-lg">
              등록된 수강생이 없습니다.
            </div>
            <div v-else class="space-y-2 max-h-48 overflow-y-auto">
              <div
                v-for="enrollment in lessonEnrollments"
                :key="enrollment.id"
                class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
              >
                <div>
                  <span class="font-medium">{{ enrollment.student_name }}</span>
                  <div class="text-sm text-gray-500">
                    남은 횟수: {{ enrollment.remaining_sessions }}회 |
                    만료일: {{ enrollment.expires_at || '-' }}
                  </div>
                </div>
                <span :class="['px-2 py-1 text-xs rounded-full', getStatusBadge(enrollment.status)]">
                  {{ getStatusText(enrollment.status) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Add Student -->
          <div class="border-t border-gray-200 pt-6">
            <h3 class="text-lg font-semibold mb-3">학생 수강 등록</h3>
            <div class="space-y-4">
              <div class="relative">
                <label class="block text-sm font-medium text-gray-700 mb-1">학생 검색 *</label>
                <input
                  v-model="studentSearch"
                  @input="searchStudents"
                  @focus="searchStudents"
                  type="text"
                  placeholder="이름 또는 전화번호로 검색"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                />
                <!-- Dropdown -->
                <div
                  v-if="showStudentDropdown"
                  class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-y-auto"
                >
                  <button
                    v-for="student in filteredStudents"
                    :key="student.id"
                    type="button"
                    @click="selectStudent(student)"
                    class="w-full px-4 py-2 text-left hover:bg-gray-100 flex justify-between items-center"
                  >
                    <span>{{ student.name }}</span>
                    <span class="text-sm text-gray-500">{{ student.phone }}</span>
                  </button>
                </div>
              </div>

              <div v-if="enrollFormData.student_id" class="p-3 bg-blue-50 rounded-lg text-sm">
                선택된 학생: <strong>{{ enrollFormData.student_name }}</strong>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">수강 횟수</label>
                  <input
                    v-model.number="enrollFormData.remaining_sessions"
                    type="number"
                    min="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">만료일</label>
                  <input
                    v-model="enrollFormData.expires_at"
                    type="date"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                </div>
              </div>

              <div v-if="formError" class="text-sm text-red-600">{{ formError }}</div>

              <button
                @click="handleEnrollStudent"
                :disabled="formLoading || !enrollFormData.student_id"
                class="w-full btn-primary"
              >
                {{ formLoading ? '등록 중...' : '수강 등록' }}
              </button>
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-gray-200">
          <button
            @click="showDetailModal = false; selectedLesson = null"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
          >
            닫기
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.btn-primary {
  @apply px-4 py-2 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 disabled:opacity-50;
}
.btn-secondary {
  @apply px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50;
}
.btn-danger {
  @apply px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700;
}
.label {
  @apply block text-sm font-medium text-gray-700 mb-1;
}
.input {
  @apply w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500;
}
.card {
  @apply bg-white rounded-xl shadow-sm border border-gray-100;
}
.card-body {
  @apply p-5;
}
.badge {
  @apply px-2 py-1 text-xs font-medium rounded-full;
}
.badge-success {
  @apply bg-green-100 text-green-800;
}
.badge-info {
  @apply bg-gray-100 text-gray-800;
}
</style>
