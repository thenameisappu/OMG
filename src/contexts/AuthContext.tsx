import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { useToast } from '@/hooks/use-toast';
import { authService, profileService } from '@/services/api';

interface User {
  id: string;
  email: string;
  name?: string;
  phone?: string;
  address?: string;
  city?: string;
}

interface AuthContextType {
  user: User | null;
  loading: boolean;
  signUp: (email: string, password: string, name: string) => Promise<void>;
  signIn: (email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
  verifyOtp: (email: string, otp: string) => Promise<void>;
  resendOtp: (email: string) => Promise<void>;
  refreshUser: () => Promise<void>;
  isAuthenticated: boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const { toast } = useToast();

  const SESSION_TIMEOUT_MS = 24 * 60 * 60 * 1000; // 24 Hours in milliseconds

  useEffect(() => {
    checkAuth();

    const handleSingleSessionLogout = (e: any) => {
      setUser((prevUser) => {
        // Only show session expired toast if the user WAS currently authenticated
        if (prevUser !== null) {
          toast({
            title: 'Session Expired',
            description: e.detail?.message || 'Your account has been logged in on another device. Please log in again.',
            variant: 'destructive',
          });
        }
        return null;
      });
      localStorage.removeItem('auth_token');
    };

    window.addEventListener('omg_single_session_logout', handleSingleSessionLogout);
    return () => {
      window.removeEventListener('omg_single_session_logout', handleSingleSessionLogout);
    };
  }, []);

  // Periodic Single Active Session check when user is logged in
  useEffect(() => {
    if (!user) return;

    // Check single session on tab focus
    const onFocus = () => {
      checkAuth();
    };

    window.addEventListener('focus', onFocus);

    // Heartbeat check every 15 seconds to detect logins from other devices in near-realtime
    const interval = setInterval(() => {
      authService.getUser().catch(() => {});
    }, 15000);

    return () => {
      window.removeEventListener('focus', onFocus);
      clearInterval(interval);
    };
  }, [user]);

  // 24-Hour Inactivity Auto-Logout Monitoring
  useEffect(() => {
    if (!user) return;

    const updateActivity = () => {
      localStorage.setItem('omg_last_activity_time', Date.now().toString());
    };

    const checkInactivity = () => {
      const lastActivityStr = localStorage.getItem('omg_last_activity_time');
      if (lastActivityStr && user) {
        const lastActivity = parseInt(lastActivityStr, 10);
        if (Date.now() - lastActivity > SESSION_TIMEOUT_MS) {
          signOut(true);
        }
      }
    };

    // Track user interaction events
    const events = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];
    events.forEach(evt => window.addEventListener(evt, updateActivity));

    // Initialize activity timestamp if missing
    if (!localStorage.getItem('omg_last_activity_time')) {
      updateActivity();
    }

    // Periodic check every 60 seconds
    const checkInterval = setInterval(checkInactivity, 60000);
    checkInactivity();

    return () => {
      events.forEach(evt => window.removeEventListener(evt, updateActivity));
      clearInterval(checkInterval);
    };
  }, [user]);

  const checkAuth = async (): Promise<User | null> => {
    try {
      setLoading(true);
      const { data, error } = await authService.getUser();
      if (data && data.user && data.user.id) {
        const tokenToSave = data.token || data.user.id;
        if (tokenToSave) {
          localStorage.setItem('auth_token', tokenToSave);
        }

        const fetchedUser: User = {
          id: data.user.id,
          email: data.user.email,
          name: data.user.name,
          phone: data.user.phone,
          address: data.user.address,
          city: data.user.city,
        };
        setUser(fetchedUser);
        return fetchedUser;
      } else {
        setUser(null);
        localStorage.removeItem('auth_token');
        return null;
      }
    } catch (error) {
      setUser(null);
      localStorage.removeItem('auth_token');
      return null;
    } finally {
      setLoading(false);
    }
  };

  const signUp = async (email: string, password: string, name: string) => {
    try {
      const res = await authService.register(email, password, name);

      toast({
        title: 'Verification Code Sent.',
        description: 'A 6-digit OTP code has been sent to your email address.',
      });
      return res;
    } catch (error: any) {
      toast({
        title: 'Sign up failed',
        description: error.response?.data?.message || 'Something went wrong',
        variant: 'destructive',
      });
      throw error;
    }
  };

  const verifyOtp = async (email: string, otp: string) => {
    try {
      const res = await authService.verifyOtp(email, otp);

      const tokenToSave = res?.token || res?.user?.id;
      if (tokenToSave) {
        localStorage.setItem('auth_token', tokenToSave);
      }

      const authenticatedUser = await checkAuth();

      if (!authenticatedUser || !authenticatedUser.id) {
        setUser(null);
        localStorage.removeItem('auth_token');
        throw new Error('Verification succeeded but user session could not be established.');
      }

      toast({
        title: 'Email verified!',
        description: 'Welcome to OMG Luxury Gifting.',
      });
    } catch (error: any) {
      setUser(null);
      localStorage.removeItem('auth_token');
      toast({
        title: 'Verification failed',
        description: error.response?.data?.message || error.message || 'Invalid or expired OTP',
        variant: 'destructive',
      });
      throw error;
    }
  };

  const resendOtp = async (email: string) => {
    try {
      await authService.resendOtp(email);
      toast({
        title: 'OTP Resent',
        description: 'A new verification code has been sent to your email.',
      });
    } catch (error: any) {
      toast({
        title: 'Failed to resend OTP',
        description: error.response?.data?.message || 'Something went wrong',
        variant: 'destructive',
      });
      throw error;
    }
  };

  const signIn = async (email: string, password: string) => {
    try {
      // Clear any stale token before attempting new login
      localStorage.removeItem('auth_token');

      const res = await authService.login(email, password);

      const tokenToSave = res?.token || res?.user?.id;
      if (tokenToSave) {
        localStorage.setItem('auth_token', tokenToSave);
      }

      let authenticatedUser: User | null = null;
      if (res && res.user && res.user.id) {
        authenticatedUser = {
          id: res.user.id,
          email: res.user.email,
          name: res.user.name,
          phone: res.user.phone,
          address: res.user.address,
          city: res.user.city,
        };
        setUser(authenticatedUser);
      } else {
        authenticatedUser = await checkAuth();
      }

      if (!authenticatedUser || !authenticatedUser.id) {
        setUser(null);
        localStorage.removeItem('auth_token');
        throw new Error('Authentication succeeded but user session could not be established. Please try logging in again.');
      }

      toast({
        title: 'Welcome back!',
        description: 'You have successfully signed in.',
      });
    } catch (error: any) {
      setUser(null);
      localStorage.removeItem('auth_token');
      if (error.response?.status === 403 && error.response?.data?.requires_verification) {
        // Pass the verification required status up
        throw error;
      }
      const errorMsg = error.response?.data?.message || error.message || 'Invalid email or password.';
      toast({
        title: 'Sign in failed',
        description: errorMsg,
        variant: 'destructive',
      });
      throw error;
    }
  };

  const signOut = async (silent = false) => {
    try {
      authService.logout();
      setUser(null);

      if (!silent) {
        toast({
          title: 'Signed out',
          description: 'You have been successfully signed out.',
        });
      }
    } catch (error: any) {
      if (!silent) {
        toast({
          title: 'Sign out failed',
          description: 'Something went wrong',
          variant: 'destructive',
        });
      }
    }
  };

  const value = {
    user,
    loading,
    signUp,
    signIn,
    signOut,
    verifyOtp,
    resendOtp,
    refreshUser: checkAuth,
    isAuthenticated: !!user,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
