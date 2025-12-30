<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'

interface User {
  id: number
  name: string
  email: string
  phone: string
  role: string
  status: string
  branch: string | null
  created_at: string
}

const users = ref<User[]>([])
const loading = ref(true)
const error = ref('')
const filter = ref({
  role: '',
  status: '',
  search: ''
})

const roleLabels: Record<string, string> = {
  parent: '학부모',
  branch_admin: '지점관리자',
  super_admin: '최고관리자'
}

const statusLabels: Record<string, string> = {
  active: '활성',
  pending: '승인대기',
  inactive: '비활성',
  rejected: '거절'
}

const statusColors: Record<string, string> = {
  active: 'badge-success',
  pending: 'badge-warning',
  inactive: 'badge-info',
  rejected: 'badge-danger'
}

async function loadUsers() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filter.value.role) params.append('role', filter.value.role)
    if (filter.value.status) params.append('status', filter.value.status)
    if (filter.value.search) params.append('search', filter.value.search)

    const response = await adminApi.users(Object.fromEntries(params))
    users.value = response.data.users
  } catch (e: any) {
    error.value = e.response?.data?.message || '사용자 목록을 불러오는데 실패했습니다.'
    console.error('Failed to load users:', e)
  } finally {
    loading.value = false
  }
}

async function approveUser(id: number) {
  if (!confirm('이 사용자를 승인하시겠습니까?')) return
  try {
    await adminApi.approveUser(id)
    await loadUsers()
  } catch (e: any) {
    alert(e.response?.data?.message || '승인 처리에 실패했습니다.')
  }
}

async function updateStatus(id: number, status: string) {
  const statusText = statusLabels[status] || status
  if (!confirm(`이 사용자를 '${statusText}' 상태로 변경하시겠습니까?`)) return
  try {
    await adminApi.updateUserStatus(id, status)
    await loadUsers()
  } catch (e: any) {
    alert(e.response?.data?.message || '상태 변경에 실패했습니다.')
  }
}

onMounted(() => {
  loadUsers()
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">사용자 관리 (학부모/관리자)</h1>

    <!-- Filters -->
    <div class="card mb-6">
      <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="label">역할</label>
            <select v-model="filter.role" @change="loadUsers" class="input">
              <option value="">전체</option>
              <option value="parent">학부모</option>
              <option value="branch_admin">지점관리자</option>
            </select>
          </div>
          <div>
            <label class="label">상태</label>
            <select v-model="filter.status" @change="loadUsers" class="input">
              <option value="">전체</option>
              <option value="active">활성</option>
              <option value="pending">승인대기</option>
              <option value="inactive">비활성</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="label">검색</label>
            <input
              v-model="filter.search"
              @keyup.enter="loadUsers"
              type="text"
              placeholder="이름, 이메일, 전화번호"
              class="input"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
      {{ error }}
    </div>

    <!-- Users Table -->
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">이름</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">이메일</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">전화번호</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">역할</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">상태</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">지점</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">가입일</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">작업</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="loading">
              <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                불러오는 중...
              </td>
            </tr>
            <tr v-else-if="users.length === 0">
              <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                사용자가 없습니다.
              </td>
            </tr>
            <tr v-else v-for="user in users" :key="user.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="font-medium text-gray-900">{{ user.name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ user.email }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ user.phone }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="badge badge-info">{{ roleLabels[user.role] || user.role }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="['badge', statusColors[user.status]]">
                  {{ statusLabels[user.status] || user.status }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ user.branch || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ user.created_at }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-right space-x-2">
                <button
                  v-if="user.status === 'pending'"
                  @click="approveUser(user.id)"
                  class="text-green-600 hover:text-green-900 text-sm font-medium"
                >
                  승인
                </button>
                <button
                  v-if="user.status === 'active'"
                  @click="updateStatus(user.id, 'inactive')"
                  class="text-yellow-600 hover:text-yellow-900 text-sm font-medium"
                >
                  비활성화
                </button>
                <button
                  v-if="user.status === 'inactive'"
                  @click="updateStatus(user.id, 'active')"
                  class="text-blue-600 hover:text-blue-900 text-sm font-medium"
                >
                  활성화
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
