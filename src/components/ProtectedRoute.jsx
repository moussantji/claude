import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext.jsx'

// Empeche l'acces a l'espace admin sans connexion.
// La page de connexion n'est liee nulle part dans la navigation publique :
// seuls les administrateurs connaissant l'URL /admin y accedent.
export default function ProtectedRoute({ children }) {
  const { isAuthenticated } = useAuth()
  const location = useLocation()

  if (!isAuthenticated) {
    return <Navigate to="/admin/login" replace state={{ from: location }} />
  }
  return children
}
