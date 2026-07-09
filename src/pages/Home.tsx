import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRight, Star, Truck, Calendar, Clock, Sparkles, ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { formatINR } from '@/lib/currency';
import { getFeaturedProducts } from '@/db/api';

export default function Home() {
  const [featuredProducts, setFeaturedProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentSlide, setCurrentSlide] = useState(0);

  useEffect(() => {
    async function loadFeatured() {
      try {
        setLoading(true);
        const data = await getFeaturedProducts();
        setFeaturedProducts(data);
      } catch (error) {
        console.error('Error fetching featured products:', error);
      } finally {
        setLoading(false);
      }
    }
    loadFeatured();
  }, []);

  // Auto-advance carousel every 5 seconds
  useEffect(() => {
    if (featuredProducts.length === 0) return;
    const timer = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % featuredProducts.length);
    }, 5000);

    return () => clearInterval(timer);
  }, [featuredProducts.length]);

  const nextSlide = () => {
    if (featuredProducts.length === 0) return;
    setCurrentSlide((prev) => (prev + 1) % featuredProducts.length);
  };

  const prevSlide = () => {
    if (featuredProducts.length === 0) return;
    setCurrentSlide((prev) => (prev - 1 + featuredProducts.length) % featuredProducts.length);
  };

  return (
    <div className="flex flex-col bg-white">
      {/* Hero Section */}
      <section className="relative h-[90vh] w-full overflow-hidden animate-fade-in">
        <div className="absolute inset-0">
          <img
            src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_1820e6cd-672c-474f-a014-772dcd375172.jpg"
            alt="Luxury Flower Arrangement"
            className="h-full w-full object-cover transition-transform duration-10000 hover:scale-110"
          />
          <div className="absolute inset-0 bg-white/10" />
        </div>

        <div className="container relative flex h-full items-center">
          <div className="max-w-2xl space-y-10 animate-slide-up">
            <div className="inline-flex items-center gap-2 rounded-full border border-secondary/30 bg-secondary/10 px-5 py-2 text-base font-medium text-secondary backdrop-blur-sm">
              <Sparkles className="h-5 w-5" />
              <span>Premium Floral Service</span>
            </div>
            <h1 className="text-6xl md:text-7xl font-bold tracking-tight text-white leading-[1.1] drop-shadow-lg">
              Elevating the Art of <span className="text-secondary">Giving</span>
            </h1>
            <p className="text-xl md:text-2xl text-white/95 max-w-lg leading-relaxed drop-shadow-md">
              Exquisite bouquets, curated hampers, and bespoke surprise services for life's most precious moments.
            </p>
            <div className="flex flex-wrap gap-6 pt-4">
              <Link to="/products">
                <Button size="lg" variant="secondary" className="h-16 px-10 text-lg font-semibold hover-lift">
                  Shop Collection's
                </Button>
              </Link>
              <Link to="/surprise-services">
                <Button size="lg" variant="outline" className="h-16 px-10 text-lg font-semibold text-white border-white/30 hover:bg-white/10">
                  Plan a Surprise
                </Button>
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Services Stats */}
      <section className="bg-primary py-16 border-y border-secondary/20">
        <div className="container grid grid-cols-2 md:grid-cols-3 gap-12 text-center text-white">
          <div className="space-y-3 animate-scale-in">
            <Truck className="h-10 w-10 mx-auto text-secondary" />
            <h3 className="text-lg font-semibold">Same-Day Delivery</h3>
            <p className="text-sm text-white/70">Bangalore, Karnataka</p>
          </div>
          <div className="space-y-3 animate-scale-in" style={{ animationDelay: '0.1s' }}>
            <Calendar className="h-10 w-10 mx-auto text-secondary" />
            <h3 className="text-lg font-semibold">Scheduled Slots</h3>
            <p className="text-sm text-white/70">Choose Your Perfect Time</p>
          </div>
          <div className="space-y-3 animate-scale-in" style={{ animationDelay: '0.2s' }}>
            <Clock className="h-10 w-10 mx-auto text-secondary" />
            <h3 className="text-lg font-semibold">24/7 Support</h3>
            <p className="text-sm text-white/70">Expert Floral Support</p>
          </div>
        </div>
      </section>

      {/* Categories Section */}
      <section className="py-28 bg-white">
        <div className="container">
          <div className="flex justify-between items-end mb-16">
            <div className="space-y-3">
              <h2 className="text-4xl md:text-5xl font-bold tracking-tight text-primary">Explore Our Collections</h2>
              <p className="text-lg text-muted-foreground">Handcrafted for every sentiment</p>
            </div>
            <Link to="/products" className="text-secondary text-lg font-semibold flex items-center gap-2 hover:underline hover:gap-3 transition-all">
              View All <ArrowRight className="h-5 w-5" />
            </Link>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <CategoryCard
              title="Oh My Bloom's"
              image="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_8fb5dcf8-22bd-4fbd-98ba-1611bfcdcc4d.jpg"
              link="/products?category=flower-arrangements"
            />
            <CategoryCard
              title="Oh My Love's"
              image="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_3556e18d-69b0-4c22-93c1-29efba584217.jpg"
              link="/products?category=gift-hampers"
            />
            <CategoryCard
              title="Oh My Celebration's"
              image="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_6bbe1cd4-2103-4b1e-b55e-83ffbca65dd2.jpg"
              link="/products?category=occasions"
            />
            <CategoryCard
              title="Oh My Signature's"
              image="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_03fde315-7935-4968-98e6-d7b417bd3057.jpg"
              link="/products?category=signature-collection"
            />
          </div>
        </div>
      </section>

      {/* Featured Products Carousel */}
      <section className="py-28 bg-muted/20">
        <div className="container text-center mb-20 space-y-4">
          <h2 className="text-5xl md:text-6xl font-bold tracking-tight text-primary">Featured Arrangements</h2>
          <div className="h-1 w-32 bg-secondary mx-auto" />
          <p className="text-xl text-muted-foreground max-w-2xl mx-auto leading-relaxed">
            Our master florists' pick of the season. Each arrangement is a unique masterpiece.
          </p>
        </div>

        <div className="container relative">
          {/* Carousel Container */}
          <div className="relative overflow-hidden min-h-[300px] flex items-center justify-center">
            {loading ? (
              <div className="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-secondary"></div>
            ) : featuredProducts.length === 0 ? (
              <p className="text-muted-foreground italic text-lg">No featured arrangements available at this time.</p>
            ) : (
              <div
                className="flex transition-transform duration-700 ease-in-out w-full"
                style={{ transform: `translateX(-${currentSlide * 100}%)` }}
              >
                {featuredProducts.map((product) => (
                  <div key={product.id} className="w-full flex-shrink-0 px-4">
                    <div className="max-w-md mx-auto">
                      <ProductCard product={product} />
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Navigation Arrows */}
          <button
            onClick={prevSlide}
            className="absolute left-4 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-white p-3 rounded-full shadow-lg transition-all hover:scale-110 z-10"
            aria-label="Previous slide"
          >
            <ChevronLeft className="h-6 w-6 text-primary" />
          </button>
          <button
            onClick={nextSlide}
            className="absolute right-4 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-white p-3 rounded-full shadow-lg transition-all hover:scale-110 z-10"
            aria-label="Next slide"
          >
            <ChevronRight className="h-6 w-6 text-primary" />
          </button>

          {/* Dots Indicator */}
          <div className="flex justify-center gap-2 mt-8">
            {featuredProducts.map((_, index) => (
              <button
                key={index}
                onClick={() => setCurrentSlide(index)}
                className={cn(
                  "h-3 w-3 rounded-full transition-all",
                  currentSlide === index ? "bg-secondary w-8" : "bg-muted-foreground/30"
                )}
                aria-label={`Go to slide ${index + 1}`}
              />
            ))}
          </div>
        </div>
      </section>

      {/* Gallery Section */}
      <section className="py-28 bg-white">
        <div className="container text-center mb-20 space-y-4">
          <h2 className="text-5xl md:text-6xl font-bold tracking-tight text-primary">Our Gallery</h2>
          <div className="h-1 w-32 bg-secondary mx-auto" />
          <p className="text-xl text-muted-foreground max-w-2xl mx-auto leading-relaxed">
            A glimpse into our world of luxury florals and unforgettable moments
          </p>
        </div>

        <div className="container">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="md:col-span-2 md:row-span-2 overflow-hidden rounded-2xl luxury-shadow hover-lift">
              <img
                src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_66ade087-5eac-4fb3-83db-431e8df6b554.jpg"
                alt="Luxury Flower Arrangement"
                className="w-full h-full object-cover transition-transform duration-700 hover:scale-110"
              />
            </div>
            <div className="overflow-hidden rounded-2xl luxury-shadow hover-lift">
              <img
                src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_c023c54c-4d4a-425e-aee3-ef5a6118cf40.jpg"
                alt="Premium Gift Hamper"
                className="w-full h-64 object-cover transition-transform duration-700 hover:scale-110"
              />
            </div>
            <div className="overflow-hidden rounded-2xl luxury-shadow hover-lift">
              <img
                src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_72323cbd-096f-46c9-91cf-bd7a3ac90808.jpg"
                alt="Romantic Rose Arrangement"
                className="w-full h-64 object-cover transition-transform duration-700 hover:scale-110"
              />
            </div>
            <div className="overflow-hidden rounded-2xl luxury-shadow hover-lift">
              <img
                src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_965d1a2d-371b-4b3f-b293-31d0d3bbfe15.jpg"
                alt="Birthday Celebration Flowers"
                className="w-full h-64 object-cover transition-transform duration-700 hover:scale-110"
              />
            </div>
            <div className="md:col-span-2 md:row-span-2 overflow-hidden rounded-2xl luxury-shadow hover-lift">
              <img
                src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_14558096-74be-4c1a-a8a2-e0334e6050d9.jpg"
                alt="Luxury Floral Event Decoration"
                className="w-full h-full object-cover transition-transform duration-700 hover:scale-110"
              />
            </div>
            <div className="overflow-hidden rounded-2xl luxury-shadow hover-lift">
              <img
                src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_71dc3f4f-be8e-455d-9c1a-cdfb7d267edc.jpg"
                alt="Elegant Orchid Arrangement"
                className="w-full h-64 object-cover transition-transform duration-700 hover:scale-110"
              />
            </div>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-28 bg-primary text-white overflow-hidden relative">
        <div className="absolute top-0 right-0 w-1/3 h-full opacity-10 pointer-events-none">
          <FlowerPattern />
        </div>
        <div className="container text-center relative z-10 space-y-8">
          <h2 className="text-5xl md:text-6xl font-bold">Planning a Special Moment?</h2>
          <p className="text-xl md:text-2xl text-white/80 max-w-3xl mx-auto leading-relaxed">
            Let our event specialists handle the details. From proposal surprises to gala floral decor, we make it unforgettable.
          </p>
          <Link to="/surprise-services">
            <Button size="lg" variant="secondary" className="px-12 h-16 text-lg font-semibold hover-lift mt-6">
              Speak to a Consultant
            </Button>
          </Link>
        </div>
      </section>
    </div>
  );
}

function CategoryCard({ title, image, link, className }: { title: string; image: string; link: string; className?: string }) {
  return (
    <Link to={link} className={cn("group relative overflow-hidden rounded-xl aspect-[4/3] hover-lift luxury-shadow", className)}>
      <img
        src={image}
        alt={title}
        className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110"
      />
      <div className="absolute inset-0 bg-gradient-to-t from-primary/80 via-primary/30 to-transparent" />
      <div className="absolute bottom-0 left-0 right-0 p-8">
        <h3 className="text-3xl font-bold text-white mb-2 group-hover:text-secondary transition-colors">{title}</h3>
        <div className="flex items-center text-white/90 group-hover:text-secondary transition-colors">
          <span className="text-base font-medium">Explore Collection</span>
          <ArrowRight className="h-5 w-5 ml-2 transform group-hover:translate-x-2 transition-transform" />
        </div>
      </div>
    </Link>
  );
}

function ProductCard({ product }: { product: any }) {
  return (
    <div className="group relative bg-white luxury-shadow hover-lift rounded-xl overflow-hidden flex flex-col h-full transition-all duration-300">
      <Link to={`/product/${product.slug}`} className="relative block aspect-square overflow-hidden">
        <img
          src={product.image}
          alt={product.name}
          className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110"
        />
        {product.is_featured && (
          <div className="absolute top-4 right-4 bg-secondary text-white px-4 py-1.5 text-xs font-bold uppercase tracking-widest rounded-full shadow-lg">
            Featured
          </div>
        )}
      </Link>
      <div className="p-8 flex flex-col flex-1">
        <div className="flex justify-between items-start mb-3">
          <Link to={`/product/${product.slug}`} className="hover:text-secondary transition-colors">
            <h3 className="text-2xl font-bold text-primary">{product.name}</h3>
          </Link>
          <div className="flex items-center text-yellow-500">
            <Star className="h-5 w-5 fill-current" />
            <span className="text-base font-bold ml-1 text-muted-foreground">4.9</span>
          </div>
        </div>
        <p className="text-base text-muted-foreground line-clamp-2 mb-8 flex-1 leading-relaxed">
          {product.description}
        </p>
        <div className="flex items-center justify-between mt-auto">
          <span className="text-3xl font-bold text-primary">{formatINR(product.price)}</span>
          <Button variant="outline" className="border-secondary text-secondary hover:bg-secondary hover:text-white rounded-full px-8 py-6 text-base font-semibold transition-all">
            View Details
          </Button>
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
