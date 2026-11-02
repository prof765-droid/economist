import type { RouteObject } from 'react-router'
import { CalculatorPage } from './pages/CalculatorPage'
import { NotFoundPage } from './pages/NotFoundPage'
import { SavedCalculationPage } from './pages/SavedCalculationPage'

// Отдельно от App: тесты собирают из этого же списка роутер в памяти и проверяют настоящие маршруты, а не копию
export const routes: RouteObject[] = [
  { path: '/', element: <CalculatorPage /> },
  { path: '/c/:id', element: <SavedCalculationPage /> },
  { path: '*', element: <NotFoundPage /> },
]
