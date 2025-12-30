import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

interface Student {
  id: number
  name: string
  phone?: string
  branch?: string
}

export const useStudentStore = defineStore('student', () => {
  const student = ref<Student | null>(null)
  const sessionToken = ref<string | null>(localStorage.getItem('student_session_token'))
  const expiresAt = ref<string | null>(localStorage.getItem('student_session_expires'))

  const isAuthenticated = computed(() => {
    if (!sessionToken.value || !expiresAt.value) return false
    return new Date(expiresAt.value) > new Date()
  })

  async function verify(name: string, phone: string) {
    const response = await api.post('/student/verify', { name, phone })

    if (response.data.success) {
      sessionToken.value = response.data.session_token
      expiresAt.value = response.data.expires_at
      student.value = response.data.student

      localStorage.setItem('student_session_token', response.data.session_token)
      localStorage.setItem('student_session_expires', response.data.expires_at)
    }

    return response.data
  }

  async function fetchMe() {
    if (!sessionToken.value) return null

    try {
      const response = await api.get('/student/me', {
        headers: { 'X-Student-Token': sessionToken.value }
      })
      student.value = response.data.student
      return response.data.student
    } catch (error) {
      // Session expired
      logout()
      throw error
    }
  }

  async function getQrToken() {
    if (!sessionToken.value) throw new Error('Not authenticated')

    const response = await api.get('/student/qr-token', {
      headers: { 'X-Student-Token': sessionToken.value }
    })
    return response.data
  }

  function logout() {
    if (sessionToken.value) {
      api.post('/student/logout', {}, {
        headers: { 'X-Student-Token': sessionToken.value }
      }).catch(() => {})
    }

    student.value = null
    sessionToken.value = null
    expiresAt.value = null
    localStorage.removeItem('student_session_token')
    localStorage.removeItem('student_session_expires')
  }

  function initialize() {
    const token = localStorage.getItem('student_session_token')
    const expires = localStorage.getItem('student_session_expires')

    if (token && expires) {
      if (new Date(expires) > new Date()) {
        sessionToken.value = token
        expiresAt.value = expires
      } else {
        logout()
      }
    }
  }

  return {
    student,
    sessionToken,
    isAuthenticated,
    verify,
    fetchMe,
    getQrToken,
    logout,
    initialize,
  }
})
