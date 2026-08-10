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
