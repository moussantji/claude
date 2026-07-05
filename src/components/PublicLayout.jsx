import { Outlet } from 'react-router-dom'
import Navbar from './Navbar.jsx'
import Footer from './Footer.jsx'
import BottomNav from './BottomNav.jsx'

export default function PublicLayout() {
  return (
    <>
      <Navbar />
      <main className="public-main">
        <Outlet />
      </main>
      <Footer />
      <BottomNav />
    </>
  )
}
