<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'

interface Student {
  id: number
  name: string
  phone: string
  branch: string
  status: string
  created_at: string
}

const students = ref<Student[]>([])
const loading = ref(false)
const showModal = ref(false)
const showEditModal = ref(false)
const editingStudent = ref<Student | null>(null)

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

const formError = ref('')
const formLoading = ref(false)

async function fetchStudents() {
  loading.value = true
  try {
    const response = await api.get('/admin/students')
    students.value = response.data.students || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  formLoading.value = true
  formError.value = ''

  try {
    await api.post('/admin/students', formData.value)
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
    await api.put(`/admin/students/${editingStudent.value.id}`, editFormData.value)
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
    await api.delete(`/admin/students/${student.id}`)
    fetchStudents()
  } catch (e: any) {
    alert(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function getStatusBadge(status: string) {
  const badges: Record<string, string> = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-gray-100 text-gray-800',
    pending: 'bg-yellow-100 text-yellow-800',
  }
  return badges[status] || 'bg-gray-100 text-gray-800'
}

function getStatusText(status: string) {
  const texts: Record<string, string> = {
    active: '활성',
    inactive: '비활성',
    pending: '대기',
  }
  return texts[status] || status
}

onMounted(() => {
  fetchStudents()
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
  </div>
</template>

<style scoped>
.btn-primary {
  @apply px-4 py-2 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 disabled:opacity-50;
}
</style>
