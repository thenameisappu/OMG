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
  Sparkles,
  Loader2,
  X
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { useCart } from '@/contexts/CartContext';
import { useAuth } from '@/contexts/AuthContext';
import { useToast } from '@/hooks/use-toast';
import { getProductBySlug } from '@/db/api';
import { productService, surpriseService } from '@/services/api';
import { WishlistButton } from '@/components/common/WishlistButton';


function getProductRating(product: any): string {
  if (product.rating && Number(product.rating) > 0) {
    return Number(product.rating).toFixed(1);
  }
  const seed = String(product.id || product.slug || '').split('').reduce((acc: number, c: string) => acc + c.charCodeAt(0), 0);
  const steps = [4.7, 4.8, 4.9, 5.0];
  return steps[seed % steps.length].toFixed(1);
}

export default function ProductDetail() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const { addToCart } = useCart();
  const { isAuthenticated } = useAuth();
  const { toast } = useToast();
  const [quantity, setQuantity] = useState(1);
  const [activeImage, setActiveImage] = useState(0);
  const [isLightboxOpen, setIsLightboxOpen] = useState(false);
  const [product, setProduct] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  // Pincode Checker state
  const [pincode, setPincode] = useState('');
  const [pincodeLoading, setPincodeLoading] = useState(false);
  const [pincodeStatus, setPincodeStatus] = useState<{ valid: boolean; message: string; detail?: string } | null>(null);
  const [selectedRating, setSelectedRating] = useState(0);
  const [ratingSubmitting, setRatingSubmitting] = useState(false);

  useEffect(() => {
    if (slug) {
      fetchProduct();
    }
  }, [slug]);

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

  const checkPincode = async (e: React.FormEvent) => {
    e.preventDefault();
    const cleanPin = pincode.replace(/\D/g, '').slice(0, 6);
    if (!cleanPin || cleanPin.length !== 6) {
      setPincodeStatus({
        valid: false,
        message: 'Please enter a valid 6-digit pincode.'
      });
      return;
    }

    // Bengaluru pincodes always start with 560
    if (!cleanPin.startsWith('560')) {
      setPincodeStatus({
        valid: false,
        message: '😔 Sorry, we are currently not delivering to your pincode. We currently deliver only within Bengaluru.'
      });
      return;
    }

    setPincodeLoading(true);
    setPincodeStatus(null);
    try {
      const res = await surpriseService.checkPincode(cleanPin);
      if (res && res.valid) {
        setPincodeStatus({
          valid: true,
          message: '✅ Delivery Available',
          detail: res.area_name ? `Same-day delivery available to ${res.area_name}.` : 'Same-day delivery is available for your location.'
        });
      } else {
        setPincodeStatus({
          valid: false,
          message: '😔 Sorry, we are currently not delivering to your pincode. We currently deliver only within Bengaluru.'
        });
      }
    } catch (e) {
      setPincodeStatus({
        valid: false,
        message: '😔 Sorry, we are currently not delivering to your pincode. We currently deliver only within Bengaluru.'
      });
    } finally {
      setPincodeLoading(false);
    }
  };

  const submitRating = async () => {
    if (!isAuthenticated || !selectedRating || !product) {
      toast({ title: 'Please sign in to rate this product.', variant: 'destructive' });
      return;
    }
    setRatingSubmitting(true);
    try {
      const result = await productService.submitRating(product.id, selectedRating);
      setProduct((current: any) => ({ ...current, rating: result.rating, rating_count: result.rating_count }));
      toast({ title: 'Rating saved', description: 'Thank you for rating this product.' });
    } catch (error: any) {
      toast({ title: 'Could not save rating', description: error.response?.data?.message || 'Please try again.', variant: 'destructive' });
    } finally {
      setRatingSubmitting(false);
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

  const isFlowerOrBouquet =
    product.category?.toLowerCase() === 'flower-arrangements' ||
    /flower|bouquet/i.test(product.name || '');

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

  // Helper to extract gallery image URLs
  const getGalleryImages = (prod: any): string[] => {
    if (!prod) return [];
    let gallery: string[] = [];
    if (Array.isArray(prod.images)) {
      gallery = prod.images.filter((img: any) => typeof img === 'string' && img.trim() !== '');
    } else if (typeof prod.images === 'string' && prod.images.trim() !== '') {
      try {
        const parsed = JSON.parse(prod.images);
        if (Array.isArray(parsed)) {
          gallery = parsed.filter((img: any) => typeof img === 'string' && img.trim() !== '');
        }
      } catch {
        gallery = [prod.images.trim()];
      }
    }
    return gallery;
  };

  // Construct deduplicated product image list: Main -> Hover (if unique) -> Gallery Images
  const images: string[] = (() => {
    if (!product) return [];
    const list: string[] = [];

    // 1. Main Image
    if (product.image && typeof product.image === 'string' && product.image.trim() !== '') {
      list.push(product.image.trim());
    }

    // 2. Hover Image (only if unique)
    if (
      product.hover_image &&
      typeof product.hover_image === 'string' &&
      product.hover_image.trim() !== '' &&
      !list.includes(product.hover_image.trim())
    ) {
      list.push(product.hover_image.trim());
    }

    // 3. Gallery Images (only unique URLs)
    const galleryList = getGalleryImages(product);
    for (const galleryImg of galleryList) {
      if (galleryImg && !list.includes(galleryImg)) {
        list.push(galleryImg);
      }
    }

    return list.length > 0 ? list : ['/images/placeholder.jpg'];
  })();

  return (
    <div className="bg-white min-h-0 flex flex-col justify-start pb-6 lg:pb-8">
      <div className="container max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-2 lg:py-3 flex-1 flex flex-col justify-start">
        {/* Breadcrumb */}
        <div className="flex items-center gap-1.5 text-muted-foreground text-[11px] font-bold uppercase tracking-wider mb-2 lg:mb-3 shrink-0">
          <Link to="/" className="hover:text-secondary transition-colors">Home</Link>
          <ChevronRight className="h-3 w-3" />
          <Link to="/products" className="hover:text-secondary transition-colors">Shop</Link>
          <ChevronRight className="h-3 w-3" />
          <span className="text-primary font-bold">{product.name}</span>
        </div>

        {/* 2-Column Main Layout (Balanced 50/50 Desktop Split) */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-8 items-start w-full">
          {/* Left Column: Product Gallery (Adjusted for Screen View) */}
          <div className="lg:col-span-6 max-w-[340px] sm:max-w-[380px] lg:max-w-[410px] flex flex-col justify-start w-full shrink-0 mx-auto lg:mx-0">
            {/* Main Product Image (Strict 3:4 Aspect Ratio) */}
            <div className="w-full aspect-[3/4] rounded-2xl sm:rounded-[24px] overflow-hidden bg-neutral-100 relative shadow-sm border border-neutral-200/80 shrink-0 group">
              <img
                src={images[activeImage] || images[0]}
                alt={product.name}
                className="h-full w-full object-cover transition-all duration-500 group-hover:scale-102 cursor-pointer"
                onClick={() => setIsLightboxOpen(true)}
                onError={(e) => {
                  const target = e.currentTarget as HTMLImageElement;
                  if (product.image && target.src !== product.image) {
                    target.src = product.image;
                  }
                }}
              />
              <WishlistButton
                product={product}
                size="lg"
                variant="gold"
                className="absolute top-3.5 right-3.5 z-20"
              />
            </div>

            {/* Product Gallery Thumbnails */}
            {images.length > 1 && (
              <div
                className={cn(
                  "mt-3 w-full shrink-0 gap-3",
                  images.length === 2 ? "grid grid-cols-2" :
                    images.length === 3 ? "grid grid-cols-3" :
                      images.length === 4 ? "grid grid-cols-4" :
                        "flex items-center overflow-x-auto scrollbar-hide py-1"
                )}
              >
                {images.map((img, idx) => (
                  <button
                    key={idx}
                    onClick={() => setActiveImage(idx)}
                    className={cn(
                      "aspect-square rounded-xl sm:rounded-[14px] overflow-hidden transition-all duration-200 p-0.5 flex items-center justify-center bg-white",
                      images.length <= 4 ? "w-full" : "w-16 sm:w-20 shrink-0",
                      activeImage === idx
                        ? "border-2 border-secondary shadow-xs scale-[1.02] bg-secondary/10"
                        : "border border-neutral-200/90 opacity-75 hover:opacity-100"
                    )}
                  >
                    <img
                      src={img}
                      alt={`${product.name} view ${idx + 1}`}
                      className="w-full h-full object-cover rounded-[10px] aspect-square"
                      onError={(e) => {
                        const target = e.currentTarget as HTMLImageElement;
                        if (product.image && target.src !== product.image) {
                          target.src = product.image;
                        }
                      }}
                    />
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Right Column: Scaled Up & Balanced Product Information */}
          <div className="lg:col-span-6 flex flex-col justify-start space-y-3 lg:space-y-3.5 w-full">
            {/* Badges */}
            <div className="flex flex-wrap items-center gap-2">
              {(product.is_bestseller === true || Number(product.is_bestseller) === 1) && (
                <span className="text-xs text-amber-800 bg-amber-50 border border-amber-300 px-3 py-1 rounded-full font-bold flex items-center gap-1.5 uppercase tracking-wider">
                  <Sparkles className="h-3.5 w-3.5 text-amber-600 fill-amber-500" /> Best Seller
                </span>
              )}
              <Badge variant="secondary" className="bg-secondary/15 text-secondary border-none uppercase tracking-widest text-xs font-bold px-3 py-1 rounded-full">
                {product.category ? product.category.replace('-', ' ') : 'GIFTING'}
              </Badge>
              <span className="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200/60 px-3 py-1 rounded-full font-bold flex items-center gap-1.5">
                <ShieldCheck className="h-3.5 w-3.5" /> Freshness Guaranteed
              </span>
            </div>

            {/* Product Title (Noticeably Larger Hierarchy) */}
            <h1 className="text-3xl sm:text-4xl font-bold font-serif text-primary leading-tight tracking-tight">
              {product.name}
            </h1>

            {/* Product Description from Admin Panel */}
            {product.description && product.description.trim() !== '' && (
              <p className="text-sm sm:text-base text-muted-foreground leading-relaxed whitespace-pre-line">
                {product.description}
              </p>
            )}

            {/* Rating & Reviews */}
            <div className="flex flex-wrap items-center gap-2.5 text-xs sm:text-sm text-muted-foreground font-medium">
              <div className="flex items-center text-amber-500 font-bold">
                {[1, 2, 3, 4, 5].map(i => <Star key={i} className="h-4 w-4 fill-current" />)}
              </div>
              <span className="font-bold text-primary text-sm">{getProductRating(product)}</span>
              <Separator orientation="vertical" className="h-3.5" />
              <span className="text-xs sm:text-sm text-muted-foreground font-normal">{product.rating_count || product.reviews_count || 0} Ratings</span>
              <Separator orientation="vertical" className="h-3.5" />
              <span className="text-xs sm:text-sm text-emerald-600 font-bold">In Stock & Ready for Delivery</span>
            </div>

            <div className="flex flex-wrap items-center gap-3 text-sm">
              <span className="font-semibold text-primary">Rate this product:</span>
              <div className="flex items-center" role="radiogroup" aria-label="Rate this product">
                {[1, 2, 3, 4, 5].map((value) => (
                  <button
                    key={value}
                    type="button"
                    onClick={() => setSelectedRating(value)}
                    className="p-0.5"
                    aria-label={`${value} star${value > 1 ? 's' : ''}`}
                  >
                    <Star className={`h-5 w-5 ${value <= selectedRating ? 'fill-amber-400 text-amber-400' : 'text-neutral-300'}`} />
                  </button>
                ))}
              </div>
              <Button type="button" size="sm" variant="outline" onClick={submitRating} disabled={ratingSubmitting || !selectedRating}>
                {ratingSubmitting ? 'Saving...' : 'Submit Rating'}
              </Button>
            </div>

            {/* Price Card */}
            <div className="p-4 px-5 rounded-2xl bg-secondary/10 border border-secondary/20 flex flex-col justify-center">
              <p className="text-xs font-bold text-muted-foreground uppercase tracking-widest mb-1">Price</p>
              <span className="text-3xl sm:text-4xl font-extrabold text-primary tracking-tight">{formatINR(product.price)}</span>
              <p className="text-xs text-muted-foreground mt-1">Includes all applicable taxes & charges</p>
            </div>

            {/* Pincode Delivery Slot Checker */}
            <div className="p-4 rounded-2xl border border-secondary/20 bg-secondary/5 space-y-2">
              <span className="text-xs font-bold text-primary uppercase tracking-wider flex items-center gap-2">
                <MapPin className="h-4 w-4 text-secondary" />
                CHECK SAME-DAY DELIVERY AVAILABILITY
              </span>
              <form onSubmit={checkPincode} className="flex gap-2">
                <Input
                  maxLength={6}
                  placeholder="Enter 6-digit Pincode (e.g., 560036)"
                  className="h-10.5 bg-white text-sm border-border rounded-xl flex-1 font-mono px-3.5 shadow-2xs placeholder:font-sans placeholder:text-muted-foreground"
                  value={pincode}
                  onChange={(e) => {
                    const clean = e.target.value.replace(/\D/g, '').slice(0, 6);
                    setPincode(clean);
                    setPincodeStatus(null);
                  }}
                />
                <Button
                  type="submit"
                  variant="secondary"
                  disabled={pincodeLoading}
                  className="h-10.5 px-5 rounded-xl font-bold text-xs uppercase tracking-wider whitespace-nowrap shrink-0 border-2 border-secondary shadow-2xs"
                >
                  {pincodeLoading ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    "CHECK SLOT"
                  )}
                </Button>
              </form>
              {pincodeStatus?.valid === true && (
                <div className="p-2.5 px-3.5 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-xs sm:text-sm flex items-center gap-2">
                  <Check className="h-4 w-4 text-emerald-600 shrink-0" />
                  <span className="font-semibold">{pincodeStatus.message}</span>
                </div>
              )}
              {pincodeStatus?.valid === false && (
                <div className="p-2.5 px-3.5 rounded-xl bg-rose-50 border border-rose-200/80 text-rose-700 font-medium text-xs sm:text-sm">
                  {pincodeStatus.message}
                </div>
              )}
            </div>

            {/* Action Row: Quantity + Add to Bag + Buy Now */}
            <div className="flex flex-wrap sm:flex-nowrap items-center gap-2.5 sm:gap-3 w-full pt-1">
              {/* Quantity Selector */}
              <div className="flex items-center justify-between h-11 px-3.5 border border-border rounded-full bg-white shadow-2xs w-32 shrink-0">
                <button
                  type="button"
                  onClick={() => setQuantity(q => Math.max(1, q - 1))}
                  className="text-muted-foreground hover:text-primary transition-colors p-1"
                  aria-label="Decrease quantity"
                >
                  <Minus className="h-4 w-4" />
                </button>
                <span className="font-bold text-sm text-primary">{quantity}</span>
                <button
                  type="button"
                  onClick={() => setQuantity(q => q + 1)}
                  className="text-muted-foreground hover:text-primary transition-colors p-1"
                  aria-label="Increase quantity"
                >
                  <Plus className="h-4 w-4" />
                </button>
              </div>

              {/* Add to Bag Button */}
              <Button
                type="button"
                variant="outline"
                className="flex-1 h-11 rounded-full border-2 border-secondary text-secondary hover:bg-secondary hover:text-primary font-bold text-xs sm:text-sm uppercase tracking-wider shadow-2xs transition-colors whitespace-nowrap px-4"
                onClick={handleAddToCart}
              >
                Add to Bag
              </Button>

              {/* Buy Now Button */}
              <Button
                type="button"
                variant="secondary"
                className="flex-1 h-11 rounded-full font-bold text-xs sm:text-sm uppercase tracking-wider bg-secondary text-primary hover:bg-secondary/90 shadow-2xs gold-glow transition-all whitespace-nowrap px-4"
                onClick={handleBuyNow}
              >
                Buy Now
              </Button>
            </div>

            {/* Side-by-Side Feature Cards (Express Delivery & Farm Fresh Stems) */}
            <div className="grid grid-cols-2 gap-3 pt-1">
              <div className="p-3.5 rounded-2xl border border-border bg-white flex items-center gap-3">
                <Truck className="h-5 w-5 text-secondary shrink-0" />
                <div>
                  <h4 className="text-xs font-bold uppercase tracking-wider text-primary leading-tight">EXPRESS DELIVERY</h4>
                  <p className="text-xs text-muted-foreground leading-tight mt-0.5">Within 2 to 4 hours in Bangalore</p>
                </div>
              </div>
              <div className="p-3.5 rounded-2xl border border-border bg-white flex items-center gap-3">
                <Flower className="h-5 w-5 text-secondary shrink-0" />
                <div>
                  <h4 className="text-xs font-bold uppercase tracking-wider text-primary leading-tight">FARM FRESH STEMS</h4>
                  <p className="text-xs text-muted-foreground leading-tight mt-0.5">Guaranteed 7-day stay bloom life</p>
                </div>
              </div>
            </div>

            {/* Tabs Row */}
            <Tabs defaultValue="details" className="w-full pt-1.5">
              <TabsList className="w-full flex items-center justify-start gap-5 sm:gap-6 bg-transparent p-0 h-10 border-b border-border rounded-none pb-1">
                <TabsTrigger
                  value="details"
                  className="rounded-none border-b-2 border-transparent data-[state=active]:border-secondary data-[state=active]:text-primary font-bold text-xs uppercase tracking-wider bg-transparent p-0 pb-2 transition-colors"
                >
                  ARRANGEMENT DETAILS
                </TabsTrigger>
                <TabsTrigger
                  value="shipping"
                  className="rounded-none border-b-2 border-transparent data-[state=active]:border-secondary data-[state=active]:text-primary font-bold text-xs uppercase tracking-wider bg-transparent p-0 pb-2 transition-colors"
                >
                  DELIVERY INFO
                </TabsTrigger>
                <TabsTrigger
                  value="care"
                  className="rounded-none border-b-2 border-transparent data-[state=active]:border-secondary data-[state=active]:text-primary font-bold text-xs uppercase tracking-wider bg-transparent p-0 pb-2 transition-colors"
                >
                  FLOWER CARE GUIDE
                </TabsTrigger>
              </TabsList>
              <TabsContent value="details" className="pt-3 text-xs sm:text-sm text-muted-foreground leading-relaxed font-normal space-y-3">
                {(() => {
                  const rawFeatures = product.features;
                  let featuresList: string[] = [];
                  if (Array.isArray(rawFeatures)) {
                    featuresList = rawFeatures.map((f: any) => String(f).trim()).filter(Boolean);
                  } else if (typeof rawFeatures === 'string') {
                    try {
                      const parsed = JSON.parse(rawFeatures);
                      if (Array.isArray(parsed)) {
                        featuresList = parsed.map((f: any) => String(f).trim()).filter(Boolean);
                      }
                    } catch { }
                    if (featuresList.length === 0) {
                      featuresList = rawFeatures.split(/\r?\n/).map((f: string) => f.trim()).filter(Boolean);
                    }
                  }

                  return (
                    <>
                      {featuresList.length > 0 && (
                        <ul className="space-y-2 list-disc pl-5">
                          {featuresList.map((feature, idx) => (
                            <li key={idx}>{feature}</li>
                          ))}
                        </ul>
                      )}
                    </>
                  );
                })()}
              </TabsContent>
              <TabsContent value="shipping" className="pt-3 text-xs sm:text-sm text-muted-foreground leading-relaxed font-normal">
                {isFlowerOrBouquet ? (
                  <ul className="space-y-2 list-disc pl-5">
                    <li>Flowers are a product of nature and slight variations in color may occur.</li>
                    <li>Flowers are seasonal. The final product may vary slightly in shape, design, or bloom stage: bud, semi-bloom, or full bloom.</li>
                    <li>On the rare occasion a bloom is unavailable, we&apos;ll replace it with one of equal or higher value, maintaining the arrangement&apos;s style and color harmony.</li>
                    <li>Because flowers are perishable, we&apos;re able to make only one delivery attempt, and orders cannot be redirected to a different address once they&apos;re on their way.</li>
                    <li>This product is hand-delivered.</li>
                  </ul>
                ) : (
                  'Same-day express delivery across Bangalore for orders placed before 2:00 PM. Transported in specialized temperature-regulated vehicles.'
                )}
              </TabsContent>
              <TabsContent value="care" className="pt-3 text-xs sm:text-sm text-muted-foreground leading-relaxed font-normal">
                {isFlowerOrBouquet ? (
                  <ul className="space-y-2 list-disc pl-5">
                    <li>When your flowers arrive, simply cut the stems and put them in water.</li>
                    <li>Cut the stems at 45 degrees, about 1-2 inches from the bottom.</li>
                    <li>Remove the leaves below the waterline.</li>
                    <li>Check the water level every day and add more if necessary.</li>
                    <li>Don&apos;t place flowers in direct sunlight or near any other source of excessive heat.</li>
                    <li>All flowers benefit from a daily mist of water.</li>
                    <li>Enjoy your flowers!</li>
                  </ul>
                ) : (
                  'Follow the care instructions provided with your product.'
                )}
              </TabsContent>
            </Tabs>
          </div>
        </div>
      </div>

      {/* Full Screen Image Lightbox */}
      {isLightboxOpen && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4 sm:p-8 animate-in fade-in duration-200" onClick={() => setIsLightboxOpen(false)}>
          <button
            onClick={() => setIsLightboxOpen(false)}
            className="absolute top-4 right-4 sm:top-8 sm:right-8 p-2 bg-white/10 hover:bg-white/20 text-white rounded-full transition-colors z-[101]"
            aria-label="Close fullscreen image"
          >
            <X className="h-6 w-6 sm:h-8 sm:w-8" />
          </button>
          <img
            src={images[activeImage] || images[0]}
            alt={product.name}
            className="max-w-full max-h-full object-contain select-none"
            onClick={(e) => e.stopPropagation()}
          />
        </div>
      )}
    </div>
  );
}
