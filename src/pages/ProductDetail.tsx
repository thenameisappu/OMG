import { formatINR } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate, useLocation } from 'react-router-dom';
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
  Flower,
  MapPin,
  Check,
  Clock,
  Sparkles
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { useCart } from '@/contexts/CartContext';
import { useAuth } from '@/contexts/AuthContext';
import { useToast } from '@/hooks/use-toast';
import { getProductBySlug, addToWishlist, removeFromWishlist, isInWishlist } from '@/db/api';
import { surpriseService } from '@/services/api';

export default function ProductDetail() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const { addToCart } = useCart();
  const { isAuthenticated } = useAuth();
  const { toast } = useToast();
  const [quantity, setQuantity] = useState(1);
  const [activeImage, setActiveImage] = useState(0);
  const [inWishlist, setInWishlist] = useState(false);
  const [wishlistLoading, setWishlistLoading] = useState(false);
  const [product, setProduct] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  // Pincode Checker state
  const [pincode, setPincode] = useState('');
  const [pincodeStatus, setPincodeStatus] = useState<null | 'valid' | 'invalid'>(null);

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
        title: "Error Loading Product",
        description: "Failed to fetch arrangement details.",
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
          title: "Saved to Wishlist ✨",
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

  const checkPincode = async (e: React.FormEvent) => {
    e.preventDefault();
    const cleanPin = pincode.replace(/\D/g, '').slice(0, 6);
    if (!cleanPin || cleanPin.length !== 6) {
      setPincodeStatus({ valid: false, message: 'Please enter a valid 6-digit numeric pincode.' });
      return;
    }
    try {
      const res = await surpriseService.checkPincode(cleanPin);
      if (res && res.valid) {
        setPincodeStatus({ valid: true, message: res.message || `Delivery Available in Bengaluru for ${cleanPin} ✨` });
      } else {
        setPincodeStatus({ valid: false, message: res.message || 'Sorry, we currently deliver only within Bengaluru.' });
      }
    } catch (e) {
      setPincodeStatus({ valid: false, message: 'Sorry, we currently deliver only within Bengaluru.' });
    }
  };

  if (loading) {
    return (
      <div className="bg-white min-h-screen flex flex-col justify-center items-center py-32">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-secondary mb-4"></div>
        <p className="text-primary font-bold tracking-widest text-xs uppercase">Loading Arrangement Details...</p>
      </div>
    );
  }

  if (!product) {
    return (
      <div className="container py-24 text-center space-y-4">
        <h2 className="text-3xl font-bold font-serif">Product Not Found</h2>
        <p className="text-muted-foreground text-sm">The requested floral design is no longer available.</p>
        <Button onClick={() => navigate('/products')} variant="secondary" className="rounded-full px-8 font-bold">
          Back to Shop Collections
        </Button>
      </div>
    );
  }

  const handleAddToCart = () => {
    addToCart(product, quantity);
    toast({
      title: "✨ Added to Shopping Bag",
      description: `${quantity} × ${product.name} added to your cart.`,
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
    <div className="bg-white min-h-screen pb-20">
      <div className="container py-10">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-muted-foreground text-xs uppercase tracking-wider mb-8">
          <Link to="/" className="hover:text-secondary transition-colors">Home</Link>
          <ChevronRight className="h-3 w-3" />
          <Link to="/products" className="hover:text-secondary transition-colors">Shop</Link>
          <ChevronRight className="h-3 w-3" />
          <span className="text-primary font-bold">{product.name}</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
          {/* Gallery Images */}
          <div className="lg:col-span-6 space-y-4">
            <div className="aspect-[4/4] md:aspect-[4/5] overflow-hidden rounded-3xl bg-muted/20 relative luxury-shadow border border-border">
              <img
                src={images[activeImage]}
                alt={product.name}
                className="h-full w-full object-cover transition-all duration-700 hover:scale-105"
              />
              <Button
                variant="ghost"
                size="icon"
                className={cn(
                  "absolute top-5 right-5 backdrop-blur-md rounded-full shadow-md transition-all hover:scale-110",
                  inWishlist ? "bg-secondary text-primary hover:bg-secondary/90" : "bg-white/80 text-foreground"
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
                    "aspect-square rounded-xl overflow-hidden border-2 transition-all duration-300",
                    activeImage === idx ? "border-secondary scale-105 shadow-md" : "border-border opacity-70 hover:opacity-100"
                  )}
                >
                  <img src={img} alt="" className="h-full w-full object-cover" />
                </button>
              ))}
            </div>
          </div>

          {/* Product Detail Info */}
          <div className="lg:col-span-6 flex flex-col space-y-6">
            <div>
              <div className="flex items-center gap-3 mb-3">
                <Badge variant="secondary" className="bg-secondary/15 text-secondary border-none uppercase tracking-widest text-[10px] font-bold px-3 py-1">
                  {product.category ? product.category.replace('-', ' ') : 'Bloom'}
                </Badge>
                <span className="text-xs text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded font-bold flex items-center gap-1">
                  <ShieldCheck className="h-3.5 w-3.5" /> Freshness Guaranteed
                </span>
              </div>

              <h1 className="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold font-serif text-primary mb-3 leading-tight">
                {product.name}
              </h1>

              <div className="flex flex-wrap items-center gap-3 text-sm">
                <div className="flex items-center text-amber-500 font-bold">
                  {[1, 2, 3, 4, 5].map(i => <Star key={i} className="h-4 w-4 fill-current" />)}
                  <span className="ml-2 text-primary font-bold">{product.rating || 4.9}</span>
                </div>
                <Separator orientation="vertical" className="h-4" />
                <span className="text-xs text-muted-foreground font-medium">{product.reviews_count || 48} Verified Reviews</span>
                <Separator orientation="vertical" className="h-4" />
                <span className="text-xs text-emerald-600 font-bold">
                  In Stock & Ready for Delivery
                </span>
              </div>
            </div>

            <div className="p-6 rounded-2xl bg-muted/20 border border-border/80 flex items-baseline justify-between">
              <div>
                <p className="text-xs font-bold text-muted-foreground uppercase tracking-widest mb-1">Price</p>
                <span className="text-4xl font-extrabold text-primary">{formatINR(product.price)}</span>
                <p className="text-xs text-muted-foreground mt-1">Includes all applicable taxes & charges</p>
              </div>
            </div>

            <p className="text-base text-muted-foreground leading-relaxed font-light">
              {product.description}
            </p>

            {/* Pincode Delivery Slot Checker */}
            <div className="p-5 rounded-2xl border border-secondary/20 bg-secondary/5 space-y-3">
              <span className="text-xs font-bold text-primary uppercase tracking-wider flex items-center gap-1.5">
                <MapPin className="h-4 w-4 text-secondary" />
                Check Same-Day Delivery Availability
              </span>
              <form onSubmit={checkPincode} className="flex flex-col sm:flex-row gap-2">
                <Input
                  maxLength={6}
                  placeholder="Enter 6-digit Pincode (e.g. 560038)"
                  className="h-11 bg-white text-sm border-border flex-1 font-mono tracking-wider"
                  value={pincode}
                  onChange={(e) => {
                    const clean = e.target.value.replace(/\D/g, '').slice(0, 6);
                    setPincode(clean);
                    setPincodeStatus(null);
                  }}
                />
                <Button type="submit" variant="secondary" className="h-11 px-6 font-bold text-xs uppercase tracking-wider whitespace-nowrap">
                  Check Slot
                </Button>
              </form>
              {pincodeStatus?.valid === true && (
                <p className="text-xs text-emerald-700 font-bold flex items-center gap-1">
                  <Check className="h-4 w-4" /> {pincodeStatus.message}
                </p>
              )}
              {pincodeStatus?.valid === false && (
                <p className="text-xs text-red-500 font-medium">{pincodeStatus.message}</p>
              )}
            </div>

            {/* Quantity and Actions */}
            <div className="space-y-4 pt-2">
              <div className="flex flex-col sm:flex-row gap-4">
                <div className="flex items-center border border-border rounded-full px-4 h-14 bg-white justify-between w-full sm:w-36">
                  <button
                    onClick={() => setQuantity(q => Math.max(1, q - 1))}
                    className="p-2 hover:text-secondary"
                  >
                    <Minus className="h-4 w-4" />
                  </button>
                  <span className="font-bold text-lg">{quantity}</span>
                  <button
                    onClick={() => setQuantity(q => q + 1)}
                    className="p-2 hover:text-secondary"
                  >
                    <Plus className="h-4 w-4" />
                  </button>
                </div>

                <div className="flex-1 flex gap-3">
                  <Button
                    size="lg"
                    variant="outline"
                    className="flex-1 h-14 rounded-full text-base font-bold border-secondary text-secondary hover:bg-secondary hover:text-primary transition-all"
                    onClick={handleAddToCart}
                  >
                    Add to Bag
                  </Button>
                  <Button
                    size="lg"
                    variant="secondary"
                    className="flex-1 h-14 rounded-full text-base font-bold shadow-lg gold-glow hover-lift"
                    onClick={handleBuyNow}
                  >
                    Buy Now
                  </Button>
                </div>
              </div>
            </div>

            {/* Feature Icons */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4">
              <div className="p-4 rounded-xl border border-border bg-white flex items-center gap-3">
                <Truck className="h-6 w-6 text-secondary flex-shrink-0" />
                <div>
                  <h4 className="text-xs font-bold uppercase tracking-wider text-primary">Express Delivery</h4>
                  <p className="text-[11px] text-muted-foreground">Within 2 to 4 hours in Bangalore</p>
                </div>
              </div>
              <div className="p-4 rounded-xl border border-border bg-white flex items-center gap-3">
                <Flower className="h-6 w-6 text-secondary flex-shrink-0" />
                <div>
                  <h4 className="text-xs font-bold uppercase tracking-wider text-primary">Farm Fresh Stems</h4>
                  <p className="text-[11px] text-muted-foreground">Guaranteed 7-day stay bloom life</p>
                </div>
              </div>
            </div>

            {/* Tabs */}
            <Tabs defaultValue="details" className="w-full pt-4">
              <TabsList className="w-full justify-start border-b rounded-none h-12 bg-transparent p-0 gap-6">
                <TabsTrigger value="details" className="rounded-none border-b-2 border-transparent data-[state=active]:border-secondary font-bold text-xs uppercase tracking-wider bg-transparent p-0 pb-3">Arrangement Details</TabsTrigger>
                <TabsTrigger value="shipping" className="rounded-none border-b-2 border-transparent data-[state=active]:border-secondary font-bold text-xs uppercase tracking-wider bg-transparent p-0 pb-3">Delivery Info</TabsTrigger>
                <TabsTrigger value="care" className="rounded-none border-b-2 border-transparent data-[state=active]:border-secondary font-bold text-xs uppercase tracking-wider bg-transparent p-0 pb-3">Flower Care Guide</TabsTrigger>
              </TabsList>
              <TabsContent value="details" className="pt-4 text-xs md:text-sm text-muted-foreground leading-relaxed">
                Handcrafted using farm-fresh, premium cut stems by OMG master florists. Wrapped in signature luxury eco-velvet packaging with custom message cards.
              </TabsContent>
              <TabsContent value="shipping" className="pt-4 text-xs md:text-sm text-muted-foreground leading-relaxed">
                Same-day express delivery across Bangalore for orders placed before 2:00 PM. Transported in specialized temperature-regulated vehicles.
              </TabsContent>
              <TabsContent value="care" className="pt-4 text-xs md:text-sm text-muted-foreground leading-relaxed">
                Trim stems at a 45° angle, place in fresh cool water with flower food, and keep away from direct sunlight and air conditioners.
              </TabsContent>
            </Tabs>
          </div>
        </div>
      </div>
    </div>
  );
}
