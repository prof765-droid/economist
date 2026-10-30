import { Link } from 'react-router'
import page from './Page.module.css'

export function NotFoundPage() {
  return (
    <main className={`${page.page} ${page.centered}`}>
      <h1>Страница не найдена</h1>
      <Link to="/">К калькулятору</Link>
    </main>
  )
}
