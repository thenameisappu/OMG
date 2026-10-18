import React from 'react';
import { HashRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import IntersectObserver from '@/components/common/IntersectObserver';
import Layout from '@/components/layout/Layout';
import routes from './routes';
import { Toaster } from '@/components/ui/toaster';
import { CartProvider } from '@/contexts/CartContext';
import { AuthProvider } from '@/contexts/AuthContext';
import { WishlistProvider } from '@/contexts/WishlistContext';
import { NetworkProvider } from '@/contexts/NetworkContext';

import ScrollToTop from '@/components/common/ScrollToTop';

const App: React.FC = () => {
  React.useEffect(() => {
    // If returning from Cashfree gateway redirect with query params outside hash
    if (typeof window !== 'undefined' && window.location.search) {
      const searchParams = new URLSearchParams(window.location.search);
      const orderId = searchParams.get('order_id') || searchParams.get('cf_id');
      if (orderId && !window.location.hash.includes('/checkout')) {
        window.location.hash = `#/checkout?order_id=${encodeURIComponent(orderId)}`;
      }
    }
  }, []);

  return (
    <NetworkProvider>
      <Router>
        <ScrollToTop />
        <AuthProvider>
          <WishlistProvider>
            <CartProvider>
              <IntersectObserver />
              <Routes>
                <Route element={<Layout />}>
                  {routes.map((route, index) => (
                    <Route
                      key={index}
                      path={route.path}
                      element={route.element}
                    />
                  ))}
                  <Route path="*" element={<Navigate to="/" replace />} />
                </Route>
              </Routes>
              <Toaster />
            </CartProvider>
          </WishlistProvider>
        </AuthProvider>
      </Router>
    </NetworkProvider>
  );
};

export default App;
