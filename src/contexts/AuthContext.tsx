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
  }, []);

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
          signOut();
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
    const interval = setInterval(checkInactivity, 60000);
    checkInactivity();

    return () => {
      events.forEach(evt => window.removeEventListener(evt, updateActivity));
      clearInterval(interval);
    };
  }, [user]);

  const checkAuth = async () => {
    try {
      const { data, error } = await authService.getUser();
      if (data && data.user) {
        // Fetch profile to get name
        try {
          const { data: profile } = await profileService.get();
          setUser({
            ...data.user,
            name: profile?.name,
            phone: profile?.phone,
            address: profile?.address,
            city: profile?.city,
          });
        } catch (e) {
          setUser(data.user);
        }
      } else {
        setUser(null);
      }
    } catch (error) {
      setUser(null);
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
      await authService.verifyOtp(email, otp);
      await checkAuth();
      toast({
        title: 'Email verified!',
        description: 'Welcome to OMG Luxury Gifting.',
      });
    } catch (error: any) {
      toast({
        title: 'Verification failed',
        description: error.response?.data?.message || 'Invalid or expired OTP',
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
      await authService.login(email, password);
      await checkAuth();

      toast({
        title: 'Welcome back!',
        description: 'You have successfully signed in.',
      });
    } catch (error: any) {
      if (error.response?.status === 403 && error.response?.data?.requires_verification) {
        // Pass the verification required status up
        throw error;
      }
      toast({
        title: 'Sign in failed',
        description: error.response?.data?.message || 'Invalid credentials',
        variant: 'destructive',
      });
      throw error;
    }
  };

  const signOut = async () => {
    try {
      authService.logout();
      setUser(null);

      toast({
        title: 'Signed out',
        description: 'You have been successfully signed out.',
      });
    } catch (error: any) {
      toast({
        title: 'Sign out failed',
        description: 'Something went wrong',
        variant: 'destructive',
      });
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
