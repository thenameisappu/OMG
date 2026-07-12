import { formatINR } from "@/lib/currency";
import { useState, useMemo, useEffect } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import { Star, ChevronRight, SlidersHorizontal, MessageSquare, Users, CalendarDays } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { useToast } from '@/hooks/use-toast';
import { useAuth } from '@/contexts/AuthContext';
import { getProducts } from '@/db/api';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';

const categories = [
  { name: 'All', slug: 'all' },
  { name: "Oh My Bloom's", slug: 'flower-arrangements' },
  { name: "Oh My Love's", slug: 'gift-hampers' },
  { name: "Oh My Signature's", slug: 'signature-collection' },
  { name: "Oh My Celebration's", slug: 'occasions' },
  { name: "Oh My Customisation's", slug: 'custom-orders' },
];

export default function Products() {
  const { user } = useAuth();
  const { toast } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const currentCategory = searchParams.get('category') || 'all';
  const [sortBy, setSortBy] = useState('featured');
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadProducts() {
      try {
        setLoading(true);
        const data = await getProducts(currentCategory);
        setProducts(data);
      } catch (error) {
        console.error('Error loading products:', error);
        toast({
          title: "Error",
          description: "Failed to load products from database.",
          variant: "destructive",
        });
      } finally {
        setLoading(false);
      }
    }
    loadProducts();
  }, [currentCategory, toast]);

  const [custForm, setCustForm] = useState({
    name: user?.name || '',
    email: user?.email || '',
    contactNo: user?.phone || '',
    eventType: 'Custom Floral Arrangement',
    serviceName: '',
    address: '',
    city: '',
    message: ''
  });

  // Auto-fill when user data is available
  useEffect(() => {
    if (user) {
      setCustForm(prev => ({
        ...prev,
        name: prev.name || user.name || '',
        email: prev.email || user.email || '',
        contactNo: prev.contactNo || user.phone || '',
      }));
    }
  }, [user]);
  const [custErrors, setCustErrors] = useState<Record<string, string>>({});
  const [custSubmitting, setCustSubmitting] = useState(false);

  const handleCustChange = (field: string, value: string) => setCustForm(prev => ({ ...prev, [field]: value }));

  const handleCustSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const errs: Record<string, string> = {};
    if (!custForm.name.trim()) errs.name = 'Name is required';
    if (!custForm.email.trim()) errs.email = 'Email is required';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(custForm.email)) errs.email = 'Invalid email';
    if (!custForm.contactNo.trim()) errs.contactNo = 'Contact number is required';
    if (!custForm.message.trim()) errs.message = 'Please describe your customisation';
    if (custForm.eventType === 'Other' && !custForm.serviceName.trim()) errs.serviceName = 'Please specify the service';
    setCustErrors(errs);
    if (Object.keys(errs).length > 0) return;
    setCustSubmitting(true);
    try {
      const res = await fetch('/backend/customisations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(custForm),
      });
      const text = await res.text();
      let data: any = {};
      try { data = JSON.parse(text); } catch { /* non-JSON */ }
      if (!res.ok) throw new Error(data.error || `Server error (${res.status})`);
      toast({ title: 'Request Submitted!', description: 'Our team will contact you within 24 hours.' });
      setCustForm({
        name: user?.name || '',
        email: user?.email || '',
        contactNo: user?.phone || '',
        eventType: 'Custom Floral Arrangement',
        serviceName: '',
        address: '',
        city: '',
        message: ''
      });
      setCustErrors({});
    } catch (err: any) {
      toast({ title: 'Submission Failed', description: err.message || 'Please try again.', variant: 'destructive' });
    } finally {
      setCustSubmitting(false);
    }
  };

  const filteredProducts = useMemo(() => {
    let result = [...products];

    if (sortBy === 'price-low') {
      result.sort((a, b) => Number(a.price) - Number(b.price));
    } else if (sortBy === 'price-high') {
      result.sort((a, b) => Number(b.price) - Number(a.price));
    }

    return result;
  }, [products, sortBy]);

  const categoryName = categories.find(c => c.slug === currentCategory)?.name || 'All Products';

  // Category banner configurations
  const categoryBanners: Record<string, { image: string; title: string; description: string }> = {
    'flower-arrangements': {
      image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_39a052aa-4054-4588-bf17-098a96cfcd2b.jpg',
      title: "Oh My Bloom's",
      description: 'Exquisite floral arrangements crafted by master florists, bringing nature\'s beauty to your special moments.'
    },
    'gift-hampers': {
      image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_032564d4-e9a3-41be-b09c-95d6ef79e302.jpg',
      title: "Oh My Love's",
      description: 'Curated luxury hampers filled with premium selections, perfect for expressing your heartfelt sentiments.'
    },
    'signature-collection': {
      image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_6d766290-f70a-4d60-98fe-4aeb18ce0016.jpg',
      title: "Oh My Signature's",
      description: 'Our exclusive signature collection featuring unique designs and rare blooms for the most discerning tastes.'
    },
    'occasions': {
      image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_aa54fb7e-093c-4976-a8af-1fb7321d319e.jpg',
      title: "Oh My Celebration's",
      description: 'Celebrate life\'s precious moments with our specially designed arrangements for every occasion.'
    },
    'custom-orders': {
      image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_26925607-4fb5-4fb4-a799-290bffb93e88.jpg',
      title: "Oh My Customisation's",
      description: 'Bespoke floral designs tailored to your vision, creating one-of-a-kind arrangements just for you.'
    }
  };

  const currentBanner = categoryBanners[currentCategory];

  if (loading) {
    return (
      <div className="min-h-screen bg-white flex flex-col justify-center items-center py-32">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-secondary mb-4"></div>
        <p className="text-primary font-medium tracking-widest text-sm uppercase">Loading Oh My Collection's...</p>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      {/* Header with Category Banner */}
      {currentBanner ? (
        <div className="relative h-[60vh] flex items-center justify-center overflow-hidden">
          <div className="absolute inset-0">
            <img
              src={currentBanner.image}
              alt={currentBanner.title}
              className="w-full h-full object-cover"
            />
            <div className="absolute inset-0 bg-white/20" />
          </div>
          <div className="container relative z-10 text-center">
            <div className="flex items-center justify-center gap-2 text-primary/80 text-base font-medium uppercase tracking-[0.2em] mb-6">
              <Link to="/" className="hover:text-secondary transition-colors">Home</Link>
              <ChevronRight className="h-4 w-4" />
              <span>Shop</span>
            </div>
            <h1 className="text-5xl md:text-7xl font-bold tracking-tight text-white mb-6 drop-shadow-lg">
              {currentBanner.title}
            </h1>
            <p className="text-xl text-white/95 max-w-2xl mx-auto leading-relaxed drop-shadow-md">
              {currentBanner.description}
            </p>
          </div>
        </div>
      ) : (
        <div className="bg-primary py-24 text-white relative overflow-hidden">
          <div className="container relative z-10 text-center space-y-6">
            <div className="flex items-center justify-center gap-2 text-secondary/90 text-base font-medium uppercase tracking-[0.2em]">
              <Link to="/" className="hover:text-secondary transition-colors">Home</Link>
              <ChevronRight className="h-4 w-4" />
              <span>Shop</span>
            </div>
            <h1 className="text-6xl md:text-7xl font-bold tracking-tight">{categoryName}</h1>
            <p className="text-xl text-white/80 max-w-2xl mx-auto leading-relaxed">
              Discover our curated selection of premium floral designs and gifts, crafted with passion and precision.
            </p>
          </div>
        </div>
      )}

      <div className={`container ${(currentCategory === 'signature-collection' || currentCategory === 'custom-orders') ? 'pt-16 pb-0' : 'py-16'}`}>
        {/* Toolbar */}
        <div className="flex flex-col md:flex-row justify-between items-center mb-12 gap-8">
          <div className="flex flex-wrap gap-3 justify-center">
            {categories.map((cat) => (
              <Button
                key={cat.slug}
                variant={currentCategory === cat.slug ? "default" : "outline"}
                className={currentCategory === cat.slug ? "bg-secondary text-primary" : "border-primary/10 hover:border-secondary"}
                onClick={() => setSearchParams({ category: cat.slug })}
              >
                {cat.name}
              </Button>
            ))}
          </div>

          <div className="flex items-center gap-6">
            <span className="text-base text-muted-foreground whitespace-nowrap font-medium">
              {filteredProducts.length} items found
            </span>
            <div className="h-5 w-[1px] bg-border hidden md:block" />
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" className="gap-2 h-11 px-6">
                  <SlidersHorizontal className="h-5 w-5" />
                  Sort By: {sortBy.charAt(0).toUpperCase() + sortBy.slice(1).replace('-', ' ')}
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuItem onClick={() => setSortBy('featured')} className="text-base py-3">Featured</DropdownMenuItem>
                <DropdownMenuItem onClick={() => setSortBy('price-low')} className="text-base py-3">Price: Low to High</DropdownMenuItem>
                <DropdownMenuItem onClick={() => setSortBy('price-high')} className="text-base py-3">Price: High to Low</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        {/* Grid */}
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6">
          {filteredProducts.map((product, index) => (
            <div key={product.id} className="animate-slide-up" style={{ animationDelay: `${(index % 12) * 0.05}s` }}>
              <ProductCard product={product} />
            </div>
          ))}
        </div>

        {filteredProducts.length === 0 && currentCategory !== 'signature-collection' && currentCategory !== 'custom-orders' && (
          <div className="py-32 text-center">
            <h3 className="text-3xl font-bold text-primary mb-4">No products found</h3>
            <p className="text-xl text-muted-foreground">Try selecting a different category or resetting filters.</p>
            <Button
              variant="secondary" className="mt-6"
              onClick={() => setSearchParams({ category: 'all' })}
            >
              Show All Products
            </Button>
          </div>
        )}
      </div>

      {/* Bespoke Services — shown only for Oh My Signature's */}
      {currentCategory === 'signature-collection' && (
        <section className="pt-4 pb-24 bg-muted/20">
          <div className="container text-center mb-16 space-y-4">
            <h2 className="text-4xl md:text-5xl font-bold tracking-tight text-primary">Our Bespoke Services</h2>
            <div className="h-1 w-32 bg-secondary mx-auto" />
            <p className="text-xl text-muted-foreground max-w-2xl mx-auto leading-relaxed">
              We handle every detail so you can focus on the emotion of the moment.
            </p>
          </div>
          <div className="container grid grid-cols-1 md:grid-cols-3 gap-10">
            {[
              { icon: '🤍', title: 'Proposal Planning & Surprise', desc: "Intimate settings, romantic floral walkways, and the perfect ambiance for that 'Yes'.", eventType: 'Proposal Planning & Surprise' },
              { icon: '✨', title: 'Midnight Surprises', desc: 'Coordinated doorstep deliveries exactly at the stroke of midnight with musicians and decor.', eventType: 'Midnight Surprises' },
              { icon: '📍', title: 'Event Styling', desc: 'Complete floral and decor transformation for gala dinners, weddings, and private parties.', eventType: 'Event Styling' },
              { icon: '📷', title: 'Moments Captured', desc: 'Professional photography and videography to document every surprised expression.', eventType: 'Moments Captured' },
              { icon: '🎵', title: 'Live Musicians', desc: 'Strings, saxophonists, or vocalists to provide the perfect soundtrack to your surprise.', eventType: 'Live Musicians' },
              { icon: '🎁', title: 'Bespoke Gifting', desc: 'Custom-made luxury hampers featuring rare finds and personalized treasures.', eventType: 'Bespoke Gifting' },
            ].map((s) => (
              <Link
                key={s.title}
                to={`/surprise-services?eventType=${encodeURIComponent(s.eventType)}`}
                className="bg-white rounded-2xl luxury-shadow p-10 flex flex-col items-center text-center hover:-translate-y-2 hover:shadow-xl transition-all duration-300 cursor-pointer group"
              >
                <span className="text-4xl mb-6">{s.icon}</span>
                <h3 className="text-xl font-bold text-primary mb-4">{s.title}</h3>
                <p className="text-muted-foreground text-sm leading-relaxed mb-6">{s.desc}</p>
                <span className="mt-auto text-sm font-semibold text-secondary border border-secondary/40 rounded-full px-4 py-1.5 group-hover:bg-secondary group-hover:text-primary transition-all duration-300">
                  Book This Service →
                </span>
              </Link>
            ))}
          </div>
          <div className="text-center mt-12">
            <Link to="/surprise-services">
              <Button size="lg" variant="secondary" className="px-10 h-14 text-base font-semibold hover-lift">
                Plan a Surprise →
              </Button>
            </Link>
          </div>
        </section>
      )}

      {/* Customisation Form — shown only for Oh My Customisation's */}
      {currentCategory === 'custom-orders' && (
        <section className="pt-4 pb-24">
          <div className="container max-w-5xl">
            <div className="bg-white rounded-3xl luxury-shadow overflow-hidden grid md:grid-cols-2">
              {/* Left panel */}
              <div className="bg-primary p-10 flex flex-col justify-center gap-8">
                <div>
                  <h2 className="text-3xl font-bold text-white mb-3">Start Your Journey</h2>
                  <p className="text-white/70 leading-relaxed">Fill out the form and a dedicated customisation specialist will contact you within 24 hours.</p>
                </div>
                <div className="space-y-4">
                  {[{ Icon: MessageSquare, label: 'Expert Guidance' }, { Icon: Users, label: 'Dedicated Team' }, { Icon: CalendarDays, label: 'Flexible Dates' }].map(({ Icon, label }) => (
                    <div key={label} className="flex items-center gap-3 text-white/80">
                      <Icon className="h-5 w-5 text-secondary" />
                      <span className="font-medium">{label}</span>
                    </div>
                  ))}
                </div>
              </div>
              {/* Right panel — form */}
              <form onSubmit={handleCustSubmit} className="p-10 space-y-5">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium text-foreground">Your Name <span className="text-destructive">*</span></label>
                  <Input placeholder="Enter your name" className={cn('h-12', custErrors.name && 'border-destructive')} value={custForm.name} onChange={e => handleCustChange('name', e.target.value)} />
                  {custErrors.name && <p className="text-sm text-destructive">{custErrors.name}</p>}
                </div>
                <div className="space-y-1.5">
                  <label className="text-sm font-medium text-foreground">Email Address <span className="text-destructive">*</span></label>
                  <Input type="email" placeholder="Enter your email" className={cn('h-12', custErrors.email && 'border-destructive')} value={custForm.email} onChange={e => handleCustChange('email', e.target.value)} />
                  {custErrors.email && <p className="text-sm text-destructive">{custErrors.email}</p>}
                </div>
                <div className="space-y-1.5">
                  <label className="text-sm font-medium text-foreground">Contact No <span className="text-destructive">*</span></label>
                  <Input type="tel" placeholder="Enter your contact number" className={cn('h-12', custErrors.contactNo && 'border-destructive')} value={custForm.contactNo} onChange={e => handleCustChange('contactNo', e.target.value)} />
                  {custErrors.contactNo && <p className="text-sm text-destructive">{custErrors.contactNo}</p>}
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-1.5">
                    <label className="text-sm font-medium text-foreground">Address</label>
                    <Input placeholder="Street/Area" className="h-12" value={custForm.address} onChange={e => handleCustChange('address', e.target.value)} />
                  </div>
                  <div className="space-y-1.5">
                    <label className="text-sm font-medium text-foreground">City</label>
                    <Input placeholder="City" className="h-12" value={custForm.city} onChange={e => handleCustChange('city', e.target.value)} />
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium text-foreground">Customisation Type</label>
                  <select className="w-full h-12 rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-secondary" value={custForm.eventType} onChange={e => handleCustChange('eventType', e.target.value)}>
                    <option>Custom Floral Arrangement</option>
                    <option>Custom Gift Hamper</option>
                    <option>Custom Event Decor</option>
                    <option>Wedding Customisation</option>
                    <option>Corporate Customisation</option>
                    <option>Birthday Customisation</option>
                    <option>Anniversary Customisation</option>
                    <option>Other</option>
                  </select>
                </div>
                {custForm.eventType === 'Other' && (
                  <div className="space-y-1.5">
                    <label className="text-sm font-medium text-foreground">Event Name <span className="text-destructive">*</span></label>
                    <Input placeholder="Enter your event name" className="h-12" value={custForm.serviceName} onChange={e => handleCustChange('serviceName', e.target.value)} />
                    {custErrors.serviceName && <p className="text-sm text-destructive">{custErrors.serviceName}</p>}
                  </div>
                )}
                <div className="space-y-1.5">
                  <label className="text-sm font-medium text-foreground">Tell us more <span className="text-destructive">*</span></label>
                  <Textarea placeholder="Describe your customisation request..." className={cn('min-h-[110px]', custErrors.message && 'border-destructive')} value={custForm.message} onChange={e => handleCustChange('message', e.target.value)} />
                  {custErrors.message && <p className="text-sm text-destructive">{custErrors.message}</p>}
                </div>
                <Button type="submit" variant="secondary" size="lg" className="w-full h-16 text-xl font-bold rounded-full mt-4 hover-lift" disabled={custSubmitting}>
                  {custSubmitting ? 'Submitting...' : 'Inquire Now'}
                </Button>
              </form>
            </div>
          </div>
        </section>
      )}
    </div>
  );
}

