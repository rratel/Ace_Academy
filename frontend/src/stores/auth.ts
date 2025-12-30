import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/services/api'
import { useRouter } from 'vue-router'

export interface User {
  id: number
  name: string
  email: string
  phone: string
  role: 'super_admin' | 'branch_admin' | 'student' | 'parent'
  status: 'pending' | 'active' | 'inactive'
  branch_id: number | null
  branch?: {
    id: number
    name: string
    code: string
  }
}

export const useAuthStore = defineStore('auth', () => {
  const router = useRouter()

  // State
  const user = ref<User | null>(null)
  const token = ref<string | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isSuperAdmin = computed(() => user.value?.role === 'super_admin')
  const isBranchAdmin = computed(() => user.value?.role === 'branch_admin')
  const isAdmin = computed(() => isSuperAdmin.value || isBranchAdmin.value)
  const isStudent = computed(() => user.value?.role === 'student')
  const isParent = computed(() => user.value?.role === 'parent')
  const isActive = computed(() => user.value?.status === 'active')
  const isPending = computed(() => user.value?.status === 'pending')

  // Actions
  async function login(email: string, password: string) {
    loading.value = true
    error.value = null

    try {
      const response = await authApi.login({ email, password })
      const { token: authToken, user: userData } = response.data

      token.value = authToken
      user.value = userData

      localStorage.setItem('token', authToken)
      localStorage.setItem('user', JSON.stringify(userData))

      // Redirect based on role
      if (userData.role === 'super_admin' || userData.role === 'branch_admin') {
        router.push('/admin')
      } else if (userData.role === 'parent') {
        router.push('/parent')
      } else {
        router.push('/student')
      }

      return true
    } catch (e: any) {
      error.value = e.response?.data?.message || '로그인에 실패했습니다.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function register(data: {
    name: string
    email: string
    password: string
    password_confirmation: string
    phone: string
    branch_id?: number
  }) {
    loading.value = true
    error.value = null

    try {
      await authApi.register(data)
      return true
    } catch (e: any) {
      error.value = e.response?.data?.message || '회원가입에 실패했습니다.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch (e) {
      // Ignore logout errors
    } finally {
      user.value = null
      token.value = null
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      router.push('/login')
    }
  }

  async function fetchUser() {
    if (!token.value) return false

    loading.value = true
    try {
      const response = await authApi.me()
      user.value = response.data
      localStorage.setItem('user', JSON.stringify(response.data))
      return true
    } catch (e) {
      logout()
      return false
    } finally {
      loading.value = false
    }
  }

  function initialize() {
    const storedToken = localStorage.getItem('token')
    const storedUser = localStorage.getItem('user')

    if (storedToken && storedUser) {
      token.value = storedToken
      try {
        user.value = JSON.parse(storedUser)
      } catch {
        logout()
      }
    }
  }

  return {
    // State
    user,
    token,
    loading,
    error,

    // Getters
    isAuthenticated,
    isSuperAdmin,
    isBranchAdmin,
    isAdmin,
    isStudent,
    isParent,
    isActive,
    isPending,

    // Actions
    login,
    register,
    logout,
    fetchUser,
    initialize,
  }
})
