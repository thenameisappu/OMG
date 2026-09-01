import { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRight, Star, Truck, Calendar, Clock, Sparkles, ChevronLeft, ChevronRight, Heart, Gift, Award, Zap } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { formatINR } from '@/lib/currency';
import { getFeaturedProducts } from '@/db/api';
import { WishlistButton } from '@/components/common/WishlistButton';



export default function Home() {
  const [featuredProducts, setFeaturedProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentSlide, setCurrentSlide] = useState(0);

  useEffect(() => {
    async function loadFeatured() {
      try {
        setLoading(true);
        const data = await getFeaturedProducts();
        setFeaturedProducts(Array.isArray(data) ? data.slice(0, 10) : []);
      } catch (error) {
        console.error('Error fetching featured products:', error);
      } finally {
        setLoading(false);
      }
    }
    loadFeatured();
  }, []);

  const [visibleCount, setVisibleCount] = useState(() => window.innerWidth >= 1024 ? 3 : window.innerWidth >= 640 ? 2 : 1);

  // Update visible count on resize
  useEffect(() => {
    const onResize = () => {
      setVisibleCount(window.innerWidth >= 1024 ? 3 : window.innerWidth >= 640 ? 2 : 1);
      setCurrentSlide(0);
    };
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  const maxSlide = Math.max(0, featuredProducts.length - visibleCount);

  // Reset/clamp current slide if maxSlide changes
  useEffect(() => {
    if (currentSlide > maxSlide) {
      setCurrentSlide(maxSlide);
    }
  }, [maxSlide, currentSlide]);

  // Auto-advance carousel every 10 seconds
  useEffect(() => {
    if (featuredProducts.length === 0) return;
    const timer = setInterval(() => {
      setCurrentSlide((prev) => (prev >= maxSlide ? 0 : prev + 1));
    }, 10000);
    return () => clearInterval(timer);
  }, [featuredProducts.length, maxSlide]);

  const nextSlide = () => {
    setCurrentSlide((prev) => (prev >= maxSlide ? 0 : prev + 1));
  };

  const prevSlide = () => {
    setCurrentSlide((prev) => (prev <= 0 ? maxSlide : prev - 1));
  };

  return (
    <div className="flex flex-col bg-white">
      {/* Luxury Ticker Marquee Ribbon */}
      <div className="bg-primary text-secondary py-2.5 overflow-hidden border-b border-secondary/20 relative z-20 font-medium text-xs md:text-sm tracking-wider uppercase">
        <div className="animate-marquee whitespace-nowrap flex items-center gap-12">
          <span>✨ Express Same-Day Delivery across Bangalore</span>
          <span>•</span>
          <span>👑 Handcrafted Luxury Floral Arrangements</span>
          <span>•</span>
          <span>💍 Bespoke Surprise & Proposal Planning Services</span>
          <span>•</span>
          <span>🌸 100% Fresh Farm-Direct Blooms Guaranteed</span>
          <span>•</span>
          <span>✨ Express Same-Day Delivery across Bangalore</span>
          <span>•</span>
          <span>👑 Handcrafted Luxury Floral Arrangements</span>
          <span>•</span>
          <span>💍 Bespoke Surprise & Proposal Planning Services</span>
          <span>•</span>
          <span>🌸 100% Fresh Farm-Direct Blooms Guaranteed</span>
        </div>
      </div>

      {/* Hero Section with Live Canvas Particles */}
      <section className="relative h-[70vh] md:h-[90vh] w-full overflow-hidden animate-fade-in">
        <HeroParticles />

        <div className="absolute inset-0">
          <img
            src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_1820e6cd-672c-474f-a014-772dcd375172.jpg"
            alt="Luxury Flower Arrangement"
            className="h-full w-full object-cover transition-transform duration-10000 hover:scale-110"
          />
          <div className="absolute inset-0 bg-gradient-to-r from-black/80 via-black/40 to-transparent" />
        </div>

        <div className="container relative z-20 flex h-full items-center">
          <div className="max-w-2xl space-y-8 animate-slide-up">
            <div className="inline-flex items-center gap-2 rounded-full border border-secondary/40 bg-secondary/20 px-5 py-2 text-sm font-semibold text-secondary backdrop-blur-md shadow-lg">
              <Sparkles className="h-4 w-4 text-secondary animate-pulse" />
              <span>Bangalore's Premier Floral & Surprise House</span>
            </div>

            <h1 className="text-3xl sm:text-5xl md:text-7xl font-bold tracking-tight text-white leading-[1.1] drop-shadow-xl font-serif">
              Elevating the Art of <span className="text-secondary italic">Giving</span>
            </h1>

            <p className="text-base md:text-xl lg:text-2xl text-white/90 max-w-xl leading-relaxed drop-shadow-md font-light">
              Exquisite bouquets, curated hampers, and bespoke surprise services for life's most precious moments.
            </p>

            <div className="flex flex-col sm:flex-row flex-wrap gap-4 pt-4">
              <Link to="/products" className="w-full sm:w-auto">
                <Button size="lg" variant="secondary" className="w-full sm:w-auto h-14 px-8 text-base font-bold hover-lift shadow-xl rounded-full">
                  Shop Collection's
                  <ArrowRight className="ml-2 h-5 w-5" />
                </Button>
              </Link>
              <Link to="/surprise-services" className="w-full sm:w-auto">
                <Button size="lg" variant="outline" className="w-full sm:w-auto h-14 px-8 text-base font-bold text-white border-secondary/60 bg-black/30 backdrop-blur-md hover:bg-secondary hover:text-primary transition-all rounded-full">
                  Plan a Surprise
                </Button>
              </Link>
              <Link to="/about" className="w-full sm:w-auto">
                <Button size="lg" variant="outline" className="w-full sm:w-auto h-14 px-8 text-base font-bold text-white border-secondary/60 bg-black/30 backdrop-blur-md hover:bg-secondary hover:text-primary transition-all rounded-full">
                  About Us
                </Button>
              </Link>
            </div>

            {/* Micro Feature Badges */}
            <div className="flex items-center gap-6 pt-4 text-white/80 text-xs md:text-sm font-medium">
              <span className="flex items-center gap-2">
                <Zap className="h-4 w-4 text-secondary" /> 2-Hour Express Delivery
              </span>
              <span className="flex items-center gap-2">
                <Award className="h-4 w-4 text-secondary" /> Master Florist Crafted
              </span>
            </div>
          </div>
        </div>
      </section>

      {/* Services Stats Bar */}
      <section className="bg-primary py-16 border-y border-secondary/20 relative z-20">
        <div className="container grid grid-cols-2 md:grid-cols-4 gap-8 text-center text-white">
          <div className="space-y-2 animate-scale-in p-4 rounded-xl hover:bg-white/5 transition-colors">
            <Truck className="h-9 w-9 mx-auto text-secondary" />
            <h3 className="text-base font-bold">Same-Day Express</h3>
            <p className="text-xs text-white/70">Bangalore & Surrounding Areas</p>
          </div>
          <div className="space-y-2 animate-scale-in p-4 rounded-xl hover:bg-white/5 transition-colors" style={{ animationDelay: '0.1s' }}>
            <Calendar className="h-9 w-9 mx-auto text-secondary" />
            <h3 className="text-base font-bold">Scheduled Delivery</h3>
            <p className="text-xs text-white/70">Midnight & Custom Time Slots</p>
          </div>
          <div className="space-y-2 animate-scale-in p-4 rounded-xl hover:bg-white/5 transition-colors" style={{ animationDelay: '0.2s' }}>
            <Sparkles className="h-9 w-9 mx-auto text-secondary" />
            <h3 className="text-base font-bold">Bespoke Surprises</h3>
            <p className="text-xs text-white/70">Secret On-Site Coordination</p>
          </div>
          <div className="space-y-2 animate-scale-in p-4 rounded-xl hover:bg-white/5 transition-colors" style={{ animationDelay: '0.3s' }}>
            <Clock className="h-9 w-9 mx-auto text-secondary" />
            <h3 className="text-base font-bold">24/7 Specialist Care</h3>
            <p className="text-xs text-white/70">Dedicated Concierge Support</p>
          </div>
        </div>
      </section>



      {/* Categories Section */}
      <section className="py-24 bg-white">
        <div className="container">
          <div className="flex flex-col md:flex-row justify-between items-start md:items-end mb-16 gap-4">
            <div className="space-y-3">
              <span className="text-secondary font-bold text-xs tracking-widest">Curated Collections</span>
              <h2 className="text-4xl md:text-5xl font-bold tracking-tight text-primary font-serif">Explore Our Collections</h2>
              <p className="text-lg text-muted-foreground">Handcrafted designs for life's unforgettable chapters</p>
            </div>
            <Link to="/products" className="text-secondary text-lg font-bold flex items-center gap-2 hover:underline hover:gap-3 transition-all">
              View all <ArrowRight className="h-5 w-5" />
            </Link>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 md:gap-8">
            <CategoryCard
              title="Oh My Bloom's"
              image="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_8fb5dcf8-22bd-4fbd-98ba-1611bfcdcc4d.jpg"
              link="/products?category=flower-arrangements"
              subtitle="Fresh Floral Bouquets & Luxury Stems"
            />
            <CategoryCard
              title="Oh My Love's"
              image="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_3556e18d-69b0-4c22-93c1-29efba584217.jpg"
              link="/products?category=gift-hampers"
              subtitle="Curated Hampers & Gourmet Treats"
            />
            <CategoryCard
              title="Oh My Moment's"
              image="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_6bbe1cd4-2103-4b1e-b55e-83ffbca65dd2.jpg"
              link="/products?category=occasions"
              subtitle="Celebration Bundles & Occasion Styling"
            />
            <CategoryCard
              title="Oh My Signature's"
              image="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_03fde315-7935-4968-98e6-d7b417bd3057.jpg"
              link="/products?category=signature-collection"
              subtitle="Rare Exotic Blooms & Bespoke Vessels"
            />
          </div>
        </div>
      </section>

      {/* Featured Products Carousel — 3 per row, slides 1 at a time */}
      <section className="py-16 md:py-24 bg-muted/30 border-y border-border">
        <div className="container text-center mb-10 md:mb-16 space-y-4">
          <span className="text-secondary font-bold text-xs uppercase tracking-widest">Masterpiece Selection</span>
          <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold tracking-tight text-primary font-serif">Featured Arrangements</h2>
          <div className="h-1 w-24 bg-secondary mx-auto rounded-full" />
          <p className="text-base md:text-lg text-muted-foreground max-w-2xl mx-auto leading-relaxed">
            Our florists' top seasonal picks, designed to bring immediate joy and awe.
          </p>
        </div>

        <div className="container relative">
          {/* Carousel Track */}
          <div className="overflow-hidden">
            {loading ? (
              <div className="flex items-center justify-center min-h-[360px]">
                <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-secondary"></div>
              </div>
            ) : featuredProducts.length === 0 ? (
              <p className="text-center text-muted-foreground italic text-lg py-24">No featured arrangements available at this time.</p>
            ) : (
              <div
                className="flex transition-transform duration-700 ease-in-out"
                style={{
                  transform: `translateX(calc(-${currentSlide} * (100% / ${visibleCount})))`,
                }}
              >
                {featuredProducts.map((product) => (
                  <div
                    key={product.id}
                    style={{ width: `${100 / visibleCount}%`, flexShrink: 0 }}
                    className="px-2 md:px-3"
                  >
                    <ProductCard product={product} />
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Navigation Arrows */}
          <button
            onClick={prevSlide}
            disabled={featuredProducts.length === 0}
            className="absolute -left-4 md:-left-6 top-1/2 -translate-y-1/2 bg-white hover:bg-secondary hover:text-primary p-2.5 md:p-3 rounded-full shadow-lg transition-all hover:scale-110 z-10 border border-border disabled:opacity-30"
            aria-label="Previous slide"
          >
            <ChevronLeft className="h-5 w-5 md:h-6 md:w-6 text-primary" />
          </button>
          <button
            onClick={nextSlide}
            disabled={featuredProducts.length === 0}
            className="absolute -right-4 md:-right-6 top-1/2 -translate-y-1/2 bg-white hover:bg-secondary hover:text-primary p-2.5 md:p-3 rounded-full shadow-lg transition-all hover:scale-110 z-10 border border-border disabled:opacity-30"
            aria-label="Next slide"
          >
            <ChevronRight className="h-5 w-5 md:h-6 md:w-6 text-primary" />
          </button>

          {/* Dot Indicators — one per navigable position */}
          <div className="flex justify-center gap-2 mt-8">
            {Array.from({ length: maxSlide + 1 }).map((_, index) => (
              <button
                key={index}
                onClick={() => setCurrentSlide(index)}
                className={cn(
                  "h-2.5 rounded-full transition-all duration-300",
                  currentSlide === index ? "bg-secondary w-8" : "bg-muted-foreground/30 w-2.5 hover:bg-secondary/50"
                )}
                aria-label={`Go to position ${index + 1}`}
              />
            ))}
          </div>

          {/* 10-second auto-advance progress bar */}
          {featuredProducts.length > 0 && (
            <div className="mt-4 mx-auto w-32 h-1 bg-muted rounded-full overflow-hidden">
              <div
                key={currentSlide}
                className="h-full bg-secondary rounded-full"
                style={{
                  animation: 'progress-fill 10s linear forwards'
                }}
              />
            </div>
          )}
        </div>
      </section>

      {/* Gallery Section */}
      <section className="py-24 bg-white">
        <div className="container text-center mb-16 space-y-4">
          <span className="text-secondary font-bold text-xs uppercase tracking-widest">Visual Diary</span>
          <h2 className="text-4xl md:text-5xl font-bold tracking-tight text-primary font-serif">Our World of Blooms</h2>
          <div className="h-1 w-24 bg-secondary mx-auto rounded-full" />
          <p className="text-lg text-muted-foreground max-w-2xl mx-auto leading-relaxed">
            A glimpse into our handcrafted creations, bespoke surprises, and floral setups.
          </p>
        </div>

        <div className="container">
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 md:gap-6">
            <div className="md:col-span-2 md:row-span-2 overflow-hidden rounded-2xl luxury-shadow hover-lift group relative">
              <img
                src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_66ade087-5eac-4fb3-83db-431e8df6b554.jpg"
                alt="Luxury Flower Arrangement"
                className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity p-8 flex items-end">
                <span className="text-white font-bold text-xl">Grand Rose Archways</span>
              </div>
            </div>
            <div className="overflow-hidden rounded-2xl luxury-shadow hover-lift group relative">
              <img
                src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_c023c54c-4d4a-425e-aee3-ef5a6118cf40.jpg"
                alt="Premium Gift Hamper"
                className="w-full h-64 object-cover transition-transform duration-700 group-hover:scale-110"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity p-6 flex items-end">
                <span className="text-white font-bold text-base">Curated Gift Boxes</span>
              </div>
            </div>
            <div className="overflow-hidden rounded-2xl luxury-shadow hover-lift group relative">
              <img
                src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_72323cbd-096f-46c9-91cf-bd7a3ac90808.jpg"
                alt="Romantic Rose Arrangement"
                className="w-full h-64 object-cover transition-transform duration-700 group-hover:scale-110"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity p-6 flex items-end">
                <span className="text-white font-bold text-base">Velvet Crimson Roses</span>
              </div>
            </div>
            <div className="overflow-hidden rounded-2xl luxury-shadow hover-lift group relative">
              <img
                src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_965d1a2d-371b-4b3f-b293-31d0d3bbfe15.jpg"
                alt="Birthday Celebration Flowers"
                className="w-full h-64 object-cover transition-transform duration-700 group-hover:scale-110"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity p-6 flex items-end">
                <span className="text-white font-bold text-base">Birthday Bloom Bundles</span>
              </div>
            </div>
            <div className="md:col-span-2 md:row-span-2 overflow-hidden rounded-2xl luxury-shadow hover-lift group relative">
              <img
                src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_14558096-74be-4c1a-a8a2-e0334e6050d9.jpg"
                alt="Luxury Floral Event Decoration"
                className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity p-8 flex items-end">
                <span className="text-white font-bold text-xl">Event & Venue Styling</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-28 bg-primary text-white overflow-hidden relative">
        <div className="absolute top-0 right-0 w-1/3 h-full opacity-10 pointer-events-none">
          <FlowerPattern />
        </div>
        <div className="container text-center relative z-10 space-y-8 max-w-3xl">
          <span className="text-secondary font-bold text-xs uppercase tracking-widest">Bespoke Experience</span>
          <h2 className="text-4xl md:text-6xl font-bold font-serif">Planning a Special Surprise?</h2>
          <p className="text-lg md:text-xl text-white/80 leading-relaxed font-light">
            Let our event architects handle every secret detail. From romantic proposal setups to midnight acoustic serenades, we make it magical.
          </p>
          <Link to="/surprise-services">
            <Button size="lg" variant="secondary" className="px-12 h-16 text-lg font-bold hover-lift rounded-full shadow-2xl mt-4">
              Explore Surprise Packages →
            </Button>
          </Link>
        </div>
      </section>
    </div>
  );
}

function HeroParticles() {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    let animationFrameId: number;
    let width = (canvas.width = canvas.parentElement?.clientWidth || window.innerWidth);
    let height = (canvas.height = canvas.parentElement?.clientHeight || window.innerHeight);

    const handleResize = () => {
      if (!canvas) return;
      width = canvas.width = canvas.parentElement?.clientWidth || window.innerWidth;
      height = canvas.height = canvas.parentElement?.clientHeight || window.innerHeight;
    };
    window.addEventListener('resize', handleResize);

    const particles: Array<{
      x: number;
      y: number;
      radius: number;
      color: string;
      vx: number;
      vy: number;
      alpha: number;
      alphaChange: number;
    }> = [];

    const colors = ['#D4AF37', '#FFF0BD', '#F3E5AB', '#E6C200', '#FFFFFF'];

    for (let i = 0; i < 45; i++) {
      particles.push({
        x: Math.random() * width,
        y: Math.random() * height,
        radius: Math.random() * 2.5 + 1,
        color: colors[Math.floor(Math.random() * colors.length)],
        vx: (Math.random() - 0.5) * 0.4,
        vy: -Math.random() * 0.8 - 0.2,
        alpha: Math.random(),
        alphaChange: (Math.random() * 0.02 + 0.005) * (Math.random() > 0.5 ? 1 : -1)
      });
    }

    const render = () => {
      ctx.clearRect(0, 0, width, height);

      particles.forEach((p) => {
        p.x += p.vx;
        p.y += p.vy;
        p.alpha += p.alphaChange;

        if (p.alpha <= 0.1 || p.alpha >= 0.9) p.alphaChange *= -1;
        if (p.y < 0) {
          p.y = height + 10;
          p.x = Math.random() * width;
        }
        if (p.x < 0 || p.x > width) p.vx *= -1;

        ctx.save();
        ctx.globalAlpha = p.alpha;
        ctx.fillStyle = p.color;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
        ctx.shadowBlur = 8;
        ctx.shadowColor = p.color;
        ctx.fill();
        ctx.restore();
      });

      animationFrameId = requestAnimationFrame(render);
    };

    render();

    return () => {
      window.removeEventListener('resize', handleResize);
      cancelAnimationFrame(animationFrameId);
    };
  }, []);

  return <canvas ref={canvasRef} className="absolute inset-0 pointer-events-none z-10" />;
}

function CategoryCard({ title, subtitle, image, link, className }: { title: string; subtitle?: string; image: string; link: string; className?: string }) {
  return (
    <Link to={link} className={cn("group relative overflow-hidden rounded-2xl aspect-[4/3] hover-lift luxury-shadow", className)}>
      <img
        src={image}
        alt={title}
        className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110"
      />
      <div className="absolute inset-0 bg-gradient-to-t from-primary/90 via-primary/30 to-transparent" />
      <div className="absolute bottom-0 left-0 right-0 p-8">
        {subtitle && <p className="text-secondary/90 text-xs font-bold uppercase tracking-widest mb-1">{subtitle}</p>}
        <h3 className="text-3xl font-bold font-serif text-white mb-2 group-hover:text-secondary transition-colors">{title}</h3>
        <div className="flex items-center text-white/90 group-hover:text-secondary transition-colors font-semibold text-sm">
          <span>Explore Collection</span>
          <ArrowRight className="h-4 w-4 ml-2 transform group-hover:translate-x-2 transition-transform" />
        </div>
      </div>
    </Link>
  );
}

function ProductCard({ product }: { product: any }) {
  return (
    <div
      className="group relative bg-white border border-border/60 luxury-shadow hover-lift rounded-2xl overflow-hidden flex flex-col h-full transition-all duration-300"
      style={{ containerType: 'inline-size', containerName: 'productcard' }}
    >
      <Link to={`/product/${product.slug}`} className="relative block aspect-square overflow-hidden bg-muted/10">
        <img
          src={product.image}
          alt={product.name}
          className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110"
        />
        {/* Best Seller Badge */}
        {(product.is_bestseller === true || Number(product.is_bestseller) === 1) && (
          <div className="absolute top-2.5 left-2.5 z-20">
            <span className="inline-flex items-center gap-1 bg-amber-500/90 backdrop-blur-md text-white text-[10px] uppercase font-bold tracking-wider px-2.5 py-1 rounded-full shadow-md">
              <Sparkles className="h-3 w-3" /> Best Seller
            </span>
          </div>
        )}
        {/* Wishlist Button */}
        <WishlistButton
          product={product}
          size="md"
          className="absolute top-2.5 right-2.5 z-20"
        />
      </Link>
      {/* Body scales via CSS container queries (product-card-* classes in index.css) */}
      <div className="product-card-body flex flex-col flex-1">
        <div className="flex justify-between items-start mb-2">
          <Link to={`/product/${product.slug}`} className="hover:text-secondary transition-colors flex-1 min-w-0 pr-2">
            <h3 className="product-card-title font-bold font-serif text-primary line-clamp-2 leading-snug">{product.name}</h3>
          </Link>
          <div className="flex items-center text-amber-500 flex-shrink-0">
            <Star className="h-3.5 w-3.5 fill-current" />
            <span className="text-xs font-bold ml-1 text-muted-foreground">4.9</span>
          </div>
        </div>
        <p className="text-xs text-muted-foreground line-clamp-2 mb-4 flex-1 leading-relaxed">
          {product.description}
        </p>
        <div className="flex items-center justify-between mt-auto pt-3 border-t border-border/50">
          <span className="product-card-price font-bold text-primary">{formatINR(product.price)}</span>
          <Link to={`/product/${product.slug}`}>
            <Button variant="outline" className="border-secondary text-secondary hover:bg-secondary hover:text-primary rounded-full px-3 md:px-5 text-xs font-bold transition-all">
              View
            </Button>
          </Link>
        </div>
      </div>
    </div>
  );
}

function FlowerPattern() {
  return (
    <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" className="w-full h-full fill-current">
      <path d="M44.7,-76.4C58.1,-69.2,69.2,-58.1,76.4,-44.7C83.7,-31.3,87,-15.7,86.6,-0.2C86.3,15.2,82.2,30.4,74.1,43.5C65.9,56.6,53.7,67.6,39.9,74.5C26,81.4,10.5,84.1,-4.7,82.2C-19.9,80.4,-34.8,74.1,-47.9,65.1C-61.1,56.1,-72.5,44.5,-79.1,30.8C-85.7,17.1,-87.5,1.2,-84.9,-13.7C-82.3,-28.7,-75.4,-42.8,-64.7,-52.3C-53.9,-61.8,-39.3,-66.7,-25.9,-73.9C-12.5,-81.1,-0.3,-90.6,12.7,-88.4C25.7,-86.2,31.3,-83.6,44.7,-76.4Z" transform="translate(100 100)" />
    </svg>
  );
}
