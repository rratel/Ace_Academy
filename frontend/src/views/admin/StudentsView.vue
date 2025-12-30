<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '@/services/api'

interface Lesson {
  id: number
  name: string
  days: string[]
  start_time: string
  end_time: string
  price: number
  max_students: number
  current_students: number
}

interface Enrollment {
  id: number
  lesson_id: number
  lesson_name: string
  status: string
  remaining_sessions: number
  expires_at: string
}

interface Student {
  id: number
  name: string
  phone: string
  branch: string
  status: string
  created_at: string
  enrollments?: Enrollment[]
}

const students = ref<Student[]>([])
const lessons = ref<Lesson[]>([])
const loading = ref(false)
const showModal = ref(false)
const showEditModal = ref(false)
const showEnrollModal = ref(false)
const editingStudent = ref<Student | null>(null)
const selectedStudent = ref<Student | null>(null)
const studentEnrollments = ref<Enrollment[]>([])
const enrollmentsLoading = ref(false)

const formData = ref({
  name: '',
  phone: '',
  birth_date: '',
  gender: '',
  school: '',
  grade: '',
})

const editFormData = ref({
  name: '',
  phone: '',
  birth_date: '',
  gender: '',
  school: '',
  grade: '',
  status: 'active',
})

const enrollFormData = ref({
  lesson_id: 0,
  remaining_sessions: 12,
  expires_at: '',
})

const formError = ref('')
const formLoading = ref(false)

const dayLabels: Record<string, string> = {
  monday: '월',
  tuesday: '화',
  wednesday: '수',
  thursday: '목',
  friday: '금',
  saturday: '토',
  sunday: '일'
}

// 만료일 기본값 (1개월 후)
const defaultExpiresAt = computed(() => {
  const date = new Date()
  date.setMonth(date.getMonth() + 1)
  return date.toISOString().split('T')[0]
})

