import { useState, useEffect } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { Heart, ShoppingCart, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useAuth } from '@/contexts/AuthContext';
import { useCart } from '@/contexts/CartContext';
import { useToast } from '@/hooks/use-toast';
import { getWishlist, removeFromWishlist } from '@/db/api';
import { formatINR } from '@/lib/currency';

export default function Wishlist() {
  const [wishlistItems, setWishlistItems] = useState<any[]>([]);
  const [localLoading, setLocalLoading] = useState(true);
  const { isAuthenticated, loading: authLoading } = useAuth();
  const { addToCart } = useCart();
  const { toast } = useToast();
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    if (!authLoading) {
      if (!isAuthenticated) {
        navigate('/login', { state: { from: location } });
        return;
      }
      fetchWishlist();
    }
  }, [isAuthenticated, authLoading, location]);

  const fetchWishlist = async () => {
    try {
      setLocalLoading(true);
      const data = await getWishlist();
      setWishlistItems(data);
    } catch (error) {
      console.error('Error fetching wishlist:', error);
      toast({
        title: "Error",
        description: "Failed to load wishlist.",
        variant: "destructive",
      });
    } finally {
      setLocalLoading(false);
    }
  };

  const handleRemove = async (productId: string, productName: string) => {
    try {
      await removeFromWishlist(productId);
      setWishlistItems(items => items.filter(item => item.product_id !== productId));
      toast({
        title: "Removed from Wishlist",
        description: `${productName} has been removed from your wishlist.`,
      });
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to remove item from wishlist.",
        variant: "destructive",
      });
    }
  };

  const handleAddToCart = (product: any) => {
    addToCart(product.products, 1);
    toast({
      title: "Added to Cart",
      description: `${product.products.name} has been added to your shopping bag.`,
    });
  };

  if (authLoading || localLoading) {
    return (
      <div className="min-h-screen bg-white py-12">
        <div className="container">
          <div className="animate-pulse">
            <div className="h-12 bg-muted rounded w-1/3 mb-4"></div>
            <div className="h-6 bg-muted rounded w-1/2 mb-8"></div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              {[1, 2, 3].map(i => (
                <div key={i} className="h-96 bg-muted rounded"></div>
              ))}
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white py-12">
      <div className="container">
        <div className="mb-8">
          <h1 className="text-4xl md:text-5xl font-bold tracking-tight mb-2">My Wishlist</h1>
          <p className="text-lg text-muted-foreground">Save your favorite items for later</p>
        </div>

        {wishlistItems.length === 0 ? (
          <Card className="p-16 text-center">
            <Heart className="h-16 w-16 mx-auto mb-4 text-muted-foreground" />
            <h2 className="text-2xl font-bold mb-2">Your wishlist is empty</h2>
            <p className="text-muted-foreground mb-6">
              Start adding items you love to your wishlist
            </p>
            <Link to="/products">
              <Button size="lg" variant="secondary">
                Browse Products
              </Button>
            </Link>
          </Card>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {wishlistItems.map((item) => (
              <Card key={item.id} className="overflow-hidden">
                <Link to={`/product/${item.products.slug}`}>
                  <div className="aspect-square relative">
                    <img
                      src={item.products.image_url}
                      alt={item.products.name}
                      className="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
                    />
                  </div>
                </Link>
                <div className="p-4">
                  <Link to={`/product/${item.products.slug}`}>
                    <h3 className="font-bold text-lg mb-2 hover:text-secondary transition-colors">
                      {item.products.name}
                    </h3>
                  </Link>
                  <p className="text-2xl font-bold text-primary mb-4">
                    {formatINR(item.products.price)}
                  </p>
                  <div className="flex gap-2">
                    <Button
                      className="flex-1"
                      size="sm"
                      onClick={() => handleAddToCart(item)}
                    >
                      <ShoppingCart className="h-4 w-4 mr-2" />
                      Add to Cart
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleRemove(item.product_id, item.products.name)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </Card>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
