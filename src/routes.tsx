import Home from './pages/Home';
import Products from './pages/Products';
import ProductDetail from './pages/ProductDetail';
import Cart from './pages/Cart';
import Checkout from './pages/Checkout';
import SurpriseServices from './pages/SurpriseServices';
import Wishlist from './pages/Wishlist';
import Login from './pages/Login';
import VerifyOtp from './pages/VerifyOtp';
import ForgotPassword from './pages/ForgotPassword';
import DeliveryInfo from './pages/DeliveryInfo';
import FAQ from './pages/FAQ';

import NotFound from './pages/NotFound';
import Orders from './pages/Orders';
import Profile from './pages/Profile';
import type { ReactNode } from 'react';

interface RouteConfig {
  name: string;
  path: string;
  element: ReactNode;
}

const routes: RouteConfig[] = [
  {
    name: 'Home',
    path: '/',
    element: <Home />
  },
  {
    name: 'Products',
    path: '/products',
    element: <Products />
  },
  {
    name: 'Product Detail',
    path: '/product/:slug',
    element: <ProductDetail />
  },
  {
    name: 'Cart',
    path: '/cart',
    element: <Cart />
  },
  {
    name: 'Checkout',
    path: '/checkout',
    element: <Checkout />
  },
  {
    name: 'Surprise Services',
    path: '/surprise-services',
    element: <SurpriseServices />
  },
  {
    name: 'Wishlist',
    path: '/wishlist',
    element: <Wishlist />
  },
  {
    name: 'Login',
    path: '/login',
    element: <Login />
  },
  {
    name: 'Sign Up',
    path: '/signup',
    element: <Login />
  },
  {
    name: 'Verify Email',
    path: '/verify-otp',
    element: <VerifyOtp />
  },
  {
    name: 'Forgot Password',
    path: '/forgot-password',
    element: <ForgotPassword />
  },
  {
    name: 'Delivery Info',
    path: '/delivery-info',
    element: <DeliveryInfo />
  },
  {
    name: 'FAQ',
    path: '/faq',
    element: <FAQ />
  },
  {
    name: 'My Orders',
    path: '/orders',
    element: <Orders />
  },
  {
    name: 'My Profile',
    path: '/profile',
    element: <Profile />
  },
  {
    name: 'Not Found',
    path: '*',
    element: <NotFound />
  }
];

export default routes;
