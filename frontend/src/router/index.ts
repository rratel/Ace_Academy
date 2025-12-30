import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useStudentStore } from '@/stores/student'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // Public routes
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { guest: true },
    },

    // Student routes (separate auth flow - no login required)
    {
      path: '/student/verify',
      name: 'student-verify',
      component: () => import('@/views/student/VerifyView.vue'),
    },
    {
      path: '/student/qr',
      name: 'student-qr',
      component: () => import('@/views/student/QrView.vue'),
      meta: { requiresStudentAuth: true },
    },

    // Parent routes
    {
      path: '/parent',
      component: () => import('@/layouts/ParentLayout.vue'),
      meta: { requiresAuth: true, roles: ['parent'] },
      children: [
        {
          path: '',
          name: 'parent-dashboard',
          component: () => import('@/views/parent/DashboardView.vue'),
        },
        {
          path: 'children/:childId',
          name: 'parent-child-detail',
          component: () => import('@/views/parent/ChildDetailView.vue'),
        },
      ],
    },

    // Admin routes
    {
      path: '/admin',
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true, roles: ['super_admin', 'branch_admin'] },
      children: [
        {
          path: '',
          name: 'admin-dashboard',
          component: () => import('@/views/admin/DashboardView.vue'),
        },
        {
          path: 'users',
          name: 'admin-users',
          component: () => import('@/views/admin/UsersView.vue'),
        },
        {
          path: 'students',
          name: 'admin-students',
          component: () => import('@/views/admin/StudentsView.vue'),
        },
        {
          path: 'lessons',
          name: 'admin-lessons',
          component: () => import('@/views/admin/LessonsView.vue'),
        },
        {
          path: 'attendances',
          name: 'admin-attendances',
          component: () => import('@/views/admin/AttendancesView.vue'),
        },
        {
          path: 'refunds',
          name: 'admin-refunds',
          component: () => import('@/views/admin/RefundsView.vue'),
        },
        {
          path: 'branches',
          name: 'admin-branches',
          component: () => import('@/views/admin/BranchesView.vue'),
          meta: { roles: ['super_admin'] },
        },
        {
          path: 'qr-reader',
          name: 'admin-qr-reader',
          component: () => import('@/views/admin/QrReaderView.vue'),
        },
      ],
    },

    // Pending approval page
    {
      path: '/pending',
      name: 'pending',
      component: () => import('@/views/auth/PendingView.vue'),
      meta: { requiresAuth: true },
    },

    // Root redirect
    {
      path: '/',
      redirect: '/student/verify',
    },

    // Student shortcut
    {
      path: '/student',
      redirect: '/student/verify',
    },

    // 404
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
})

// Navigation guards
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  const studentStore = useStudentStore()

  // Initialize auth state from localStorage
  if (!authStore.isAuthenticated) {
    authStore.initialize()
  }

  // Initialize student session
  studentStore.initialize()

  // Student auth routes
  if (to.meta.requiresStudentAuth) {
    if (!studentStore.isAuthenticated) {
      return next('/student/verify')
    }
    return next()
  }

  const isAuthenticated = authStore.isAuthenticated
  const userRole = authStore.user?.role
  const userStatus = authStore.user?.status

  // Guest-only routes (login, register)
  if (to.meta.guest && isAuthenticated) {
    // Redirect authenticated users based on role
    if (authStore.isAdmin) {
      return next('/admin')
    } else if (authStore.isParent) {
      return next('/parent')
    } else {
      return next('/student/verify')
    }
  }

  // Protected routes
  if (to.meta.requiresAuth) {
    if (!isAuthenticated) {
      return next('/login')
    }

    // Check if user is pending approval
    if (userStatus === 'pending' && to.name !== 'pending') {
      return next('/pending')
    }

    // Check role access
    const allowedRoles = to.meta.roles as string[] | undefined
    if (allowedRoles && userRole && !allowedRoles.includes(userRole)) {
      // Redirect to appropriate dashboard based on actual role
      if (authStore.isAdmin) {
        return next('/admin')
      } else if (authStore.isParent) {
        return next('/parent')
      } else {
        return next('/student/verify')
      }
    }
  }

  next()
})

export default router
