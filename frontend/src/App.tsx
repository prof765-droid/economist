import { createBrowserRouter } from 'react-router'
import { RouterProvider } from 'react-router/dom'
import { CalculatorPage } from './pages/CalculatorPage'
import { NotFoundPage } from './pages/NotFoundPage'
import { SavedCalculationPage } from './pages/SavedCalculationPage'

const router = createBrowserRouter([
  { path: '/', element: <CalculatorPage /> },
  { path: '/c/:id', element: <SavedCalculationPage /> },
  { path: '*', element: <NotFoundPage /> },
])

function App() {
  return <RouterProvider router={router} />
}

export default App
