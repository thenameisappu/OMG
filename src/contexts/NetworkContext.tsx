import React, { createContext, useContext, useState, useEffect, useCallback, useRef } from 'react';
import { WifiOff, AlertTriangle, RefreshCw, CheckCircle2 } from 'lucide-react';
import { API_BASE_URL } from '@/config';

export type NetworkState = 'online' | 'offline' | 'slow';

interface NetworkContextType {
  networkState: NetworkState;
  isOnline: boolean;
  checkConnectivity: () => Promise<boolean>;
  reportNetworkError: (type: 'offline' | 'slow', retryFn?: () => void) => void;
  clearNetworkError: () => void;
  retryLastOperation: () => void;
}

const NetworkContext = createContext<NetworkContextType | undefined>(undefined);

type NetworkListener = (state: NetworkState) => void;
const listeners = new Set<NetworkListener>();
let globalNetworkState: NetworkState = 'online';
let globalRetryFn: (() => void) | null = null;

export const reportNetworkErrorGlobal = (type: 'offline' | 'slow', retryFn?: () => void) => {
  if (retryFn) {
    globalRetryFn = retryFn;
  }
  if (globalNetworkState !== type) {
    globalNetworkState = type;
    listeners.forEach((listener) => listener(globalNetworkState));
  }
};

export const clearNetworkErrorGlobal = () => {
  if (globalNetworkState !== 'online') {
    globalNetworkState = 'online';
    globalRetryFn = null;
    listeners.forEach((listener) => listener(globalNetworkState));
  }
};

export const getGlobalNetworkState = (): NetworkState => globalNetworkState;

export const checkActualConnectivity = async (): Promise<boolean> => {
  if (typeof navigator !== 'undefined' && !navigator.onLine) {
    return false;
  }

  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 4000);
    
    // Ping API base URL or static endpoint to verify internet connectivity
    const pingUrl = `${API_BASE_URL}/auth.php?action=get_user&_t=${Date.now()}`;
    const response = await fetch(pingUrl, {
      method: 'HEAD',
      mode: 'cors',
      cache: 'no-store',
      signal: controller.signal,
    }).catch(() => null);

    clearTimeout(timeoutId);

    if (response) {
      return true;
    }

    // Fallback ping to origin root
    const fallbackController = new AbortController();
    const fallbackTimeout = setTimeout(() => fallbackController.abort(), 3000);
    const fallbackResponse = await fetch(`/?_t=${Date.now()}`, {
      method: 'HEAD',
      cache: 'no-store',
      signal: fallbackController.signal,
    }).catch(() => null);

    clearTimeout(fallbackTimeout);
    return !!fallbackResponse;
  } catch {
    return false;
  }
};

export const NetworkProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [networkState, setNetworkState] = useState<NetworkState>(globalNetworkState);
  const [isRetrying, setIsRetrying] = useState(false);
  const [recoveredMessage, setRecoveredMessage] = useState(false);
  const isInitialMount = useRef(true);

  const reportNetworkError = useCallback((type: 'offline' | 'slow', retryFn?: () => void) => {
    reportNetworkErrorGlobal(type, retryFn);
  }, []);

  const clearNetworkError = useCallback(() => {
    clearNetworkErrorGlobal();
  }, []);

  const checkConnectivity = useCallback(async (): Promise<boolean> => {
    const online = await checkActualConnectivity();
    if (online) {
      if (globalNetworkState !== 'online') {
        clearNetworkErrorGlobal();
        setRecoveredMessage(true);
        setTimeout(() => setRecoveredMessage(false), 3000);
      }
    } else {
      reportNetworkErrorGlobal('offline');
    }
    return online;
  }, []);

  const retryLastOperation = useCallback(async () => {
    setIsRetrying(true);
    const online = await checkConnectivity();
    if (online) {
      if (globalRetryFn) {
        const fn = globalRetryFn;
        globalRetryFn = null;
        try {
          await fn();
        } catch (e) {
          console.error('Retry failed:', e);
        }
      }
    }
    setIsRetrying(false);
  }, [checkConnectivity]);

  useEffect(() => {
    const handleStateChange = (newState: NetworkState) => {
      setNetworkState(newState);
    };

    listeners.add(handleStateChange);

    const handleOnlineEvent = async () => {
      // Browser says online - verify actual connectivity
      const actualOnline = await checkConnectivity();
      if (actualOnline && globalRetryFn) {
        retryLastOperation();
      }
    };

    const handleOfflineEvent = () => {
      reportNetworkErrorGlobal('offline');
    };

    window.addEventListener('online', handleOnlineEvent);
    window.addEventListener('offline', handleOfflineEvent);

    // Initial check on mount
    if (isInitialMount.current) {
      isInitialMount.current = false;
      if (!navigator.onLine) {
        reportNetworkErrorGlobal('offline');
      }
    }

    // Periodic check if offline or slow to auto-recover when back online
    const interval = setInterval(() => {
      if (globalNetworkState !== 'online') {
        checkConnectivity();
      }
    }, 8000);

    return () => {
      listeners.delete(handleStateChange);
      window.removeEventListener('online', handleOnlineEvent);
      window.removeEventListener('offline', handleOfflineEvent);
      clearInterval(interval);
    };
  }, [checkConnectivity, retryLastOperation]);

  return (
    <NetworkContext.Provider
      value={{
        networkState,
        isOnline: networkState === 'online',
        checkConnectivity,
        reportNetworkError,
        clearNetworkError,
        retryLastOperation,
      }}
    >
      {children}
      <NetworkStatusBanner
        networkState={networkState}
        isRetrying={isRetrying}
        recoveredMessage={recoveredMessage}
        onRetry={retryLastOperation}
      />
    </NetworkContext.Provider>
  );
};

