import { productService, orderService, profileService, wishlistService } from '@/services/api';

// ============================================
// PRODUCT API FUNCTIONS
// ============================================

/**
 * Fetch all products with optional category filter
 */
export async function getProducts(category?: string) {
  try {
    const { data, error } = await productService.getAll(category);
    if (error) throw error;
    return data || [];
  } catch (error) {
    console.error('Error fetching products:', error);
    throw error;
  }
}

/**
 * Fetch a single product by slug
 */
export async function getProductBySlug(slug: string) {
  try {
    const { data, error } = await productService.getBySlug(slug);
    if (error) throw error;
    return data;
  } catch (error) {
    console.error('Error fetching product:', error);
    throw error;
  }
}

/**
 * Fetch featured/bestseller products
 */
export async function getFeaturedProducts() {
  try {
    const { data, error } = await productService.getFeatured();
    if (error) throw error;
    return data || [];
  } catch (error) {
    console.error('Error fetching featured products:', error);
    throw error;
  }
}

/**
 * Search products by name or description
 */
export async function searchProducts(searchTerm: string) {
  try {
    const { data, error } = await productService.search(searchTerm);
    if (error) throw error;
    return data || [];
  } catch (error) {
    console.error('Error searching products:', error);
    throw error;
  }
}

// ============================================
// ORDER API FUNCTIONS
// ============================================

/**
 * Create a new order with order items
 */
export async function createOrder(orderData: {
  total_amount: number;
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  delivery_address: string;
  delivery_option: string;
  delivery_date?: string;
  delivery_time: string;
  payment_method: string;
  items: Array<{
    product_id: string;
    quantity: number;
    unit_price: number;
  }>;
}) {
  try {
    const { data, error } = await orderService.create(orderData);
    if (error) throw error;
    return data;
  } catch (error) {
    console.error('Error creating order:', error);
    throw error;
  }
}

/**
 * Fetch user's orders
 */
export async function getUserOrders() {
  try {
    const { data, error } = await orderService.getAll();
    if (error) throw error;
    return data || [];
  } catch (error) {
    console.error('Error fetching orders:', error);
    throw error;
  }
}

/**
 * Fetch a single order by ID
 */
export async function getOrderById(orderId: string) {
  try {
    const { data, error } = await orderService.getById(orderId);
    if (error) throw error;
    return data;
  } catch (error) {
    console.error('Error fetching order:', error);
    throw error;
  }
}

/**
 * Cancel an order
 */
export async function cancelOrder(orderId: string) {
  try {
    const { data, error } = await orderService.cancel(orderId);
    if (error) throw error;
    return data;
  } catch (error) {
    console.error('Error cancelling order:', error);
    throw error;
  }
}

// ============================================
// USER PROFILE API FUNCTIONS
// ============================================

/**
 * Get user profile
 */
export async function getUserProfile() {
  try {
    const { data, error } = await profileService.get();
    if (error) throw error;
    return data;
  } catch (error) {
    console.error('Error fetching profile:', error);
    throw error;
  }
}

/**
 * Update user profile
 */
export async function updateUserProfile(profileData: {
  name?: string;
  phone?: string;
  address?: string;
  city?: string;
}) {
  try {
    const { data, error } = await profileService.update(profileData);
    if (error) throw error;
    return data;
  } catch (error) {
    console.error('Error updating profile:', error);
    throw error;
  }
}

// ============================================
// WISHLIST API FUNCTIONS
// ============================================

/**
 * Get user's wishlist
 */
export async function getWishlist() {
  try {
    const { data, error } = await wishlistService.getAll();
    if (error) throw error;
    return data || [];
  } catch (error) {
    console.error('Error fetching wishlist:', error);
    throw error;
  }
}

/**
 * Add product to wishlist
 */
export async function addToWishlist(productId: string) {
  try {
    const { error } = await wishlistService.add(productId);
    if (error) throw error;
    return true;
  } catch (error) {
    console.error('Error adding to wishlist:', error);
    throw error;
  }
}

/**
 * Remove product from wishlist
 */
export async function removeFromWishlist(productId: string) {
  try {
    const { error } = await wishlistService.remove(productId);
    if (error) throw error;
    return true;
  } catch (error) {
    console.error('Error removing from wishlist:', error);
    throw error;
  }
}

/**
 * Check if product is in wishlist
 */
export async function isInWishlist(productId: string): Promise<boolean> {
  try {
    return await wishlistService.check(productId);
  } catch (error) {
    console.error('Error checking wishlist:', error);
    return false;
  }
}
