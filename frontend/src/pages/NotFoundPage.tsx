import { Link } from 'react-router'

export function NotFoundPage() {
  return (
    <main>
      <h1>Страница не найдена</h1>
      <Link to="/">К калькулятору</Link>
    </main>
  )
}
