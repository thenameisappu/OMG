import { formatINR } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import {
  Star,
  Truck,
  Calendar,
  ShieldCheck,
  ChevronRight,
  Minus,
  Plus,
  Heart,
  Share2,
  Flower
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCart } from '@/contexts/CartContext';
import { useAuth } from '@/contexts/AuthContext';
import { useToast } from '@/hooks/use-toast';
import { getProductBySlug, addToWishlist, removeFromWishlist, isInWishlist } from '@/db/api';

export default function ProductDetail() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const { addToCart } = useCart();
  const { isAuthenticated } = useAuth();
  const { toast } = useToast();
  const [quantity, setQuantity] = useState(1);
  const [activeImage, setActiveImage] = useState(0);
  const [inWishlist, setInWishlist] = useState(false);
  const [wishlistLoading, setWishlistLoading] = useState(false);
  const [product, setProduct] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (slug) {
      fetchProduct();
    }
  }, [slug]);

  useEffect(() => {
    if (product && isAuthenticated) {
      checkWishlistStatus();
    }
  }, [product, isAuthenticated]);

  const fetchProduct = async () => {
    try {
      setLoading(true);
      const data = await getProductBySlug(slug!);
      setProduct(data);
    } catch (error) {
      console.error('Error fetching product:', error);
      toast({
        title: "Error",
        description: "Failed to load product details.",
        variant: "destructive",
      });
    } finally {
      setLoading(false);
    }
  };

  const checkWishlistStatus = async () => {
    if (!product) return;
    try {
      const status = await isInWishlist(product.id);
      setInWishlist(status);
    } catch (error) {
      console.error('Error checking wishlist:', error);
    }
  };

  const handleWishlistToggle = async () => {
    if (!isAuthenticated) {
      toast({
        title: "Login Required",
        description: "Please login to add items to your wishlist.",
        variant: "destructive",
      });
      navigate('/login', { state: { from: location } });
      return;
    }

    if (!product) return;

    setWishlistLoading(true);
    try {
      if (inWishlist) {
        await removeFromWishlist(product.id);
        setInWishlist(false);
        toast({
          title: "Removed from Wishlist",
          description: `${product.name} has been removed from your wishlist.`,
        });
      } else {
        await addToWishlist(product.id);
        setInWishlist(true);
        toast({
          title: "Added to Wishlist",
          description: `${product.name} has been added to your wishlist.`,
        });
      }
    } catch (error: any) {
      toast({
        title: "Error",
        description: error.message || "Failed to update wishlist.",
        variant: "destructive",
      });
    } finally {
      setWishlistLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="bg-background min-h-screen pb-20">
        <div className="container py-20 text-center">
          <div className="animate-pulse">
            <div className="h-8 bg-muted rounded w-1/3 mx-auto mb-4"></div>
            <div className="h-4 bg-muted rounded w-1/2 mx-auto"></div>
          </div>
        </div>
      </div>
    );
  }

  if (!product) {
    return (
      <div className="container py-20 text-center">
        <h2 className="text-3xl font-bold mb-4">Product Not Found</h2>
        <Button onClick={() => navigate('/products')}>Back to Shop</Button>
      </div>
    );
  }

  const handleAddToCart = () => {
    addToCart(product, quantity);
    toast({
      title: "Added to Cart",
      description: `${product.name} has been added to your shopping bag.`,
    });
  };

  const handleBuyNow = () => {
    addToCart(product, quantity);
    navigate('/checkout');
  };

  const images = [
    product.image,
    product.hover_image || product.image,
    product.image
  ];

  return (
    <div className="bg-background min-h-screen pb-20">
      <div className="container py-8">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-muted-foreground text-sm mb-10">
          <Link to="/" className="hover:text-primary transition-colors">Home</Link>
          <ChevronRight className="h-3 w-3" />
          <Link to="/products" className="hover:text-primary transition-colors">Shop</Link>
          <ChevronRight className="h-3 w-3" />
          <span className="text-primary font-medium">{product.name}</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-16">
          {/* Product Images */}
          <div className="space-y-4">
            <div className="aspect-[4/5] overflow-hidden rounded-xl bg-muted relative">
              <img
                src={product.image}
                alt={product.name}
                className="h-full w-full object-cover transition-all duration-500"
              />
              <Button
                variant="ghost"
                size="icon"
                className={cn(
                  "absolute top-4 right-4 backdrop-blur rounded-full hover:bg-white transition-colors",
                  inWishlist ? "bg-secondary text-white hover:bg-secondary/90" : "bg-white/80"
                )}
                onClick={handleWishlistToggle}
                disabled={wishlistLoading}
              >
                <Heart className={cn("h-5 w-5", inWishlist && "fill-current")} />
              </Button>
            </div>
            <div className="grid grid-cols-3 gap-4">
              {images.map((img, idx) => (
                <button
                  key={idx}
                  onClick={() => setActiveImage(idx)}
                  className={cn(
                    "aspect-square rounded-lg overflow-hidden border-2 transition-all",
                    activeImage === idx ? "border-secondary" : "border-transparent opacity-60 hover:opacity-100"
                  )}
                >
                  <img src={img} alt="" className="h-full w-full object-cover" />
                </button>
              ))}
            </div>
          </div>

          {/* Product Info */}
          <div className="flex flex-col">
            <div className="mb-6">
              <Badge variant="secondary" className="mb-4 bg-secondary/10 text-secondary border-none uppercase tracking-widest px-3">
                {product.category.replace('-', ' ')}
              </Badge>
              <h1 className="text-4xl md:text-5xl font-bold text-primary mb-4 font-serif-luxury italic">
                {product.name}
              </h1>
              <div className="flex items-center gap-4">
                <div className="flex items-center text-yellow-500">
                  {[1, 2, 3, 4, 5].map(i => <Star key={i} className="h-4 w-4 fill-current" />)}
                  <span className="ml-2 text-sm font-bold text-primary">{product.rating || 5.0}</span>
                </div>
                <Separator orientation="vertical" className="h-4" />
                <span className="text-sm text-muted-foreground">{product.reviews_count || 0} Reviews</span>
                <Separator orientation="vertical" className="h-4" />
                <span className="text-sm text-success flex items-center gap-1 font-medium">
                  <ShieldCheck className="h-4 w-4" /> {product.stock_status === 'in_stock' ? 'In Stock' : 'Out of Stock'}
                </span>
              </div>
            </div>

            <div className="mb-8">
              <span className="text-4xl font-bold text-primary">{formatINR(product.price)}</span>
              <p className="text-sm text-muted-foreground mt-1">Inclusive of all taxes & nationwide delivery</p>
            </div>

            <p className="text-lg text-muted-foreground leading-relaxed mb-8">
              {product.description}
            </p>

            <div className="flex flex-col sm:flex-row gap-4 mb-10">
              <div className="flex items-center border border-input rounded-full px-4 h-14 bg-background">
                <button
                  onClick={() => setQuantity(q => Math.max(1, q - 1))}
                  className="p-1 hover:text-secondary"
                >
                  <Minus className="h-4 w-4" />
                </button>
                <span className="w-12 text-center font-bold text-lg">{quantity}</span>
                <button
                  onClick={() => setQuantity(q => q + 1)}
                  className="p-1 hover:text-secondary"
                >
                  <Plus className="h-4 w-4" />
                </button>
              </div>
              <div className="flex gap-4">
                <Button
                  size="lg"
                  variant="secondary"
                  className="flex-1 h-14 rounded-full text-lg font-bold"
                  onClick={handleAddToCart}
                >
                  Add to Shopping Bag
                </Button>
                <Button
                  size="lg"
                  variant="default"
                  className="flex-1 h-14 rounded-full text-lg font-bold bg-primary text-white hover:bg-primary/90"
                  onClick={handleBuyNow}
                >
                  Buy Now
                </Button>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4 mb-10">
              <div className="p-4 rounded-xl border bg-white flex flex-col items-center text-center gap-2">
                <Truck className="h-6 w-6 text-primary" />
                <span className="text-xs font-bold uppercase tracking-wider">Same Day Delivery</span>
                <span className="text-[10px] text-muted-foreground">Orders before 1PM</span>
              </div>
              <div className="p-4 rounded-xl border bg-white flex flex-col items-center text-center gap-2">
                <Calendar className="h-6 w-6 text-primary" />
                <span className="text-xs font-bold uppercase tracking-wider">Scheduled Slots</span>
                <span className="text-[10px] text-muted-foreground">Pick your time</span>
              </div>
            </div>

            <Tabs defaultValue="details" className="w-full">
              <TabsList className="w-full justify-start border-b rounded-none h-12 bg-transparent p-0">
                <TabsTrigger value="details" className="rounded-none border-b-2 border-transparent data-[state=active]:border-secondary bg-transparent data-[state=active]:bg-transparent">Details</TabsTrigger>
                <TabsTrigger value="shipping" className="rounded-none border-b-2 border-transparent data-[state=active]:border-secondary bg-transparent data-[state=active]:bg-transparent">Shipping</TabsTrigger>
                <TabsTrigger value="care" className="rounded-none border-b-2 border-transparent data-[state=active]:border-secondary bg-transparent data-[state=active]:bg-transparent">Care Guide</TabsTrigger>
              </TabsList>
              <TabsContent value="details" className="pt-6 text-sm text-muted-foreground leading-relaxed">
                This exquisite arrangement features hand-selected blooms sourced from our premium growers.
                Our florists carefully arrange each stem to create a visual symphony of colors and textures.
                Perfect for anniversaries, birthdays, or just because.
              </TabsContent>
              <TabsContent value="shipping" className="pt-6 text-sm text-muted-foreground leading-relaxed">
                We offer nationwide delivery across all major cities. Same-day delivery is available for orders placed
                before 1:00 PM local time. All arrangements are transported in climate-controlled vehicles
                to ensure maximum freshness upon arrival.
              </TabsContent>
              <TabsContent value="care" className="pt-6 text-sm text-muted-foreground leading-relaxed">
                Keep your flowers in a cool, draft-free spot. Recut stems diagonally every 2 days and replace
                the water with fresh lukewarm water. Use the provided flower food to extend the life of your blooms.
              </TabsContent>
            </Tabs>
          </div>
        </div>
      </div>
    </div>
  );
}

