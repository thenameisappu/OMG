import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/contexts/AuthContext';
import { useToast } from '@/hooks/use-toast';
import { getWishlist, addToWishlist as apiAddToWishlist, removeFromWishlist as apiRemoveFromWishlist } from '@/db/api';

interface WishlistContextType {
  wishlistIds: string[];
  wishlistItems: any[];
  isInWishlist: (productId: string) => boolean;
  toggleWishlist: (product: any) => Promise<boolean>;
  wishlistCount: number;
  loading: boolean;
  refreshWishlist: () => Promise<void>;
}

const WishlistContext = createContext<WishlistContextType | undefined>(undefined);

export function WishlistProvider({ children }: { children: React.ReactNode }) {
  const [wishlistItems, setWishlistItems] = useState<any[]>([]);
  const [wishlistIds, setWishlistIds] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);
  const { isAuthenticated, loading: authLoading } = useAuth();
  const { toast } = useToast();

  const refreshWishlist = useCallback(async () => {
    if (!isAuthenticated) {
      // Fallback to local storage for guests
      const saved = localStorage.getItem('omg_wishlist');
      if (saved) {
        try {
          const parsed = JSON.parse(saved);
          setWishlistIds(parsed.map((item: any) => String(item.product_id || item.id)));
          setWishlistItems(parsed);
        } catch (e) {
          setWishlistIds([]);
          setWishlistItems([]);
        }
      } else {
        setWishlistIds([]);
        setWishlistItems([]);
      }
      return;
    }

    try {
      setLoading(true);
      const data = await getWishlist();
      if (Array.isArray(data)) {
        setWishlistItems(data);
        setWishlistIds(data.map((item: any) => String(item.product_id || item.id)));
      }
    } catch (e) {
      console.error('Failed to fetch wishlist from backend:', e);
    } finally {
      setLoading(false);
    }
  }, [isAuthenticated]);

  useEffect(() => {
    if (!authLoading) {
      refreshWishlist();
    }
  }, [authLoading, refreshWishlist]);

  const isInWishlist = (productId: string | number) => {
    if (!productId) return false;
    return wishlistIds.some(id => String(id) === String(productId));
  };

  const toggleWishlist = async (product: any): Promise<boolean> => {
    if (!product || (!product.id && !product.product_id)) return false;
    const productId = String(product.id || product.product_id);
    const exists = isInWishlist(productId);

    if (exists) {
      // Remove from wishlist
      setWishlistIds(prev => prev.filter(id => String(id) !== productId));
      setWishlistItems(prev => prev.filter(item => String(item.product_id || item.id) !== productId));

      if (isAuthenticated) {
        try {
          await apiRemoveFromWishlist(productId);
        } catch (e) {
          console.error('Failed to remove from backend wishlist', e);
        }
      } else {
        const updated = wishlistItems.filter(item => String(item.product_id || item.id) !== productId);
        localStorage.setItem('omg_wishlist', JSON.stringify(updated));
      }

      toast({
        title: "Removed from Wishlist",
        description: `${product.name} removed from your wishlist.`,
      });
      return false;
    } else {
      // Add to wishlist
      setWishlistIds(prev => [...prev.filter(id => String(id) !== productId), productId]);
      const newItem = {
        id: `w-${productId}`,
        product_id: productId,
        products: {
          name: product.name,
          price: product.price,
          image_url: product.image || product.image_url,
          slug: product.slug
        }
      };
      setWishlistItems(prev => [...prev.filter(item => String(item.product_id || item.id) !== productId), newItem]);

      if (isAuthenticated) {
        try {
          await apiAddToWishlist(productId);
        } catch (e) {
          console.error('Failed to add to backend wishlist', e);
        }
      } else {
        const updated = [...wishlistItems.filter(item => String(item.product_id || item.id) !== productId), newItem];
        localStorage.setItem('omg_wishlist', JSON.stringify(updated));
      }

      toast({
        title: "Saved to Wishlist ✨",
        description: `${product.name} added to your wishlist.`,
      });
      return true;
    }
  };

  return (
    <WishlistContext.Provider
      value={{
        wishlistIds,
        wishlistItems,
        isInWishlist,
        toggleWishlist,
        wishlistCount: wishlistIds.length,
        loading,
        refreshWishlist,
      }}
    >
      {children}
    </WishlistContext.Provider>
  );
}

export function useWishlist() {
  const context = useContext(WishlistContext);
  if (context === undefined) {
    throw new Error('useWishlist must be used within a WishlistProvider');
  }
  return context;
}