export const useNetworkStatus = () => {
  const context = useContext(NetworkContext);
  if (!context) {
    throw new Error('useNetworkStatus must be used within a NetworkProvider');
  }
  return context;
};

// Sleek, non-blocking notification banner component
interface NetworkStatusBannerProps {
  networkState: NetworkState;
  isRetrying: boolean;
  recoveredMessage: boolean;
  onRetry: () => void;
}

const NetworkStatusBanner: React.FC<NetworkStatusBannerProps> = ({
  networkState,
  isRetrying,
  recoveredMessage,
  onRetry,
}) => {
  if (networkState === 'online' && !recoveredMessage) {
    return null;
  }

  if (recoveredMessage) {
    return (
      <div
        className="fixed top-4 left-1/2 -translate-x-1/2 z-[99999] flex items-center gap-3 px-4 py-3 rounded-full bg-emerald-900/90 text-white shadow-2xl backdrop-blur-md border border-emerald-500/30 transition-all duration-300 animate-in fade-in slide-in-from-top-4"
        role="status"
        aria-live="polite"
      >
        <CheckCircle2 className="w-5 h-5 text-emerald-400 shrink-0" />
        <span className="text-sm font-medium">Internet Connection Restored</span>
      </div>
    );
  }

  const isOffline = networkState === 'offline';

  return (
    <div
      className="fixed top-4 left-1/2 -translate-x-1/2 z-[99999] w-[92%] max-w-md transition-all duration-300 animate-in fade-in slide-in-from-top-4 pointer-events-auto"
      role="alert"
      aria-live="assertive"
    >
      <div
        className={`flex items-start gap-3.5 p-4 rounded-2xl shadow-2xl backdrop-blur-xl border ${
          isOffline
            ? 'bg-rose-950/90 border-rose-500/30 text-rose-50 shadow-rose-950/40'
            : 'bg-amber-950/90 border-amber-500/30 text-amber-50 shadow-amber-950/40'
        }`}
      >
        <div
          className={`p-2 rounded-xl shrink-0 ${
            isOffline ? 'bg-rose-500/20 text-rose-300' : 'bg-amber-500/20 text-amber-300'
          }`}
        >
          {isOffline ? <WifiOff className="w-5 h-5" /> : <AlertTriangle className="w-5 h-5" />}
        </div>

        <div className="flex-1 min-w-0 pt-0.5">
          <h4 className="text-sm font-semibold leading-snug">
            {isOffline ? 'No Internet Connection' : 'Slow Internet Connection'}
          </h4>
          <p className="text-xs text-slate-200/90 mt-1 leading-relaxed">
            {isOffline
              ? 'Please check your internet connection and try again.'
              : 'Your internet connection is too slow. Please check your connection and try again.'}
          </p>
        </div>

        <button
          onClick={onRetry}
          disabled={isRetrying}
          className={`px-3 py-1.5 rounded-lg text-xs font-semibold shrink-0 transition-all flex items-center gap-1.5 self-center ${
            isOffline
              ? 'bg-rose-500 hover:bg-rose-600 active:scale-95 text-white shadow-sm'
              : 'bg-amber-500 hover:bg-amber-600 active:scale-95 text-slate-950 font-bold shadow-sm'
          } disabled:opacity-50 disabled:cursor-not-allowed`}
          aria-label="Retry connection"
        >
          <RefreshCw className={`w-3.5 h-3.5 ${isRetrying ? 'animate-spin' : ''}`} />
          {isRetrying ? 'Retrying...' : 'Retry'}
        </button>
      </div>
    </div>
  );
};
