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
  isAuthenticated: boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const { toast } = useToast();

  useEffect(() => {
    checkAuth();
  }, []);

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
      const response = await authService.register(email, password);

      if (response.requires_verification) {
        toast({
          title: 'Account created!',
          description: 'Please verify your email with the OTP sent to you.',
        });
        return; // Redirect handled by component
      }

      // Fallback for unexpected response structure
      await authService.login(email, password);
      await profileService.update({ name });
      await checkAuth();

      toast({
        title: 'Account created!',
        description: 'Welcome to OMG Luxury Gifting.',
      });
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