function ProductCard({ product }: { product: any }) {
  return (
    <div className="group relative bg-white border border-transparent hover:border-secondary/20 luxury-shadow hover-lift rounded-xl overflow-hidden flex flex-col h-full transition-all">
      <Link to={`/product/${product.slug}`} className="relative block aspect-square overflow-hidden">
        <img
          src={product.image}
          alt={product.name}
          className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110"
        />
        <div className="absolute inset-0 bg-black/5 opacity-0 group-hover:opacity-100 transition-opacity" />
        {product.stock_status === 'out_of_stock' && (
          <div className="absolute inset-0 bg-white/60 backdrop-blur-[2px] flex items-center justify-center">
            <Badge variant="destructive" className="px-3 py-1 text-xs">Sold Out</Badge>
          </div>
        )}
        <div className="absolute bottom-4 left-1/2 -translate-x-1/2 translate-y-16 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 w-[90%]">
          <Button className="w-full h-10 bg-primary text-white hover:bg-secondary hover:text-primary font-semibold text-xs rounded-full">
            Quick View
          </Button>
        </div>
      </Link>
      <div className="p-4 flex flex-col flex-1">
        <div className="mb-2">
          <Badge variant="secondary" className="bg-secondary/10 text-secondary border-none text-[10px] uppercase tracking-wider font-bold px-2 py-0.5">
            {product.category.replace('-', ' ')}
          </Badge>
        </div>
        <Link to={`/product/${product.slug}`} className="hover:text-secondary transition-colors mb-2">
          <h3 className="text-sm md:text-base font-bold text-primary line-clamp-1">{product.name}</h3>
        </Link>
        <div className="flex items-center gap-0.5 text-yellow-500 mb-3">
          {[1, 2, 3, 4, 5].map(i => <Star key={i} className="h-3 w-3 fill-current" />)}
          <span className="text-[10px] text-muted-foreground font-bold ml-1">(42)</span>
        </div>
        <div className="mt-auto flex items-center justify-between">
          <span className="text-base md:text-lg font-bold text-primary">{formatINR(product.price)}</span>
          <span className="text-xs text-muted-foreground italic">Free Delivery</span>
        </div>
      </div>
    </div>
  );
}
