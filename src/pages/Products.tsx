import { formatINR } from "@/lib/currency";
import { useState, useMemo, useEffect } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import {
  Star,
  ChevronRight,
  SlidersHorizontal,
  MessageSquare,
  Users,
  CalendarDays,
  Sparkles,
  Heart,
  Eye,
  ShoppingBag,
  ArrowRight,
  CheckCircle2,
  ShieldCheck,
  PhoneCall,
  Music,
  Camera,
  Gift,
  Moon,
  Flame,
  X,
  Clock,
  Check
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { useToast } from '@/hooks/use-toast';
import { useAuth } from '@/contexts/AuthContext';
import { getProducts } from '@/db/api';
import { customisationService } from '@/services/api';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { useCart } from '@/contexts/CartContext';
import { WishlistButton } from '@/components/common/WishlistButton';

const categories = [
  { name: 'All Collections', slug: 'all' },
  { name: "Oh My Bloom's", slug: 'flower-arrangements' },
  { name: "Oh My Love's", slug: 'gift-hampers' },
  { name: "Oh My Signature's", slug: 'signature-collection' },
  { name: "Oh My Moment's", slug: 'occasions' },
  { name: "Oh My Customisation's", slug: 'custom-orders' },
];

const MOMENT_SERVICES_DATA = [
  {
    id: 'proposal',
    title: 'Proposal Planning & Surprise',
    subtitle: 'Romantic Milestone & Secret "YES!" Moments',
    tag: 'MOST CELEBRATED',
    icon: Heart,
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_0d1f5bf9-c3e6-4678-b943-f496a294145d.jpg',
    desc: 'Intimate candlelit walkways, grand floral arches, "MARRY ME" illumination, and secret on-site orchestration for that breathtaking, cinematic "YES" moment.',
    highlights: ['50+ Pure Wax Pillar Candles', 'Bespoke Fresh Rose Archway', 'Secret Coordinator & Timing Cue', 'Complimentary Champagne Setup'],
    itinerary: '1. Secret Itinerary Design → 2. Hidden Venue Transformation → 3. The Grand Reveal & Emotion',
    priceEstimate: 'From ₹14,999'
  },
  {
    id: 'midnight',
    title: 'Midnight Surprises',
    subtitle: 'Doorstep Magic at the Stroke of 00:00',
    tag: 'POPULAR CHOICE',
    icon: Moon,
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_71ed4da9-9ce1-4430-b4a5-15e4fbfc8ba3.jpg',
    desc: 'Coordinated doorstep delivery at precisely 00:00 midnight with live acoustic guitarist serenade, balloon ceiling transformations, fresh blooms, and luxury gourmet treats.',
    highlights: ['Guaranteed 00:00 Sharp Delivery', 'Live Acoustic Guitarist/Singer', 'Helium Balloon Ceiling Bouquet', 'Luxury Cake & Bloom Pairing'],
    itinerary: '1. Secret Delivery Route → 2. Midnight Doorbell & Live Serenade → 3. Midnight Unwrapping',
    priceEstimate: 'From ₹7,999'
  },
  {
    id: 'event-styling',
    title: 'Event Styling & Decor',
    subtitle: 'Bespoke Floral & Venue Architecture',
    tag: 'SIGNATURE CRAFT',
    icon: Sparkles,
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_14558096-74be-4c1a-a8a2-e0334e6050d9.jpg',
    desc: 'Complete high-fashion floral and decor transformation for private dinner parties, luxury anniversaries, intimate terrace gatherings, and milestone galas.',
    highlights: ['Full Room Floral Canopy', 'Architectural Table Centrepieces', 'Mood Lighting & Warm Ambience', 'Custom Color Palette Styling'],
    itinerary: '1. Venue Inspection & Moodboard → 2. Floral Production → 3. On-Site Complete Styling',
    priceEstimate: 'From ₹19,999'
  },
  {
    id: 'moments-captured',
    title: 'Moments Captured (4K Cinema)',
    subtitle: 'Discreet Photography & Cinematic Film',
    tag: 'KEEPSAKE MEMORY',
    icon: Camera,
    image: 'https://images.unsplash.com/photo-1537633552985-df8429e8048b?w=800&q=80',
    desc: 'High-end discreet cinematography and editorial photography to capture the genuine shock, tears of joy, and intimate candid reactions forever.',
    highlights: ['Professional 4K Cinematographer', 'Discreet Candid Photography', 'Color-Graded Cinematic Reel', 'High-Res Digital Album in 48h'],
    itinerary: '1. Hidden Camera Positioning → 2. Multi-Angle Surprise Capture → 3. Edited 4K Teaser & Photos',
    priceEstimate: 'From ₹4,999'
  },
  {
    id: 'live-musicians',
    title: 'Live Musicians & Serenades',
    subtitle: 'Strings, Saxophone & Acoustic Vocals',
    tag: 'EMOTIONAL TOUCH',
    icon: Music,
    image: 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=800&q=80',
    desc: 'Talented cellists, saxophonists, violin duos, or acoustic vocalists performing your loved one\'s favorite love ballad as they walk into the surprise.',
    highlights: ['Handpicked Master Musicians', 'Custom Song Repertoire', 'Wireless Sound Equipment', 'Seamless Hidden Entry'],
    itinerary: '1. Song Selection → 2. Soundcheck & Rehearsal → 3. Live Surprise Performance',
    priceEstimate: 'From ₹3,999'
  },
  {
    id: 'bespoke-gifting',
    title: 'Bespoke Gifting Atelier',
    subtitle: 'Customized Keepsakes & Luxury Hampers',
    tag: 'ULTRA LUXURY',
    icon: Gift,
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_c023c54c-4d4a-425e-aee3-ef5a6118cf40.jpg',
    desc: 'Handmade velvet keepsakes, engraved crystal flutes, artisan European chocolates, and rare botanical blooms curated exclusively for your milestone.',
    highlights: ['Custom Engraving & Leather Tags', 'Handmade Velvet Presentation Box', 'Artisan Gourmet Pairings', 'Personalized Calligraphy Letter'],
    itinerary: '1. Taste & Preference Curation → 2. Artisan Packaging → 3. White-Glove Hand Delivery',
    priceEstimate: 'From ₹3,499'
  }
];

export default function Products() {
  const { user } = useAuth();
  const { toast } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const currentCategory = searchParams.get('category') || 'all';
  const searchQuery = searchParams.get('search') || '';
  const [sortBy, setSortBy] = useState('featured');
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeExperienceModal, setActiveExperienceModal] = useState<any | null>(null);

  useEffect(() => {
    async function loadProducts() {
      try {
        setLoading(true);
        const data = await getProducts(currentCategory);
        setProducts(data);
      } catch (error) {
        console.error('Error loading products:', error);
        toast({
          title: "Error Loading Products",
          description: "Failed to fetch collection catalog.",
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
    if (!custForm.email.trim()) errs.email = 'Email is required';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(custForm.email)) errs.email = 'Invalid email';
    if (!custForm.contactNo.trim()) errs.contactNo = 'Contact number is required';
    if (!custForm.message.trim()) errs.message = 'Please describe your customisation';
    if (custForm.eventType === 'Other' && !custForm.serviceName.trim()) errs.serviceName = 'Please specify the service';
    setCustErrors(errs);
    if (Object.keys(errs).length > 0) return;
    setCustSubmitting(true);
    try {
      const data = await customisationService.submit(custForm);
      if (data.error) throw new Error(data.error);
      toast({ title: 'Custom Inquiry Submitted!', description: 'Our lead floral designer will contact you within 24 hours.' });
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

    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      result = result.filter(p => p.name.toLowerCase().includes(q) || p.description?.toLowerCase().includes(q));
    }

    if (sortBy === 'price-low') {
      result.sort((a, b) => Number(a.price) - Number(b.price));
    } else if (sortBy === 'price-high') {
      result.sort((a, b) => Number(b.price) - Number(a.price));
    }

    return result;
  }, [products, sortBy, searchQuery]);

  const categoryName = categories.find(c => c.slug === currentCategory)?.name || 'All Products';

  const categoryBanners: Record<string, { image: string; title: string; description: string }> = {
    'flower-arrangements': {
      image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_39a052aa-4054-4588-bf17-098a96cfcd2b.jpg',
      title: "Oh My Bloom's",
      description: 'Exquisite floral arrangements crafted with fresh farm-direct stems by master florists for life\'s finest moments.'
    },
    'gift-hampers': {
      image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_032564d4-e9a3-41be-b09c-95d6ef79e302.jpg',
      title: "Oh My Love's",
      description: 'Curated luxury hampers featuring artisan delicacies, fine wines, and velvet ribbons designed to express your deepest sentiment.'
    },
    'signature-collection': {
      image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_6d766290-f70a-4d60-98fe-4aeb18ce0016.jpg',
      title: "Oh My Signature's",
      description: 'Our flagship collection of rare exotic blooms, handcrafted ceramic vessels, and statement architectural florals.'
    },
    'occasions': {
      image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_aa54fb7e-093c-4976-a8af-1fb7321d319e.jpg',
      title: "Oh My Moment's",
      description: 'Handcrafted floral masterpieces tailored for birthdays, anniversaries, proposals, and royal celebrations.'
    },
    'custom-orders': {
      image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_26925607-4fb5-4fb4-a799-290bffb93e88.jpg',
      title: "Oh My Customisation's",
      description: 'Bespoke floral & hamper designs created exclusively for your vision with personalized consultations.'
    }
  };

  const currentBanner = categoryBanners[currentCategory];

  if (loading) {
    return (
      <div className="min-h-screen bg-white flex flex-col justify-center items-center py-32">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-secondary mb-4"></div>
        <p className="text-primary font-bold tracking-widest text-xs uppercase">Curating Oh My Collection...</p>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      {/* Category Banner Header */}
      {currentBanner ? (
        <div className="relative h-[40vh] md:h-[55vh] flex items-center justify-center overflow-hidden bg-primary">
          <div className="absolute inset-0">
            <img
              src={currentBanner.image}
              alt={currentBanner.title}
              className="w-full h-full object-cover opacity-60 filter brightness-90 scale-105"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-primary via-primary/50 to-transparent" />
          </div>
          <div className="container relative z-10 text-center text-white px-4">
            <div className="flex items-center justify-center gap-2 text-secondary font-bold text-xs uppercase tracking-[0.25em] mb-4">
              <Link to="/" className="hover:underline">Home</Link>
              <ChevronRight className="h-3.5 w-3.5" />
              <span>Shop Collection</span>
            </div>
            <h1 className="text-3xl sm:text-5xl md:text-7xl font-bold font-serif tracking-tight mb-3 md:mb-4 drop-shadow-xl">
              {currentBanner.title}
            </h1>
            <p className="text-lg md:text-xl text-white/90 max-w-2xl mx-auto leading-relaxed font-light drop-shadow">
              {currentBanner.description}
            </p>
          </div>
        </div>
      ) : (
        <div className="bg-primary py-20 text-white relative overflow-hidden">
          <div className="container relative z-10 text-center space-y-4">
            <div className="flex items-center justify-center gap-2 text-secondary font-bold text-xs uppercase tracking-[0.25em]">
              <Link to="/" className="hover:underline">Home</Link>
              <ChevronRight className="h-3.5 w-3.5" />
              <span>Catalog</span>
            </div>
            <h1 className="text-5xl md:text-7xl font-bold font-serif tracking-tight">{searchQuery ? `Results for "${searchQuery}"` : categoryName}</h1>
            <p className="text-lg text-white/80 max-w-xl mx-auto font-light">
              Explore our handcrafted luxury bouquets, curated hampers, and bespoke floral gifts.
            </p>
          </div>
        </div>
      )}

      <div className={`container ${(currentCategory === 'occasions' || currentCategory === 'custom-orders') ? 'pt-12 pb-0' : 'py-14'}`}>
        {/* Toolbar */}
        <div className="flex flex-col gap-4 md:flex-row justify-between items-start md:items-center mb-10 border-b border-border pb-6">
          <div className="flex flex-wrap gap-2 justify-start w-full md:w-auto">
            {categories.map((cat) => (
              <button
                key={cat.slug}
                type="button"
                onClick={() => setSearchParams({ category: cat.slug })}
                className={cn(
                  "rounded-full text-xs font-bold tracking-wider px-5 h-10 transition-all duration-300 border outline-none",
                  currentCategory === cat.slug
                    ? "bg-secondary text-primary border-secondary shadow-md gold-glow font-extrabold"
                    : "border-border text-foreground bg-white hover:bg-secondary/20 hover:border-secondary hover:text-primary hover:shadow-sm"
                )}
              >
                {cat.name}
              </button>
            ))}
          </div>

          <div className="flex items-center gap-4">
            <span className="text-xs text-muted-foreground whitespace-nowrap font-bold uppercase tracking-wider">
              {filteredProducts.length} Products
            </span>
            <div className="h-4 w-[1px] bg-border hidden md:block" />
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" className="gap-2 h-10 px-5 rounded-full text-xs font-semibold border-border">
                  <SlidersHorizontal className="h-4 w-4 text-secondary" />
                  Sort: {sortBy.charAt(0).toUpperCase() + sortBy.slice(1).replace('-', ' ')}
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-52 p-1">
                <DropdownMenuItem onClick={() => setSortBy('featured')} className="text-xs py-2.5 font-medium">Featured First</DropdownMenuItem>
                <DropdownMenuItem onClick={() => setSortBy('price-low')} className="text-xs py-2.5 font-medium">Price: Low to High</DropdownMenuItem>
                <DropdownMenuItem onClick={() => setSortBy('price-high')} className="text-xs py-2.5 font-medium">Price: High to Low</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        {/* Product Grid — auto-fills columns based on available width */}
        <div
          className="gap-4 md:gap-5"
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fill, minmax(min(240px, 100%), 1fr))'
          }}
        >
          {filteredProducts.map((product, index) => (
            <div key={product.id} className="animate-slide-up" style={{ animationDelay: `${(index % 12) * 0.04}s` }}>
              <ProductCard product={product} />
            </div>
          ))}
        </div>

        {filteredProducts.length === 0 && currentCategory !== 'occasions' && currentCategory !== 'custom-orders' && (
          <div className="py-24 text-center space-y-4">
            <h3 className="text-2xl font-bold font-serif text-primary">No Arrangements Found</h3>
            <p className="text-muted-foreground text-sm">We couldn't find items matching your criteria. Try resetting filters.</p>
            <Button
              variant="secondary"
              className="mt-2 rounded-full px-8 font-bold"
              onClick={() => setSearchParams({ category: 'all' })}
            >
              Browse All Collections
            </Button>
          </div>
        )}
      </div>

      {/* Bespoke Services — shown for Oh My Moment's (Occasions) */}
      {currentCategory === 'occasions' && (
        <section className="pt-8 pb-24 bg-gradient-to-b from-transparent via-[#FAF6F0]/60 to-transparent border-t border-border mt-12">
          <div className="container text-center mb-16 space-y-4">
            <div className="inline-flex items-center justify-center gap-2 px-4 py-1.5 rounded-full border border-secondary/40 bg-secondary/10 text-secondary text-xs font-bold uppercase tracking-widest">
              <Sparkles className="h-3.5 w-3.5" />
              <span>Bespoke Concierge & Event Architecture</span>
            </div>
            <h2 className="text-4xl md:text-5xl lg:text-6xl font-bold font-serif text-primary tracking-tight">
              Moment <span className="text-secondary italic">Services</span>
            </h2>
            <div className="h-1 w-24 bg-secondary mx-auto rounded-full" />
            <p className="text-muted-foreground max-w-2xl mx-auto text-base md:text-lg leading-relaxed">
              Explore our curated portfolio of secret proposals, midnight doorstep reveals, grand event styling, and live performances designed for life's unforgettable chapters.
            </p>
          </div>

          {/* 6 Viewable Luxury Experience Cards */}
          <div className="container grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
            {MOMENT_SERVICES_DATA.map((service) => {
              const ServiceIcon = service.icon;
              return (
                <div
                  key={service.id}
                  onClick={() => setActiveExperienceModal(service)}
                  className="group relative bg-white dark:bg-card rounded-3xl overflow-hidden border border-border/80 luxury-shadow hover-lift cursor-pointer flex flex-col transition-all duration-500 hover:border-secondary/60 hover:shadow-2xl"
                >
                  {/* Photo Header */}
                  <div className="relative aspect-[16/10] overflow-hidden bg-muted/20">
                    <img
                      src={service.image}
                      alt={service.title}
                      className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-transparent" />
                    
                    {/* Tag badge */}
                    <div className="absolute top-3.5 left-3.5 z-10">
                      <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-black/60 backdrop-blur-md border border-white/20 text-white text-[10px] uppercase font-bold tracking-widest">
                        <ServiceIcon className="h-3 w-3 text-secondary" />
                        {service.tag}
                      </span>
                    </div>

                    {/* Price Tag */}
                    <div className="absolute top-3.5 right-3.5 z-10">
                      <span className="inline-flex items-center px-3 py-1 rounded-full bg-secondary/90 text-primary text-xs font-extrabold shadow-md">
                        {service.priceEstimate}
                      </span>
                    </div>

                    {/* Title inside photo overlay */}
                    <div className="absolute bottom-3 left-4 right-4 text-white">
                      <h3 className="text-xl font-bold font-serif leading-snug group-hover:text-secondary transition-colors">
                        {service.title}
                      </h3>
                      <p className="text-xs text-white/80 line-clamp-1 mt-0.5 font-light">
                        {service.subtitle}
                      </p>
                    </div>
                  </div>

                  {/* Body Details */}
                  <div className="p-6 flex flex-col flex-1 justify-between gap-4">
                    <p className="text-xs text-muted-foreground leading-relaxed line-clamp-2">
                      {service.desc}
                    </p>

                    {/* Inclusions pill tags */}
                    <div className="space-y-1.5 pt-2 border-t border-border/60">
                      <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Signature Inclusions:</p>
                      <div className="flex flex-wrap gap-1.5">
                        {service.highlights.slice(0, 2).map((h, i) => (
                          <span key={i} className="text-[11px] font-medium bg-muted/50 text-foreground px-2.5 py-1 rounded-md border border-border/50">
                            ✦ {h}
                          </span>
                        ))}
                      </div>
                    </div>

                    {/* Interactive "View Experience" Action */}
                    <div className="pt-3 border-t border-border/50 flex items-center justify-between text-xs font-bold text-secondary group-hover:text-primary transition-colors">
                      <span className="flex items-center gap-1.5">
                        <Eye className="h-4 w-4" /> View Experience Details
                      </span>
                      <ArrowRight className="h-4 w-4 transform group-hover:translate-x-1.5 transition-transform" />
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          {/* ONE Grand, Unified "Book The Service" Master Call-to-Action Section */}
          <div className="container mt-16">
            <div className="relative rounded-3xl overflow-hidden bg-primary text-white border border-secondary/30 luxury-shadow p-8 md:p-12 lg:p-16">
              <div className="absolute top-0 right-0 w-96 h-96 bg-secondary/10 rounded-full blur-3xl pointer-events-none" />
              <div className="relative z-10 grid lg:grid-cols-12 gap-8 items-center">
                <div className="lg:col-span-8 space-y-4">
                  <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-secondary/40 bg-secondary/20 text-secondary text-xs font-bold uppercase tracking-widest">
                    <Sparkles className="h-3.5 w-3.5" />
                    <span>Bespoke Concierge Booking</span>
                  </div>
                  <h3 className="text-3xl md:text-5xl font-bold font-serif leading-tight">
                    Ready to Orchestrate an <span className="text-secondary italic">Unforgettable Moment?</span>
                  </h3>
                  <p className="text-white/80 text-sm md:text-base max-w-2xl leading-relaxed font-light">
                    Select your base experience, choose live musicians, photography, or floral upgrades, and reserve instantly online with guaranteed date confirmation and on-site coordination across Bangalore.
                  </p>
                  <div className="flex flex-wrap items-center gap-6 pt-2 text-xs md:text-sm text-white/90">
                    <span className="flex items-center gap-2"><CheckCircle2 className="h-4 w-4 text-secondary" /> 100% Secret & Discreet</span>
                    <span className="flex items-center gap-2"><CheckCircle2 className="h-4 w-4 text-secondary" /> 1,200+ Executed Surprises</span>
                    <span className="flex items-center gap-2"><CheckCircle2 className="h-4 w-4 text-secondary" /> Master Florist Styling</span>
                  </div>
                </div>

                <div className="lg:col-span-4 flex flex-col gap-3.5 w-full">
                  <Link to="/surprise-services">
                    <Button size="lg" className="w-full h-14 rounded-full bg-secondary text-primary hover:bg-secondary/90 font-bold text-base shadow-xl gold-glow hover-lift">
                      <Sparkles className="mr-2 h-5 w-5" />
                      Book &amp; Customize Experience
                    </Button>
                  </Link>
                  <a href="tel:+918147736396">
                    <Button size="lg" variant="outline" className="w-full h-14 rounded-full border-secondary/60 text-white bg-black/40 hover:bg-white/10 font-bold text-base backdrop-blur-md">
                      <PhoneCall className="mr-2 h-5 w-5 text-secondary" />
                      Speak to Concierge
                    </Button>
                  </a>
                </div>
              </div>
            </div>
          </div>

          {/* Interactive Experience Detail Modal */}
          {activeExperienceModal && (
            <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm animate-fade-in">
              <div className="bg-white dark:bg-card w-full max-w-3xl rounded-3xl overflow-hidden luxury-shadow border border-border flex flex-col max-h-[90vh] animate-scale-in">
                {/* Modal Header & Photo */}
                <div className="relative aspect-[16/8] sm:aspect-[16/7] overflow-hidden bg-black">
                  <img
                    src={activeExperienceModal.image}
                    alt={activeExperienceModal.title}
                    className="w-full h-full object-cover"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent" />
                  
                  {/* Close button */}
                  <button
                    onClick={() => setActiveExperienceModal(null)}
                    className="absolute top-4 right-4 h-9 w-9 rounded-full bg-black/60 text-white hover:bg-black flex items-center justify-center transition-colors z-20"
                    aria-label="Close modal"
                  >
                    <X className="h-5 w-5" />
                  </button>

                  <div className="absolute bottom-4 left-6 right-6 text-white">
                    <div className="flex items-center gap-2 mb-1.5">
                      <span className="text-[10px] font-bold uppercase tracking-widest bg-secondary text-primary px-2.5 py-0.5 rounded-full">
                        {activeExperienceModal.tag}
                      </span>
                      <span className="text-xs font-bold text-secondary">
                        {activeExperienceModal.priceEstimate}
                      </span>
                    </div>
                    <h3 className="text-2xl sm:text-3xl font-bold font-serif leading-tight">
                      {activeExperienceModal.title}
                    </h3>
                  </div>
                </div>

                {/* Modal Body */}
                <div className="p-6 sm:p-8 space-y-6 overflow-y-auto">
                  <div>
                    <h4 className="text-xs font-bold uppercase tracking-widest text-secondary mb-2">Experience Overview</h4>
                    <p className="text-sm text-muted-foreground leading-relaxed">
                      {activeExperienceModal.desc}
                    </p>
                  </div>

                  {/* Highlights Grid */}
                  <div>
                    <h4 className="text-xs font-bold uppercase tracking-widest text-secondary mb-3">What's Included</h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      {activeExperienceModal.highlights.map((item: string, idx: number) => (
                        <div key={idx} className="flex items-start gap-2.5 p-3 rounded-xl bg-muted/40 border border-border/50 text-xs font-medium">
                          <Check className="h-4 w-4 text-emerald-600 shrink-0 mt-0.5" />
                          <span>{item}</span>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Itinerary */}
                  <div>
                    <h4 className="text-xs font-bold uppercase tracking-widest text-secondary mb-2">Execution Itinerary</h4>
                    <p className="text-xs font-medium text-foreground/90 bg-[#FAF6F0] dark:bg-muted/20 p-3.5 rounded-xl border border-secondary/20">
                      {activeExperienceModal.itinerary}
                    </p>
                  </div>
                </div>

                {/* Modal Footer Actions */}
                <div className="p-6 border-t border-border/60 bg-muted/20 flex flex-col sm:flex-row gap-3 items-center justify-between">
                  <div>
                    <span className="text-xs text-muted-foreground">Estimated Investment:</span>
                    <p className="text-lg font-bold text-primary">{activeExperienceModal.priceEstimate}</p>
                  </div>
                  <div className="flex gap-2.5 w-full sm:w-auto">
                    <Button
                      variant="outline"
                      onClick={() => setActiveExperienceModal(null)}
                      className="flex-1 sm:flex-initial rounded-full h-11 px-6 text-xs font-bold"
                    >
                      Close
                    </Button>
                    <Link
                      to={`/surprise-services?eventType=${encodeURIComponent(activeExperienceModal.title)}`}
                      className="flex-1 sm:flex-initial"
                    >
                      <Button
                        variant="secondary"
                        className="w-full rounded-full h-11 px-8 text-xs font-bold shadow-md gold-glow"
                      >
                        <Sparkles className="mr-1.5 h-4 w-4" />
                        Customize &amp; Book Experience
                      </Button>
                    </Link>
                  </div>
                </div>
              </div>
            </div>
          )}
        </section>
      )}

      {/* Customisation Form — shown for Oh My Customisation's */}
      {currentCategory === 'custom-orders' && (
        <section className="pt-4 pb-24 mt-12">
          <div className="container max-w-4xl">
            <div className="bg-white rounded-3xl luxury-shadow overflow-hidden grid md:grid-cols-2 border border-border">
              <div className="bg-primary p-10 flex flex-col justify-center gap-8 text-white">
                <div>
                  <span className="text-secondary font-bold text-xs uppercase tracking-widest mb-2 block">Personal Atelier</span>
                  <h2 className="text-3xl font-bold font-serif mb-3">Custom Floral Design</h2>
                  <p className="text-white/70 text-sm leading-relaxed">Fill out the form and a master florist will contact you within 24 hours.</p>
                </div>
                <div className="space-y-4 text-xs">
                  {[{ Icon: MessageSquare, label: 'Personal Consultation' }, { Icon: Users, label: 'Dedicated Floral Designer' }, { Icon: CalendarDays, label: 'Express Delivery Dates' }].map(({ Icon, label }) => (
                    <div key={label} className="flex items-center gap-3 text-white/90">
                      <Icon className="h-5 w-5 text-secondary" />
                      <span className="font-semibold">{label}</span>
                    </div>
                  ))}
                </div>
              </div>

              <form onSubmit={handleCustSubmit} className="p-8 space-y-4 bg-white">
                <div className="space-y-1">
                  <label className="text-xs font-bold uppercase tracking-wider text-foreground">Your Name <span className="text-destructive">*</span></label>
                  <Input placeholder="Enter your name" className={cn('h-11 text-sm', custErrors.name && 'border-destructive')} value={custForm.name} onChange={e => handleCustChange('name', e.target.value)} />
                  {custErrors.name && <p className="text-xs text-destructive">{custErrors.name}</p>}
                </div>
                <div className="space-y-1">
                  <label className="text-xs font-bold uppercase tracking-wider text-foreground">Email Address <span className="text-destructive">*</span></label>
                  <Input type="email" placeholder="Enter your email" className={cn('h-11 text-sm', custErrors.email && 'border-destructive')} value={custForm.email} onChange={e => handleCustChange('email', e.target.value)} />
                  {custErrors.email && <p className="text-xs text-destructive">{custErrors.email}</p>}
                </div>
                <div className="space-y-1">
                  <label className="text-xs font-bold uppercase tracking-wider text-foreground">Contact No <span className="text-destructive">*</span></label>
                  <Input type="tel" placeholder="Enter your contact number" className={cn('h-11 text-sm', custErrors.contactNo && 'border-destructive')} value={custForm.contactNo} onChange={e => handleCustChange('contactNo', e.target.value)} />
                  {custErrors.contactNo && <p className="text-xs text-destructive">{custErrors.contactNo}</p>}
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold uppercase tracking-wider text-foreground">Customisation Type</label>
                  <select className="w-full h-11 rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-secondary" value={custForm.eventType} onChange={e => handleCustChange('eventType', e.target.value)}>
                    <option>Custom Floral Arrangement</option>
                    <option>Custom Gift Hamper</option>
                    <option>Custom Event Decor</option>
                    <option>Wedding Customisation</option>
                    <option>Corporate Customisation</option>
                    <option>Other</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold uppercase tracking-wider text-foreground">Tell us more <span className="text-destructive">*</span></label>
                  <Textarea placeholder="Describe your custom arrangement..." className={cn('min-h-[100px] text-sm', custErrors.message && 'border-destructive')} value={custForm.message} onChange={e => handleCustChange('message', e.target.value)} />
                  {custErrors.message && <p className="text-xs text-destructive">{custErrors.message}</p>}
                </div>

                <Button type="submit" variant="secondary" className="w-full h-14 font-bold rounded-full mt-2 hover-lift shadow-md" disabled={custSubmitting}>
                  {custSubmitting ? 'Submitting...' : '✨ Submit Custom Request'}
                </Button>
              </form>
            </div>
          </div>
        </section>
      )}
    </div>
  );
}

function getProductRating(product: any): string {
  if (product.rating && Number(product.rating) > 0) {
    return Number(product.rating).toFixed(1);
  }
  const seed = String(product.id || product.slug || '').split('').reduce((acc: number, c: string) => acc + c.charCodeAt(0), 0);
  const steps = [4.7, 4.8, 4.9, 5.0];
  return steps[seed % steps.length].toFixed(1);
}

function ProductCard({ product }: { product: any }) {
  const { addToCart } = useCart();
  const { toast } = useToast();

  const handleQuickAdd = (e: React.MouseEvent) => {
    e.preventDefault();
    addToCart(product, 1);
    toast({
      title: "Added to Shopping Bag",
      description: `${product.name} has been added.`,
    });
  };

  return (
    <div
      className="group relative bg-white border border-border/80 luxury-shadow hover-lift rounded-2xl overflow-hidden flex flex-col h-full transition-all duration-300 hover:border-secondary/50"
      style={{ containerType: 'inline-size', containerName: 'productcard' }}
    >
      <Link to={`/product/${product.slug}`} className="relative block aspect-square overflow-hidden bg-muted/10">
        <img
          src={product.image}
          alt={product.name}
          className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110"
        />
        <div className="absolute inset-0 bg-black/10 opacity-0 group-hover:opacity-100 transition-opacity" />

        {/* Best Seller Badge */}
        {(product.is_bestseller === true || Number(product.is_bestseller) === 1) && (
          <div className="absolute top-2.5 left-2.5 z-20">
            <span className="inline-flex items-center gap-1 bg-amber-500/90 backdrop-blur-md text-white text-[10px] uppercase font-bold tracking-wider px-2.5 py-1 rounded-full shadow-md">
              <Sparkles className="h-3 w-3" /> Best Seller
            </span>
          </div>
        )}

        {/* Wishlist Heart Button */}
        <WishlistButton
          product={product}
          size="md"
          className="absolute top-2.5 right-2.5 z-20"
        />

        {product.stock_status === 'out_of_stock' && (
          <div className="absolute inset-0 bg-white/70 backdrop-blur-[2px] flex items-center justify-center z-10">
            <Badge variant="destructive" className="px-3 py-1 text-xs uppercase tracking-widest font-bold">Sold Out</Badge>
          </div>
        )}

        <div className="absolute bottom-2 left-2 right-2 opacity-0 group-hover:opacity-100 transition-all duration-300 flex gap-2 z-10">
          <Button
            onClick={handleQuickAdd}
            size="sm"
            className="flex-1 h-9 bg-primary text-white hover:bg-secondary hover:text-primary font-bold text-xs rounded-full shadow-md gap-1.5"
          >
            <ShoppingBag className="h-3.5 w-3.5" />
            Quick Add
          </Button>
        </div>
      </Link>

      {/* Card body — padding scales with container width */}
      <div className="product-card-body flex flex-col flex-1">
        <div className="flex justify-between items-center mb-1.5">
          <span className="text-[10px] font-bold uppercase tracking-wider text-secondary bg-secondary/10 px-2 py-0.5 rounded truncate max-w-[65%]">
            {product.category ? product.category.replace('-', ' ') : 'Bloom'}
          </span>
          <div className="flex items-center gap-0.5 text-amber-500">
            <Star className="h-3 w-3 fill-current" />
            <span className="text-[11px] font-bold text-muted-foreground">{getProductRating(product)}</span>
          </div>
        </div>

        <Link to={`/product/${product.slug}`} className="hover:text-secondary transition-colors mb-1">
          <h3 className="product-card-title font-bold font-serif text-primary line-clamp-2 leading-snug">{product.name}</h3>
        </Link>

        <p className="text-xs text-muted-foreground line-clamp-2 mb-3 leading-relaxed flex-1">
          {product.description}
        </p>

        <div className="mt-auto flex items-center justify-between pt-2.5 border-t border-border/50">
          <span className="product-card-price font-bold text-primary">{formatINR(product.price)}</span>
          <span className="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded whitespace-nowrap">
            Same-Day
          </span>
        </div>
      </div>
    </div>
  );
}
