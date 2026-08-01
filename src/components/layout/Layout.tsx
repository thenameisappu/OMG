import { Outlet } from 'react-router-dom';
import Navbar from './Navbar';
import Footer from './Footer';

const WHATSAPP_NUMBER = '918147736396';
const WHATSAPP_MESSAGE = encodeURIComponent('Hi OMG! I\'d like to know more about your floral arrangements and gifts 🌸');
const WHATSAPP_URL = `https://wa.me/${WHATSAPP_NUMBER}?text=${WHATSAPP_MESSAGE}`;

export default function Layout() {
  return (
    <div className="flex min-h-screen flex-col bg-background">
      <Navbar />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />

      {/* WhatsApp Floating Button */}
      <a
        href={WHATSAPP_URL}
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Chat with us on WhatsApp"
        style={{
          position: 'fixed',
          bottom: '28px',
          right: '28px',
          zIndex: 9999,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          width: '48px',
          height: '48px',
          borderRadius: '50%',
          backgroundColor: '#25D366',
          boxShadow: '0 4px 20px rgba(37, 211, 102, 0.45)',
          transition: 'transform 0.2s ease, box-shadow 0.2s ease',
          textDecoration: 'none',
        }}
        onMouseEnter={e => {
          (e.currentTarget as HTMLAnchorElement).style.transform = 'scale(1.12)';
          (e.currentTarget as HTMLAnchorElement).style.boxShadow = '0 6px 28px rgba(37, 211, 102, 0.6)';
        }}
        onMouseLeave={e => {
          (e.currentTarget as HTMLAnchorElement).style.transform = 'scale(1)';
          (e.currentTarget as HTMLAnchorElement).style.boxShadow = '0 4px 20px rgba(37, 211, 102, 0.45)';
        }}
      >
        {/* Pulse ring */}
        <span style={{
          position: 'absolute',
          width: '48px',
          height: '48px',
          borderRadius: '50%',
          backgroundColor: '#25D366',
          opacity: 0.35,
          animation: 'wa-pulse 2s ease-out infinite',
        }} />
        {/* WhatsApp SVG icon */}
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 32 32"
          width="26"
          height="26"
          fill="white"
          style={{ position: 'relative', zIndex: 1 }}
        >
          <path d="M16 0C7.164 0 0 7.163 0 16c0 2.82.736 5.463 2.023 7.754L0 32l8.489-2.023A15.938 15.938 0 0016 32c8.836 0 16-7.163 16-16S24.836 0 16 0zm0 29.333a13.28 13.28 0 01-6.773-1.854l-.486-.29-5.04 1.201 1.24-4.9-.318-.502A13.252 13.252 0 012.667 16C2.667 8.637 8.637 2.667 16 2.667S29.333 8.637 29.333 16 23.363 29.333 16 29.333zm7.27-9.87c-.398-.199-2.355-1.162-2.72-1.295-.366-.132-.632-.198-.898.199-.265.397-1.03 1.295-1.262 1.56-.232.265-.465.299-.863.1-.397-.2-1.677-.618-3.196-1.972-1.18-1.053-1.977-2.353-2.21-2.75-.232-.398-.025-.613.175-.81.179-.177.398-.465.597-.697.2-.232.266-.398.398-.664.133-.265.067-.497-.033-.696-.1-.199-.898-2.164-1.23-2.96-.324-.776-.653-.671-.898-.683l-.764-.013c-.265 0-.697.1-1.063.497-.365.398-1.396 1.363-1.396 3.328 0 1.965 1.43 3.863 1.63 4.13.198.265 2.814 4.298 6.82 6.028.954.412 1.698.658 2.278.842.957.305 1.829.262 2.517.159.767-.115 2.355-.963 2.687-1.893.332-.93.332-1.728.232-1.893-.099-.165-.365-.265-.763-.464z"/>
        </svg>

        <style>{`
          @keyframes wa-pulse {
            0% { transform: scale(1); opacity: 0.35; }
            70% { transform: scale(1.6); opacity: 0; }
            100% { transform: scale(1.6); opacity: 0; }
          }
        `}</style>
      </a>
    </div>
  );
}
