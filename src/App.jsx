import { Routes, Route, Navigate, useLocation } from 'react-router-dom'
import { useEffect } from 'react'

import PublicLayout from './components/PublicLayout.jsx'
import ProtectedRoute from './components/ProtectedRoute.jsx'
import AdminLayout from './components/admin/AdminLayout.jsx'

import Home from './pages/public/Home.jsx'
import Services from './pages/public/Services.jsx'
import Streaming from './pages/public/Streaming.jsx'
import Ecommerce from './pages/public/Ecommerce.jsx'
import Iptv from './pages/public/Iptv.jsx'
import Blog from './pages/public/Blog.jsx'
import BlogPost from './pages/public/BlogPost.jsx'
import Contact from './pages/public/Contact.jsx'
import NotFound from './pages/public/NotFound.jsx'

import Login from './pages/auth/Login.jsx'

import Dashboard from './pages/admin/Dashboard.jsx'
import Leads from './pages/admin/Leads.jsx'
import Clients from './pages/admin/Clients.jsx'
import Projects from './pages/admin/Projects.jsx'
import Products from './pages/admin/Products.jsx'
import Orders from './pages/admin/Orders.jsx'
import BlogAdmin from './pages/admin/BlogAdmin.jsx'
import Messages from './pages/admin/Messages.jsx'
import Settings from './pages/admin/Settings.jsx'

function ScrollToTop() {
  const { pathname } = useLocation()
  useEffect(() => {
    window.scrollTo(0, 0)
  }, [pathname])
  return null
}

export default function App() {
  return (
    <>
      <ScrollToTop />
      <Routes>
        {/* ---------- Site public ---------- */}
        <Route element={<PublicLayout />}>
          <Route path="/" element={<Home />} />
          <Route path="/services" element={<Services />} />
          <Route path="/streaming" element={<Streaming />} />
          <Route path="/e-commerce" element={<Ecommerce />} />
          <Route path="/iptv" element={<Iptv />} />
          <Route path="/blog" element={<Blog />} />
          <Route path="/blog/:slug" element={<BlogPost />} />
          <Route path="/contact" element={<Contact />} />
          <Route path="*" element={<NotFound />} />
        </Route>

        {/* ---------- Connexion admin (accessible uniquement par URL directe) ---------- */}
        <Route path="/admin/login" element={<Login />} />

        {/* ---------- Espace admin protege ---------- */}
        <Route
          path="/admin"
          element={
            <ProtectedRoute>
              <AdminLayout />
            </ProtectedRoute>
          }
        >
          <Route index element={<Dashboard />} />
          <Route path="prospects" element={<Leads />} />
          <Route path="clients" element={<Clients />} />
          <Route path="projets" element={<Projects />} />
          <Route path="produits" element={<Products />} />
          <Route path="commandes" element={<Orders />} />
          <Route path="blog" element={<BlogAdmin />} />
          <Route path="messages" element={<Messages />} />
          <Route path="parametres" element={<Settings />} />
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </>
  )
}
