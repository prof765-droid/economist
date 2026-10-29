// Зеркало DTO бэка (дизайн 4.2, 4.3, 5). Деньги и ставка — строки: JS-числа — это float.

export type RepaymentType = 'annuity' | 'differentiated'

export interface MortgageParameters {
  propertyPrice: string
  downPayment: string
  annualRate: string
  termMonths: number
  repaymentType: RepaymentType
  startDate: string
}

export interface Summary {
  creditAmount: string
  monthlyPayment: string
  totalPayment: string
  overpayment: string
}

export interface Payment {
  number: number
  date: string
  payment: string
  interest: string
  principal: string
  balance: string
}

export interface CalculationResult {
  // только у сохранённого расчёта (GET /api/calculations/{id})
  id?: string
  parameters?: MortgageParameters
  summary: Summary
  schedule: Payment[]
}

export interface SaveResponse {
  id: string
}

export interface Violation {
  field: string
  message: string
}

export interface ProblemDetails {
  type: string
  title: string
  status: number
  violations?: Violation[]
}
