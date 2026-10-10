import { Link } from 'react-router-dom'

export default function Footer() {
  return (
    <footer className="footer">
      <div className="container">
        <div className="footer-grid">
          <div>
            <div className="brand" style={{ marginBottom: 14 }}>
              <span className="logo"><span /><span /><span /></span>
              <span>Manden Baoubab</span>
            </div>
            <p style={{ maxWidth: 320 }}>
              Entreprise numerique malienne. Nous creons des sites web, applications, logiciels
              et plateformes (streaming, e-commerce, IPTV) qui font grandir votre activite.
            </p>
            <p style={{ marginBottom: 0 }}>
              ACI 2000, Bamako - Mali<br />
              +223 20 00 00 00 - contact@mandenbaoubab.com
            </p>
          </div>

          <div>
            <h4>Services</h4>
            <Link to="/services">Creation de sites</Link>
            <Link to="/services">Applications web</Link>
            <Link to="/services">Logiciels PC</Link>
            <Link to="/contact">Devis gratuit</Link>
          </div>

          <div>
            <h4>Plateformes</h4>
            <Link to="/streaming">Manden Stream</Link>
            <Link to="/e-commerce">Manden Shop</Link>
            <Link to="/iptv">Manden TV IPTV</Link>
            <Link to="/blog">Blog</Link>
          </div>

          <div>
            <h4>Entreprise</h4>
            <Link to="/contact">Nous contacter</Link>
            <Link to="/blog">Actualites</Link>
            <a href="#!">Recrutement</a>
            <a href="#!">Mentions legales</a>
          </div>
        </div>

        <div className="footer-bottom">
          <span>&copy; {new Date().getFullYear()} Manden Baoubab. Tous droits reserves.</span>
          <span>
            Fait avec fierte au Mali &middot;{' '}
            <Link to="/admin/login" className="admin-link">Espace administration</Link>
          </span>
        </div>
      </div>
    </footer>
  )
}
