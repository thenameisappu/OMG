import { createContext, useContext, useEffect, useState, useRef, ReactNode } from 'react';
import { useToast } from '@/hooks/use-toast';
import { authService, profileService, tokenStorage } from '@/services/api';

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
  const [loginInProgress, setLoginInProgress] = useState(false);
  const loginInProgressRef = useRef(false);
  const { toast } = useToast();

  const SESSION_TIMEOUT_MS = 60 * 60 * 1000; // 1 Hour in milliseconds

  useEffect(() => {
    checkAuth();

    // Listener: Same account logged in on another device/browser
    const handleSingleSessionLogout = (e: any) => {
      // If login is currently in progress, DO NOT trigger forced logout or toast
      if (loginInProgressRef.current) return;

      setUser((prevUser) => {
        // Only show session expired toast if the user WAS currently authenticated
        if (prevUser !== null) {
          toast({
            title: 'Logged Out',
            description: e.detail?.message || 'Your account has been logged in on another device. Please log in again.',
            variant: 'destructive',
          });
        }
        return null;
      });
      tokenStorage.remove();
    };

    // Listener: Normal 1-hour session expiration
    const handleSessionExpired = (e: any) => {
      if (loginInProgressRef.current) return;

      setUser((prevUser) => {
        if (prevUser !== null) {
          toast({
            title: 'Session Expired',
            description: e.detail?.message || 'Your session has expired. Please log in again.',
            variant: 'destructive',
          });
        }
        return null;
      });
      tokenStorage.remove();
    };

    window.addEventListener('omg_single_session_logout', handleSingleSessionLogout);
    window.addEventListener('omg_session_expired', handleSessionExpired);
    return () => {
      window.removeEventListener('omg_single_session_logout', handleSingleSessionLogout);
      window.removeEventListener('omg_session_expired', handleSessionExpired);
    };
  }, []);

  // Periodic Single Active Session check when user is logged in
  useEffect(() => {
    if (!user || loginInProgress) return;

    // Check single session on tab focus
    const onFocus = () => {
      if (!loginInProgressRef.current) {
        checkAuth();
      }
    };

    window.addEventListener('focus', onFocus);

    // Heartbeat: verify session is still valid every 30 seconds.
    // Use a 2-second startup delay so the heartbeat never fires during the
    // brief window between loginInProgressRef being cleared and the toast
    // being shown (avoids race condition that could wipe the new token).
    let interval: ReturnType<typeof setInterval>;
    const startHeartbeat = () => {
      interval = setInterval(() => {
        if (!loginInProgressRef.current && user) {
          authService.getUser().catch(() => { });
        }
      }, 30000); // every 30 seconds
    };
    const startupDelay = setTimeout(startHeartbeat, 2000);

    return () => {
      window.removeEventListener('focus', onFocus);
      clearTimeout(startupDelay);
      clearInterval(interval);
    };
  }, [user, loginInProgress]);

  // 1-Hour Inactivity Auto-Logout Monitoring (frontend guard — backend enforces authoritatively)
  useEffect(() => {
    if (!user) return;

    // Use sessionStorage so each tab tracks its own user's activity independently.
    // This prevents User 2's activity in Tab 2 from resetting User 1's inactivity
    // timer in Tab 1.
    const updateActivity = () => {
      sessionStorage.setItem('omg_last_activity_time', Date.now().toString());
    };

    const checkInactivity = () => {
      const lastActivityStr = sessionStorage.getItem('omg_last_activity_time');
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

    // Initialize activity timestamp for this tab if missing
    if (!sessionStorage.getItem('omg_last_activity_time')) {
      updateActivity();
    }

    // Periodic check every 60 seconds
    const checkInterval = setInterval(checkInactivity, 60000);
    // Don't run checkInactivity() immediately on mount — the timestamp was
    // already reset to Date.now() at login time (see signIn), so it is always
    // fresh. Running it here immediately would risk a false positive if the
    // effect fires before signIn has a chance to reset the timestamp.

    return () => {
      events.forEach(evt => window.removeEventListener(evt, updateActivity));
      clearInterval(checkInterval);
    };
  }, [user]);

  const checkAuth = async (explicitToken?: string): Promise<User | null> => {
    try {
      setLoading(true);
      const token = explicitToken || tokenStorage.get();
      if (!token) {
        setUser(null);
        setLoading(false);
        return null;
      }

      const { data, error } = await authService.getUser();
      if (data && data.user && data.user.id) {
        // NOTE: getUser returns { user: {...} } with NO token field.
        // Do NOT overwrite the stored session token with data.user.id (UUID).
        // The token already in tokenStorage is the correct 64-char hex session token
        // validated by authenticate(). Only refresh it if the server explicitly
        // returns a new token (it currently does not).
        if (data.token && typeof data.token === 'string' && data.token.length > 10) {
          tokenStorage.set(data.token);
        }

        const fetchedUser: User = {
          id: String(data.user.id),
          email: data.user.email,
          name: data.user.name || '',
          phone: data.user.phone || '',
          address: data.user.address || '',
          city: data.user.city || '',
        };
        setUser(fetchedUser);
        return fetchedUser;
      } else {
        setUser(null);
        tokenStorage.remove();
        return null;
      }
    } catch (error) {
      setUser(null);
      tokenStorage.remove();
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
        tokenStorage.set(tokenToSave);
      }

      const authenticatedUser = await checkAuth(tokenToSave);

      if (!authenticatedUser || !authenticatedUser.id) {
        setUser(null);
        tokenStorage.remove();
        throw new Error('Verification succeeded but user session could not be established.');
      }

      toast({
        title: 'Email verified!',
        description: 'Welcome to OMG Luxury Gifting.',
      });
    } catch (error: any) {
      setUser(null);
      tokenStorage.remove();
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
    loginInProgressRef.current = true;
    setLoginInProgress(true);

    try {
      // 1. Clear old/stale authentication state
      tokenStorage.remove();

      // 2. Call login API
      const res = await authService.login(email, password);

      if (!res || !res.token) {
        throw new Error(res?.message || 'Invalid email or password.');
      }

      // 3. Save NEW token
      const newToken = res.token;
      tokenStorage.set(newToken);

      // 4. Update authenticated user state directly
      let authenticatedUser: User | null = null;
      if (res && res.user && res.user.id) {
        authenticatedUser = {
          id: String(res.user.id),
          email: res.user.email,
          name: res.user.name || '',
          phone: res.user.phone || '',
          address: res.user.address || '',
          city: res.user.city || '',
        };
        setUser(authenticatedUser);
      } else {
        authenticatedUser = await checkAuth(newToken);
      }

      if (!authenticatedUser || !authenticatedUser.id) {
        setUser(null);
        tokenStorage.remove();
        throw new Error('Authentication succeeded but user session could not be established. Please try logging in again.');
      }

      // 5. Mark login as completed successfully
      loginInProgressRef.current = false;
      setLoginInProgress(false);

      // 6. Reset the inactivity timer to NOW so a stale old timestamp from
      //    a previous session cannot immediately trigger signOut on this
      //    fresh session before the inactivity monitoring effect starts.
      sessionStorage.setItem('omg_last_activity_time', Date.now().toString());

      // 7. Show Welcome back toast ONLY after complete successful setup
      toast({
        title: 'Welcome back!',
        description: 'You have successfully signed in.',
      });
    } catch (error: any) {
      loginInProgressRef.current = false;
      setLoginInProgress(false);
      setUser(null);
      tokenStorage.remove();

      if (error.response?.status === 403 && error.response?.data?.requires_verification) {
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
