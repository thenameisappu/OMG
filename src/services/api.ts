
import axios from 'axios';
import { API_BASE_URL } from '@/config';
import {
    reportNetworkErrorGlobal,
    clearNetworkErrorGlobal,
    checkActualConnectivity,
} from '@/contexts/NetworkContext';

export { API_BASE_URL };


export const tokenStorage = {
    set: (token: string) => {
        sessionStorage.setItem('auth_token', token);
        localStorage.setItem('auth_token', token);
    },
    get: (): string | null => {
        // sessionStorage takes priority — if the tab has its own token, use it.
        return sessionStorage.getItem('auth_token') || localStorage.getItem('auth_token');
    },
    remove: () => {
        sessionStorage.removeItem('auth_token');
        localStorage.removeItem('auth_token');
    },
};

export const api = axios.create({
    baseURL: API_BASE_URL,
    timeout: 10000, // 10s default timeout
    headers: {
        'Content-Type': 'application/json',
    },
    withCredentials: true, // IMPORTANT: Send cookies (session) with requests
});

// Request interceptor: Check offline state and inject Authorization header
api.interceptors.request.use(
    async (config) => {
        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            reportNetworkErrorGlobal('offline');
            return Promise.reject(new Error('No Internet Connection. Please check your internet connection and try again.'));
        }
        const token = tokenStorage.get();
        if (token) {
            config.headers = config.headers || {};
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

// Helper for delay in retry
const delay = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

// Response interceptor with timeout detection, retries, and network status reporting
api.interceptors.response.use(
    (response) => {
        // Successful response clears any previous network error
        clearNetworkErrorGlobal();
        return response;
    },
    async (error) => {
        const config = error.config;

        // HTTP response errors (4xx, 5xx): Do NOT treat as internet connectivity issues
        if (error.response) {
            if (error.response.status === 401) {
                // Check if original request carried an Authorization header
                const authHeader = config?.headers?.Authorization || config?.headers?.authorization;
                const hadAuthHeader = !!authHeader && String(authHeader).trim() !== '';

                if (hadAuthHeader) {
                    tokenStorage.remove();

                    if (typeof window !== 'undefined') {
                        const reason = error.response.data?.reason;
                        const isConcurrentLogin =
                            error.response.data?.single_session_logged_out === true ||
                            reason === 'concurrent_login';
                        const isExpired = reason === 'expired';

                        if (isConcurrentLogin) {
                            // Another device/browser logged in with the same account
                            window.dispatchEvent(new CustomEvent('omg_single_session_logout', {
                                detail: { message: error.response.data?.message || 'Your account has been logged in on another device. Please log in again.' }
                            }));
                        } else if (isExpired) {
                            // Normal 1-hour session expiration
                            window.dispatchEvent(new CustomEvent('omg_session_expired', {
                                detail: { message: error.response.data?.message || 'Your session has expired. Please log in again.' }
                            }));
                        }
                    }
                }
            }
            return Promise.reject(error);
        }

        // Handle network error / request timeout / aborted requests (no error.response)
        const isTimeout = error.code === 'ECONNABORTED' || (error.message && error.message.toLowerCase().includes('timeout'));

        // Retry logic for temporary network hiccup or single request timeout
        if (config && !config._retryAttempted) {
            config._retryAttempted = true;
            await delay(1000);
            try {
                const retryResponse = await api(config);
                clearNetworkErrorGlobal();
                return retryResponse;
            } catch (retryError: any) {
                // If retry also fails, proceed to classify error
                error = retryError;
            }
        }

        // Retry failed or already attempted: classify network state
        const retryFn = config ? () => api(config) : undefined;
        const online = await checkActualConnectivity();

        if (!online) {
            reportNetworkErrorGlobal('offline', retryFn);
        } else if (isTimeout || error.code === 'ERR_NETWORK') {
            reportNetworkErrorGlobal('slow', retryFn);
        }

        return Promise.reject(error);
    }
);

// Auth Services
export const authService = {
    login: async (email: string, password: string): Promise<any> => {
        const response = await api.post('/auth.php?action=login', { email, password });
        if (response.data.token) {
            tokenStorage.set(response.data.token);
        }
        return response.data;
    },
    register: async (email: string, password: string, name?: string): Promise<any> => {
        const response = await api.post('/auth.php?action=register', { email, password, name });
        return response.data;
    },
    logout: (): void => {
        api.get('/auth.php?action=logout').catch(() => { });
        tokenStorage.remove();
    },
    getUser: async (): Promise<{ data: { user: any }; error: any }> => {
        try {
            const response = await api.get('/auth.php?action=get_user');
            return { data: { user: response.data.user }, error: null };
        } catch (error) {
            return { data: { user: null }, error: error };
        }
    },
    verifyOtp: async (email: string, otp: string): Promise<any> => {
        const response = await api.post('/auth.php?action=verify_otp', { email, otp });
        if (response.data.token) {
            tokenStorage.set(response.data.token);
        }
        return response.data;
    },
    resendOtp: async (email: string): Promise<any> => {
        const response = await api.post('/auth.php?action=resend_otp', { email });
        return response.data;
    },
    forgotPassword: async (email: string): Promise<any> => {
        const response = await api.post('/auth.php?action=forgot_password', { email });
        return response.data;
    },
    verifyResetOtp: async (email: string, otp: string): Promise<any> => {
        const response = await api.post('/auth.php?action=verify_reset_otp', { email, otp });
        return response.data;
    },
    resetPassword: async (email: string, otp: string, password: string): Promise<any> => {
        const response = await api.post('/auth.php?action=reset_password', { email, otp, password });
        return response.data;
    },
};

// Product Services
export const productService = {
    getAll: async (category: string = 'all'): Promise<{ data: any; error: any }> => {
        const response = await api.get(`/products.php?action=get_products&category=${category}`);
        return { data: response.data, error: null };
    },
    getBySlug: async (slug: string): Promise<{ data: any; error: any }> => {
        const response = await api.get(`/products.php?action=get_product&slug=${slug}`);
        return { data: response.data, error: null };
    },
    getFeatured: async (): Promise<{ data: any; error: any }> => {
        const response = await api.get('/products.php?action=get_featured');
        return { data: response.data, error: null };
    },
    search: async (term: string): Promise<{ data: any; error: any }> => {
        const response = await api.get(`/products.php?action=search&search=${term}`);
        return { data: response.data, error: null };
    },
};

// Order Services
export const orderService = {
    create: async (orderData: any): Promise<{ data: any; error: any }> => {
        const response = await api.post('/orders.php?action=create_order', orderData);
        return { data: response.data, error: null };
    },
    getAll: async (): Promise<{ data: any; error: any }> => {
        const response = await api.get('/orders.php?action=get_orders');
        return { data: response.data, error: null };
    },
    getById: async (id: string): Promise<{ data: any; error: any }> => {
        const response = await api.get(`/orders.php?action=get_order&id=${id}`);
        return { data: response.data, error: null };
    },
    cancel: async (id: string): Promise<{ data: any; error: any }> => {
        const response = await api.post(`/orders.php?action=cancel_order&id=${id}`);
        return { data: response.data, error: null };
    },
};

// Profile Services
export const profileService = {
    get: async (): Promise<{ data: any; error: any }> => {
        const response = await api.get('/profile.php?action=get_profile');
        return { data: response.data, error: null };
    },
    update: async (profileData: any): Promise<{ data: any; error: any }> => {
        const response = await api.post('/profile.php?action=update_profile', profileData);
        return { data: response.data, error: null };
    },
};

// Wishlist Services
export const wishlistService = {
    getAll: async (): Promise<{ data: any; error: any }> => {
        const response = await api.get('/wishlist.php?action=get_wishlist');
        return { data: response.data, error: null };
    },
    add: async (productId: string): Promise<{ data: any; error: any }> => {
        const response = await api.post('/wishlist.php?action=add', { product_id: productId });
        return { data: response.data, error: null };
    },
    remove: async (productId: string): Promise<{ data: any; error: any }> => {
        const response = await api.get(`/wishlist.php?action=remove&product_id=${productId}`);
        return { data: response.data, error: null };
    },
    check: async (productId: string): Promise<boolean> => {
        try {
            const response = await api.get(`/wishlist.php?action=check&product_id=${productId}`);
            return response.data; // Returns boolean
        } catch (e) {
            return false;
        }
    }
};

// Newsletter Services
export const newsletterService = {
    subscribe: async (email: string): Promise<any> => {
        const response = await api.post('/newsletter.php', { email });
        return response.data;
    }
};

// Inquiry & Customisation Services
export const inquiryService = {
    submit: async (inquiryData: any): Promise<any> => {
        const response = await api.post('/inquiries.php', inquiryData);
        return response.data;
    }
};

export const customisationService = {
    submit: async (customisationData: any): Promise<any> => {
        const response = await api.post('/customisations.php', customisationData);
        return response.data;
    }
};

// Dynamic Surprise Builder & Pincode Validation Services
export const surpriseService = {
    getData: async (): Promise<{ success: boolean; experiences: any[]; upgrades: any[]; pincodes: string[] }> => {
        const response = await api.get('/surprises.php?action=get_data');
        return response.data;
    },
    checkPincode: async (pincode: string): Promise<{ valid: boolean; message: string; area_name?: string }> => {
        const response = await api.get(`/surprises.php?action=check_pincode&pincode=${encodeURIComponent(pincode)}`);
        return response.data;
    }
};

