<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'

interface Branch {
  id: number
  name: string
  code: string
  address: string | null
  phone: string | null
  is_active: boolean
  students_count: number
  lessons_count: number
}

const branches = ref<Branch[]>([])
const loading = ref(true)
const error = ref('')
const showModal = ref(false)
const editingBranch = ref<Branch | null>(null)

const form = ref({
  name: '',
  code: '',
  address: '',
  phone: ''
})

async function loadBranches() {
  loading.value = true
  error.value = ''
  try {
    const response = await adminApi.branches()
    branches.value = response.data.branches
  } catch (e: any) {
    error.value = e.response?.data?.message || '지점 목록을 불러오는데 실패했습니다.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingBranch.value = null
  form.value = {
    name: '',
    code: '',
    address: '',
    phone: ''
  }
  showModal.value = true
}

function openEditModal(branch: Branch) {
  editingBranch.value = branch
  form.value = {
    name: branch.name,
    code: branch.code,
    address: branch.address || '',
    phone: branch.phone || ''
  }
  showModal.value = true
}

async function saveBranch() {
  try {
    if (editingBranch.value) {
      await adminApi.updateBranch(editingBranch.value.id, form.value)
    } else {
      await adminApi.createBranch(form.value)
    }
    showModal.value = false
    await loadBranches()
  } catch (e: any) {
    alert(e.response?.data?.message || '저장에 실패했습니다.')
  }
}

async function toggleActive(branch: Branch) {
  const action = branch.is_active ? '비활성화' : '활성화'
  if (!confirm(`${branch.name} 지점을 ${action}하시겠습니까?`)) return

  try {
    await adminApi.updateBranch(branch.id, { is_active: !branch.is_active })
    await loadBranches()
  } catch (e: any) {
    alert(e.response?.data?.message || '상태 변경에 실패했습니다.')
  }
}

onMounted(() => {
  loadBranches()
})
</script>

<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900">지점 관리</h1>
      <button @click="openCreateModal" class="btn-primary">
        + 새 지점 추가
      </button>
    </div>

    <!-- Info Box -->
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg mb-6">
      <p class="text-sm">
        지점 관리는 최고관리자만 접근할 수 있습니다. 지점을 비활성화하면 해당 지점의 모든 데이터가 숨겨집니다.
      </p>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
      {{ error }}
    </div>

    <!-- Branches Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-if="loading" class="col-span-full text-center py-8 text-gray-500">
        불러오는 중...
      </div>
      <div v-else-if="branches.length === 0" class="col-span-full text-center py-8 text-gray-500">
        등록된 지점이 없습니다.
      </div>
      <div v-else v-for="branch in branches" :key="branch.id" class="card">
        <div class="card-body">
          <div class="flex justify-between items-start mb-3">
            <div>
              <h3 class="font-semibold text-lg">{{ branch.name }}</h3>
              <p class="text-sm text-gray-500">{{ branch.code }}</p>
            </div>
            <span :class="['badge', branch.is_active ? 'badge-success' : 'badge-info']">
              {{ branch.is_active ? '활성' : '비활성' }}
            </span>
          </div>

          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-500">주소</span>
              <span class="font-medium">{{ branch.address || '-' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">전화번호</span>
              <span class="font-medium">{{ branch.phone || '-' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">학생 수</span>
              <span class="font-medium text-primary-600">{{ branch.students_count }}명</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">수업 수</span>
              <span class="font-medium">{{ branch.lessons_count }}개</span>
            </div>
          </div>

          <div class="flex gap-2 mt-4 pt-4 border-t border-gray-100">
            <button @click="openEditModal(branch)" class="flex-1 btn-secondary text-sm py-1.5">
              수정
            </button>
            <button @click="toggleActive(branch)"
                    :class="['flex-1 text-sm py-1.5', branch.is_active ? 'btn-secondary' : 'btn-primary']">
              {{ branch.is_active ? '비활성화' : '활성화' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary -->
    <div v-if="!loading && branches.length > 0" class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="card">
        <div class="card-body text-center">
          <div class="text-2xl font-bold text-gray-900">
            {{ branches.filter(b => b.is_active).length }}개
          </div>
          <div class="text-sm text-gray-500">활성 지점</div>
        </div>
      </div>
      <div class="card">
        <div class="card-body text-center">
          <div class="text-2xl font-bold text-primary-600">
            {{ branches.reduce((sum, b) => sum + b.students_count, 0) }}명
          </div>
          <div class="text-sm text-gray-500">전체 학생</div>
        </div>
      </div>
      <div class="card">
        <div class="card-body text-center">
          <div class="text-2xl font-bold text-gray-900">
            {{ branches.reduce((sum, b) => sum + b.lessons_count, 0) }}개
          </div>
          <div class="text-sm text-gray-500">전체 수업</div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4">
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-xl font-bold">{{ editingBranch ? '지점 수정' : '새 지점 추가' }}</h2>
        </div>

        <form @submit.prevent="saveBranch" class="p-6 space-y-4">
          <div>
            <label class="label">지점명</label>
            <input v-model="form.name" type="text" class="input" required placeholder="예: 강남점" />
          </div>

          <div>
            <label class="label">지점 코드</label>
            <input v-model="form.code" type="text" class="input" required placeholder="예: GANGNAM"
                   :disabled="!!editingBranch" />
            <p v-if="!editingBranch" class="text-xs text-gray-500 mt-1">영문 대문자로 입력 (수정 불가)</p>
          </div>

          <div>
            <label class="label">주소</label>
            <input v-model="form.address" type="text" class="input" placeholder="선택 사항" />
          </div>

          <div>
            <label class="label">전화번호</label>
            <input v-model="form.phone" type="tel" class="input" placeholder="선택 사항" />
          </div>

          <div class="flex gap-3 pt-4">
            <button type="button" @click="showModal = false" class="flex-1 btn-secondary">
              취소
            </button>
            <button type="submit" class="flex-1 btn-primary">
              {{ editingBranch ? '수정' : '추가' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
