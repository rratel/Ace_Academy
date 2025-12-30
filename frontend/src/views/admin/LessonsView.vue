<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'

interface Lesson {
  id: number
  name: string
  description: string | null
  days_of_week: string[]
  start_time: string
  end_time: string
  price: number
  max_students: number
  current_students: number
  is_active: boolean
}

const lessons = ref<Lesson[]>([])
const loading = ref(true)
const error = ref('')
const showModal = ref(false)
const editingLesson = ref<Lesson | null>(null)

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
  days_of_week: [] as string[],
  start_time: '',
  end_time: '',
  price: 0,
  max_students: 10
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

function openCreateModal() {
  editingLesson.value = null
  form.value = {
    name: '',
    description: '',
    days_of_week: [],
    start_time: '',
    end_time: '',
    price: 0,
    max_students: 10
  }
  showModal.value = true
}

function openEditModal(lesson: Lesson) {
  editingLesson.value = lesson
  form.value = {
    name: lesson.name,
    description: lesson.description || '',
    days_of_week: [...lesson.days_of_week],
    start_time: lesson.start_time,
    end_time: lesson.end_time,
    price: lesson.price,
    max_students: lesson.max_students
  }
  showModal.value = true
}

async function saveLesson() {
  try {
    if (editingLesson.value) {
      await adminApi.updateLesson(editingLesson.value.id, form.value)
    } else {
      await adminApi.createLesson(form.value as any)
    }
    showModal.value = false
    await loadLessons()
  } catch (e: any) {
    alert(e.response?.data?.message || '저장에 실패했습니다.')
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

function formatDays(days: string[]): string {
  return days.map(d => dayLabels[d] || d).join(', ')
}

function formatPrice(price: number): string {
  return new Intl.NumberFormat('ko-KR').format(price) + '원'
}

onMounted(() => {
  loadLessons()
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
      <div v-else v-for="lesson in lessons" :key="lesson.id" class="card">
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
              <span class="font-medium">{{ formatDays(lesson.days_of_week) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">시간</span>
              <span class="font-medium">{{ lesson.start_time }} - {{ lesson.end_time }}</span>
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

          <div class="flex gap-2 mt-4 pt-4 border-t border-gray-100">
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

    <!-- Modal -->
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
                <input type="checkbox" :value="key" v-model="form.days_of_week" class="rounded" />
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

          <div class="flex gap-3 pt-4">
            <button type="button" @click="showModal = false" class="flex-1 btn-secondary">
              취소
            </button>
            <button type="submit" class="flex-1 btn-primary">
              {{ editingLesson ? '수정' : '추가' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
