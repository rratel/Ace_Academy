<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'

interface RefundCandidate {
  payment_id: number
  student_name: string
  lesson: string
  paid_amount: number
  calculated_refund: number
  paid_at: string
}

const refunds = ref<RefundCandidate[]>([])
const loading = ref(true)
const error = ref('')
const showModal = ref(false)
const selectedPayment = ref<RefundCandidate | null>(null)
const refundAmount = ref(0)
const refundNotes = ref('')
const processing = ref(false)

async function loadRefunds() {
  loading.value = true
  error.value = ''
  try {
    const response = await adminApi.refunds()
    refunds.value = response.data.refund_candidates
  } catch (e: any) {
    error.value = e.response?.data?.message || '환급 정보를 불러오는데 실패했습니다.'
  } finally {
    loading.value = false
  }
}

function openRefundModal(payment: RefundCandidate) {
  selectedPayment.value = payment
  refundAmount.value = payment.calculated_refund
  refundNotes.value = ''
  showModal.value = true
}

async function processRefund() {
  if (!selectedPayment.value) return
  if (!confirm(`${formatPrice(refundAmount.value)}을 환급 처리하시겠습니까?`)) return

  processing.value = true
  try {
    await adminApi.processRefund(selectedPayment.value.payment_id, {
      amount: refundAmount.value,
      notes: refundNotes.value
    })
    showModal.value = false
    await loadRefunds()
    alert('환급 처리가 완료되었습니다.')
  } catch (e: any) {
    alert(e.response?.data?.message || '환급 처리에 실패했습니다.')
  } finally {
    processing.value = false
  }
}

function formatPrice(price: number): string {
  return new Intl.NumberFormat('ko-KR').format(price) + '원'
}

onMounted(() => {
  loadRefunds()
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">환급 관리</h1>

    <!-- Info Box -->
    <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg mb-6">
      <p class="text-sm">
        환급액은 <strong>(수강료 / 총 수업 횟수) × 출석 횟수</strong> 공식으로 자동 계산됩니다.
        환급 처리 전 금액을 조정할 수 있습니다.
      </p>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
      {{ error }}
    </div>

    <!-- Refunds Table -->
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">학생</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">수업</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">결제금액</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">환급예정액</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">결제일</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">작업</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="loading">
              <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                불러오는 중...
              </td>
            </tr>
            <tr v-else-if="refunds.length === 0">
              <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                환급 대상이 없습니다.
              </td>
            </tr>
            <tr v-else v-for="refund in refunds" :key="refund.payment_id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="font-medium text-gray-900">{{ refund.student_name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ refund.lesson }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-gray-900">
                {{ formatPrice(refund.paid_amount) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <span class="font-medium text-primary-600">{{ formatPrice(refund.calculated_refund) }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ refund.paid_at }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <button
                  @click="openRefundModal(refund)"
                  class="text-primary-600 hover:text-primary-900 text-sm font-medium"
                >
                  환급 처리
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Summary -->
    <div v-if="!loading && refunds.length > 0" class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="card">
        <div class="card-body text-center">
          <div class="text-2xl font-bold text-gray-900">{{ refunds.length }}건</div>
          <div class="text-sm text-gray-500">환급 대상</div>
        </div>
      </div>
      <div class="card">
        <div class="card-body text-center">
          <div class="text-2xl font-bold text-gray-900">
            {{ formatPrice(refunds.reduce((sum, r) => sum + r.paid_amount, 0)) }}
          </div>
          <div class="text-sm text-gray-500">총 결제금액</div>
        </div>
      </div>
      <div class="card">
        <div class="card-body text-center">
          <div class="text-2xl font-bold text-primary-600">
            {{ formatPrice(refunds.reduce((sum, r) => sum + r.calculated_refund, 0)) }}
          </div>
          <div class="text-sm text-gray-500">총 환급예정액</div>
        </div>
      </div>
    </div>

    <!-- Refund Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4">
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-xl font-bold">환급 처리</h2>
        </div>

        <div v-if="selectedPayment" class="p-6 space-y-4">
          <div class="bg-gray-50 rounded-lg p-4 space-y-2">
            <div class="flex justify-between">
              <span class="text-gray-500">학생</span>
              <span class="font-medium">{{ selectedPayment.student_name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">수업</span>
              <span class="font-medium">{{ selectedPayment.lesson }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">결제금액</span>
              <span class="font-medium">{{ formatPrice(selectedPayment.paid_amount) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">자동계산 환급액</span>
              <span class="font-medium text-primary-600">{{ formatPrice(selectedPayment.calculated_refund) }}</span>
            </div>
          </div>

          <div>
            <label class="label">환급금액 (원)</label>
            <input
              v-model.number="refundAmount"
              type="number"
              class="input"
              :max="selectedPayment.paid_amount"
              min="0"
            />
            <p class="text-xs text-gray-500 mt-1">최대 {{ formatPrice(selectedPayment.paid_amount) }}까지 가능</p>
          </div>

          <div>
            <label class="label">메모 (선택)</label>
            <textarea v-model="refundNotes" class="input" rows="2" placeholder="환급 사유 등"></textarea>
          </div>

          <div class="flex gap-3 pt-4">
            <button type="button" @click="showModal = false" class="flex-1 btn-secondary" :disabled="processing">
              취소
            </button>
            <button @click="processRefund" class="flex-1 btn-primary" :disabled="processing || refundAmount <= 0">
              {{ processing ? '처리 중...' : '환급 처리' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
