
import axios from 'axios';

// Configuration
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || (import.meta.env.DEV ? 'https://saddlebrown-badger-327057.hostingersite.com/backend' : '/backend');

const api = axios.create({
    baseURL: API_BASE_URL,
    headers: {
        'Content-Type': 'application/json',
    },
    withCredentials: true, // IMPORTANT: Send cookies (session) with requests
});

// Response interceptor for unified error handling (optional but good practice)
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            // Check if we are not already on the login page to avoid loops
            if (!window.location.pathname.includes('/login')) {
                // Dispatch a custom event or use a callback to handle redirects if needed
                // For now, we just pass the error through
            }
        }
        return Promise.reject(error);
    }
);

// Auth Services
export const authService = {
    login: async (email: string, password: string): Promise<any> => {
        const response = await api.post('/auth.php?action=login', { email, password });
        if (response.data.token) {
            localStorage.setItem('auth_token', response.data.token);
        }
        return response.data;
    },
    register: async (email: string, password: string): Promise<any> => {
        const response = await api.post('/auth.php?action=register', { email, password });
        return response.data;
    },
    logout: (): void => {
        localStorage.removeItem('auth_token');
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
        const response = await api.get(`/orders.php?action=cancel_order&id=${id}`);
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
