import axios, { type AxiosInstance, type AxiosError, type InternalAxiosRequestConfig } from 'axios'

const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Token refresh state
let isRefreshing = false
let failedQueue: Array<{
  resolve: (value: unknown) => void
  reject: (reason?: unknown) => void
}> = []

const processQueue = (error: Error | null, token: string | null = null) => {
  failedQueue.forEach((prom) => {
    if (error) {
      prom.reject(error)
    } else {
      prom.resolve(token)
    }
  })
  failedQueue = []
}

// Request interceptor - Add auth token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor - Handle errors and token refresh
api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean }

    // If 401 and not already retrying
    if (error.response?.status === 401 && !originalRequest._retry) {
      // Don't try to refresh on login/register/refresh endpoints
      const skipRefreshPaths = ['/auth/login', '/auth/register', '/auth/refresh']
      if (skipRefreshPaths.some(path => originalRequest.url?.includes(path))) {
        return Promise.reject(error)
      }

      if (isRefreshing) {
        // Queue this request while refresh is in progress
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        }).then((token) => {
          originalRequest.headers.Authorization = `Bearer ${token}`
          return api(originalRequest)
        }).catch((err) => {
          return Promise.reject(err)
        })
      }

      originalRequest._retry = true
      isRefreshing = true

      try {
        const response = await axios.post(
          `${import.meta.env.VITE_API_URL || '/api'}/auth/refresh`,
          {},
          {
            headers: {
              Authorization: `Bearer ${localStorage.getItem('token')}`,
            },
          }
        )

        const newToken = response.data.token
        localStorage.setItem('token', newToken)

        api.defaults.headers.common['Authorization'] = `Bearer ${newToken}`
        originalRequest.headers.Authorization = `Bearer ${newToken}`

        processQueue(null, newToken)
        return api(originalRequest)
      } catch (refreshError) {
        processQueue(refreshError as Error, null)

        // Token refresh failed - logout user
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        window.location.href = '/login'

        return Promise.reject(refreshError)
      } finally {
        isRefreshing = false
      }
    }

    return Promise.reject(error)
  }
)

export default api

// Auth API
export const authApi = {
  login: (credentials: { email: string; password: string }) =>
    api.post('/auth/login', credentials),

  register: (data: {
    name: string
    email: string
    password: string
    password_confirmation: string
    phone: string
    branch_id?: number
  }) => api.post('/auth/register', data),

  logout: () => api.post('/auth/logout'),

  me: () => api.get('/auth/me'),

  refreshToken: () => api.post('/auth/refresh'),
}

// Student API
export const studentApi = {
  dashboard: () => api.get('/student/dashboard'),

  getQrToken: () => api.get('/qr/token'),

  enrollments: () => api.get('/student/enrollments'),

  enroll: (lessonId: number) => api.post('/student/enrollments', { lesson_id: lessonId }),

  attendances: (params?: { month?: string }) => api.get('/student/attendances', { params }),

  payments: () => api.get('/student/payments'),
}

// Parent API
export const parentApi = {
  children: () => api.get('/parent/children'),

  childAttendances: (childId: number, params?: { month?: string }) =>
    api.get(`/parent/children/${childId}/attendances`, { params }),

  childEnrollments: (childId: number) =>
    api.get(`/parent/children/${childId}/enrollments`),
}

// Admin API
export const adminApi = {
  // Users
  users: (params?: { status?: string; role?: string; search?: string }) =>
    api.get('/admin/users', { params }),

  approveUser: (userId: number) => api.patch(`/admin/users/${userId}/approve`),

  updateUserStatus: (userId: number, status: string) =>
    api.patch(`/admin/users/${userId}/status`, { status }),

  // Lessons
  lessons: (params?: { is_active?: boolean }) =>
    api.get('/admin/lessons', { params }),

  createLesson: (data: {
    name: string
    description?: string
    price: number
    days: string[]
    start_time: string
    end_time: string
    max_students: number
    branch_id: number
  }) => api.post('/admin/lessons', data),

  updateLesson: (lessonId: number, data: Partial<{
    name: string
    description: string
    price: number
    days: string[]
    start_time: string
    end_time: string
    max_students: number
    is_active: boolean
  }>) => api.put(`/admin/lessons/${lessonId}`, data),

  deleteLesson: (lessonId: number) => api.delete(`/admin/lessons/${lessonId}`),

  // Students
  students: (params?: { status?: string; search?: string }) =>
    api.get('/admin/students', { params }),

  createStudent: (data: {
    name: string
    phone: string
    birth_date?: string
    gender?: string
    school?: string
    grade?: number
    parent_id?: number
    notes?: string
  }) => api.post('/admin/students', data),

  updateStudent: (studentId: number, data: Partial<{
    name: string
    phone: string
    birth_date: string
    gender: string
    school: string
    grade: number
    status: string
    parent_id: number
    notes: string
  }>) => api.put(`/admin/students/${studentId}`, data),

  deleteStudent: (studentId: number) => api.delete(`/admin/students/${studentId}`),

  // Enrollments
  enrollments: (params?: { status?: string; lesson_id?: number; student_id?: number }) =>
    api.get('/admin/enrollments', { params }),

  createEnrollment: (data: {
    student_id: number
    lesson_id: number
    remaining_sessions?: number
    expires_at?: string
  }) => api.post('/admin/enrollments', data),

  approveEnrollment: (enrollmentId: number) => api.patch(`/admin/enrollments/${enrollmentId}/approve`),

  rejectEnrollment: (enrollmentId: number) => api.patch(`/admin/enrollments/${enrollmentId}/reject`),

  // Attendances
  attendances: (params?: { lesson_id?: number; date?: string }) =>
    api.get('/admin/attendances', { params }),

  updateAttendance: (attendanceId: number, data: { status: string; note?: string }) =>
    api.patch(`/admin/attendances/${attendanceId}`, data),

  bulkAttendance: (attendances: Array<{ enrollment_id: number; status: string; date: string }>) =>
    api.post('/admin/attendances/bulk', { attendances }),

  // Payments
  payments: (params?: { type?: string; status?: string; student_id?: number }) =>
    api.get('/admin/payments', { params }),

  createPayment: (data: {
    enrollment_id: number
    type: string
    amount: number
    method?: string
    notes?: string
  }) => api.post('/admin/payments', data),

  // Refunds
  refunds: (params?: { status?: string }) =>
    api.get('/admin/refunds', { params }),

  calculateRefunds: (paymentId: number) =>
    api.post('/admin/refunds/calculate', { payment_id: paymentId }),

  processRefund: (paymentId: number, data: { amount: number; notes?: string }) =>
    api.post(`/admin/refunds/${paymentId}`, data),

  // Branches (Super Admin only)
  branches: () => api.get('/admin/branches'),

  createBranch: (data: { name: string; code: string; address?: string; phone?: string }) =>
    api.post('/admin/branches', data),

  updateBranch: (branchId: number, data: Partial<{ name: string; code: string; address?: string; phone?: string; is_active: boolean }>) =>
    api.put(`/admin/branches/${branchId}`, data),

  // Dashboard
  dashboard: () => api.get('/admin/dashboard'),
}

// QR Validation API (for QR reader mode)
export const qrApi = {
  validate: (token: string) => api.post('/qr/validate', { token }),
}