async function fetchStudents() {
  loading.value = true
  try {
    const response = await adminApi.students()
    students.value = response.data.students || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function fetchLessons() {
  try {
    const response = await adminApi.lessons()
    lessons.value = response.data.lessons || []
  } catch (e) {
    console.error(e)
  }
}

async function fetchStudentEnrollments(studentId: number) {
  enrollmentsLoading.value = true
  try {
    const response = await adminApi.enrollments({ student_id: studentId })
    studentEnrollments.value = response.data.enrollments || []
  } catch (e) {
    console.error(e)
    studentEnrollments.value = []
  } finally {
    enrollmentsLoading.value = false
  }
}

async function handleSubmit() {
  formLoading.value = true
  formError.value = ''

  try {
    await adminApi.createStudent(formData.value as any)
    showModal.value = false
    formData.value = { name: '', phone: '', birth_date: '', gender: '', school: '', grade: '' }
    fetchStudents()
  } catch (e: any) {
    formError.value = e.response?.data?.message || '학생 등록에 실패했습니다.'
  } finally {
    formLoading.value = false
  }
}

function openEditModal(student: Student) {
  editingStudent.value = student
  editFormData.value = {
    name: student.name,
    phone: student.phone.replace(/-/g, ''),
    birth_date: '',
    gender: '',
    school: '',
    grade: '',
    status: student.status,
  }
  formError.value = ''
  showEditModal.value = true
}

async function handleUpdate() {
  if (!editingStudent.value) return

  formLoading.value = true
  formError.value = ''

  try {
    await adminApi.updateStudent(editingStudent.value.id, editFormData.value as any)
    showEditModal.value = false
    editingStudent.value = null
    fetchStudents()
  } catch (e: any) {
    formError.value = e.response?.data?.message || '학생 정보 수정에 실패했습니다.'
  } finally {
    formLoading.value = false
  }
}

async function handleDelete(student: Student) {
  if (!confirm(`${student.name} 학생을 삭제하시겠습니까?`)) return

  try {
    await adminApi.deleteStudent(student.id)
    fetchStudents()
  } catch (e: any) {
    alert(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function openEnrollModal(student: Student) {
  selectedStudent.value = student
  enrollFormData.value = {
    lesson_id: 0,
    remaining_sessions: 12,
    expires_at: defaultExpiresAt.value || '',
  }
  formError.value = ''
  fetchStudentEnrollments(student.id)
  showEnrollModal.value = true
}

async function handleEnroll() {
  if (!selectedStudent.value || !enrollFormData.value.lesson_id) {
    formError.value = '수업을 선택해주세요.'
    return
  }

  formLoading.value = true
  formError.value = ''

  try {
    await adminApi.createEnrollment({
      student_id: selectedStudent.value.id,
      lesson_id: enrollFormData.value.lesson_id,
      remaining_sessions: enrollFormData.value.remaining_sessions,
      expires_at: enrollFormData.value.expires_at,
    })
    fetchStudentEnrollments(selectedStudent.value.id)
    enrollFormData.value.lesson_id = 0
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

function getStatusBadge(status: string) {
  const badges: Record<string, string> = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-gray-100 text-gray-800',
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    expired: 'bg-gray-100 text-gray-800',
  }
  return badges[status] || 'bg-gray-100 text-gray-800'
}

function getStatusText(status: string) {
  const texts: Record<string, string> = {
    active: '활성',
    inactive: '비활성',
    pending: '대기',
    approved: '수강중',
    rejected: '거절',
    expired: '만료',
  }
  return texts[status] || status
}

onMounted(() => {
  fetchStudents()
  fetchLessons()
})
</script>

<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900">학생 관리</h1>
      <button
        @click="showModal = true"
        class="btn-primary"
      >
        + 학생 등록
      </button>
    </div>

    <!-- Students Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-500">
        로딩 중...
      </div>

      <table v-else-if="students.length" class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">이름</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">핸드폰</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">상태</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">등록일</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">관리</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="student in students" :key="student.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
              {{ student.name }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              {{ student.phone }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="['px-2 py-1 text-xs rounded-full', getStatusBadge(student.status)]">
                {{ getStatusText(student.status) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              {{ student.created_at }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
              <button
                @click="openEnrollModal(student)"
                class="text-blue-600 hover:text-blue-900 font-medium"
              >
                수강관리
              </button>
              <button
                @click="openEditModal(student)"
                class="text-primary-600 hover:text-primary-900"
              >
                수정
              </button>
              <button
                @click="handleDelete(student)"
                class="text-red-600 hover:text-red-900"
              >
                삭제
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else class="p-8 text-center text-gray-500">
        등록된 학생이 없습니다.
      </div>
    </div>

    <!-- Add Student Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <h2 class="text-xl font-bold mb-4">학생 등록</h2>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">이름 *</label>
            <input
              v-model="formData.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">핸드폰 번호 *</label>
            <input
              v-model="formData.phone"
              type="tel"
              required
              placeholder="010-1234-5678"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">생년월일</label>
            <input
              v-model="formData.birth_date"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">성별</label>
              <select
                v-model="formData.gender"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
              >
                <option value="">선택</option>
                <option value="male">남</option>
                <option value="female">여</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">학년</label>
              <input
                v-model="formData.grade"
                type="number"
                min="1"
                max="12"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">학교</label>
            <input
              v-model="formData.school"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div v-if="formError" class="text-sm text-red-600">{{ formError }}</div>

          <div class="flex justify-end gap-3 pt-4">
            <button
              type="button"
              @click="showModal = false"
              class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
            >
              취소
            </button>
            <button
              type="submit"
              :disabled="formLoading"
              class="btn-primary"
            >
              {{ formLoading ? '등록 중...' : '등록' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Student Modal -->
    <div v-if="showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <h2 class="text-xl font-bold mb-4">학생 정보 수정</h2>

        <form @submit.prevent="handleUpdate" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">이름 *</label>
            <input
              v-model="editFormData.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">핸드폰 번호 *</label>
            <input
              v-model="editFormData.phone"
              type="tel"
              required
              placeholder="01012345678"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">상태</label>
            <select
              v-model="editFormData.status"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            >
              <option value="active">활성</option>
              <option value="inactive">비활성</option>
              <option value="pending">대기</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">생년월일</label>
            <input
              v-model="editFormData.birth_date"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">성별</label>
              <select
                v-model="editFormData.gender"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
              >
                <option value="">선택</option>
                <option value="male">남</option>
                <option value="female">여</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">학년</label>
              <input
                v-model="editFormData.grade"
                type="number"
                min="1"
                max="12"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">학교</label>
            <input
              v-model="editFormData.school"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div v-if="formError" class="text-sm text-red-600">{{ formError }}</div>

          <div class="flex justify-end gap-3 pt-4">
            <button
              type="button"
              @click="showEditModal = false; editingStudent = null"
              class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
            >
              취소
            </button>
            <button
              type="submit"
              :disabled="formLoading"
              class="btn-primary"
            >
              {{ formLoading ? '저장 중...' : '저장' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Enrollment Management Modal -->
    <div v-if="showEnrollModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg w-full max-w-2xl mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-xl font-bold">{{ selectedStudent?.name }} - 수강 관리</h2>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
          <!-- Current Enrollments -->
          <div class="mb-6">
            <h3 class="text-lg font-semibold mb-3">현재 수강 중인 수업</h3>
            <div v-if="enrollmentsLoading" class="text-center text-gray-500 py-4">
              로딩 중...
            </div>
            <div v-else-if="studentEnrollments.length === 0" class="text-center text-gray-500 py-4 bg-gray-50 rounded-lg">
              등록된 수업이 없습니다.
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="enrollment in studentEnrollments"
                :key="enrollment.id"
                class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
              >
                <div>
                  <span class="font-medium">{{ enrollment.lesson_name }}</span>
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

          <!-- Add New Enrollment -->
          <div class="border-t border-gray-200 pt-6">
            <h3 class="text-lg font-semibold mb-3">새 수업 등록</h3>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">수업 선택 *</label>
                <select
                  v-model="enrollFormData.lesson_id"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                >
                  <option :value="0">수업을 선택하세요</option>
                  <option
                    v-for="lesson in lessons"
                    :key="lesson.id"
                    :value="lesson.id"
                  >
                    {{ lesson.name }} ({{ formatDays(lesson.days) }}) - {{ lesson.current_students }}/{{ lesson.max_students }}명
                  </option>
                </select>
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
                @click="handleEnroll"
                :disabled="formLoading || !enrollFormData.lesson_id"
                class="w-full btn-primary"
              >
                {{ formLoading ? '등록 중...' : '수강 등록' }}
              </button>
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-gray-200">
          <button
            @click="showEnrollModal = false; selectedStudent = null"
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
</style>
