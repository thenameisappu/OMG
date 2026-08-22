import React, { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { Heart, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useWishlist } from '@/contexts/WishlistContext';
import { useAuth } from '@/contexts/AuthContext';
import { useToast } from '@/hooks/use-toast';

interface WishlistButtonProps {
  product: {
    id: string | number;
    name?: string;
    price?: number;
    image?: string;
    image_url?: string;
    slug?: string;
    [key: string]: any;
  };
  size?: 'sm' | 'md' | 'lg';
  className?: string;
  onToggleSuccess?: (isWishlisted: boolean) => void;
}

export const WishlistButton: React.FC<WishlistButtonProps> = ({
  product,
  size = 'md',
  className,
  onToggleSuccess,
}) => {
  const { isInWishlist, toggleWishlist } = useWishlist();
  const { isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { toast } = useToast();
  const [loading, setLoading] = useState(false);

  if (!product || (!product.id && product.id !== 0)) {
    return null;
  }

  const isWishlisted = isInWishlist(String(product.id));

  const handleClick = async (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();

    if (!isAuthenticated) {
      toast({
        title: "Login Required",
        description: "Please login to save items to your wishlist.",
        variant: "destructive",
      });
      navigate('/login', { state: { from: location } });
      return;
    }

    setLoading(true);
    try {
      const result = await toggleWishlist(product);
      if (onToggleSuccess) {
        onToggleSuccess(result);
      }
    } catch (error: any) {
      console.error('Wishlist toggle error:', error);
    } finally {
      setLoading(false);
    }
  };

  // Sizing maps matching the Product Card reference design
  const sizeStyles = {
    sm: {
      button: 'h-8 w-8',
      icon: 'h-4 w-4',
    },
    md: {
      button: 'h-9 w-9',
      icon: 'h-4.5 w-4.5',
    },
    lg: {
      button: 'h-10 w-10 border border-white/70',
      icon: 'h-5 w-5',
    },
  };

  const currentSize = sizeStyles[size] || sizeStyles.md;

  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={loading}
      className={cn(
        "rounded-full backdrop-blur-md transition-all duration-300 shadow-md flex items-center justify-center shrink-0 cursor-pointer select-none",
        currentSize.button,
        isWishlisted
          ? "bg-secondary text-primary hover:bg-secondary/90 hover:scale-110 shadow-sm"
          : "bg-white/85 text-foreground hover:bg-white hover:scale-110",
        loading && "opacity-80 pointer-events-none",
        className
      )}
      aria-label={isWishlisted ? "Remove from wishlist" : "Add to wishlist"}
    >
      {loading ? (
        <Loader2 className={cn("animate-spin text-current", currentSize.icon)} />
      ) : (
        <Heart
          className={cn(
            currentSize.icon,
            "transition-transform",
            isWishlisted && "fill-current text-primary"
          )}
        />
      )}
    </button>
  );
};

export default WishlistButton;
