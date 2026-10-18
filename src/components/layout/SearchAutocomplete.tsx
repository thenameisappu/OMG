import React, { useState, useEffect, useRef, useMemo } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Search,
  X,
  ArrowRight,
  Sparkles,
  Heart,
  Gift,
  Moon,
  Calendar,
  Flame,
  Music,
  TrendingUp,
  Tag,
  ChevronRight,
  Layers
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { formatINR } from '@/lib/currency';
import { getProducts } from '@/db/api';
import { mockProducts } from '@/data/mockProducts';

// Curated Occasions & Experiences
export interface OccasionItem {
  id: string;
  title: string;
  subtitle: string;
  keywords: string[];
  link: string;
  icon: React.ComponentType<{ className?: string }>;
  tag: string;
}

const OCCASIONS: OccasionItem[] = [
  {
    id: 'anniversary',
    title: 'Anniversary Celebrations',
    subtitle: 'Romantic rose arches, candlelit dining & champagne',
    keywords: ['anniversary', 'romance', 'love', 'couple', 'romantic', 'roses', 'candle'],
    link: '/products?category=occasions',
    icon: Heart,
    tag: 'Occasion'
  },
  {
    id: 'birthday',
    title: 'Birthday Surprises',
    subtitle: 'Celebration blooms, cake pairings & balloon ceilings',
    keywords: ['birthday', 'bday', 'party', 'balloons', 'cake', 'celebration'],
    link: '/products?category=occasions',
    icon: Gift,
    tag: 'Occasion'
  },
  {
    id: 'proposal',
    title: 'Proposal Planning & Surprise',
    subtitle: 'Secret "YES!" moments, candlelit walkways & "MARRY ME" decor',
    keywords: ['proposal', 'propose', 'marry', 'engagement', 'secret', 'ring', 'walkway'],
    link: '/surprise-services',
    icon: Sparkles,
    tag: 'Experience'
  },
  {
    id: 'midnight',
    title: 'Midnight Surprises (00:00)',
    subtitle: 'Doorstep magic at midnight with live acoustic guitarist serenade',
    keywords: ['midnight', 'night', 'serenade', 'guitarist', '12am', 'surprise', '00:00'],
    link: '/surprise-services',
    icon: Moon,
    tag: 'Experience'
  },
  {
    id: 'subscriptions',
    title: 'Floral Subscriptions',
    subtitle: 'Occasion-timed fresh bloom deliveries right to your door',
    keywords: ['subscription', 'subscriptions', 'recurring', 'weekly', 'monthly', 'flower club', 'plan'],
    link: '/subscriptions',
    icon: Calendar,
    tag: 'Service'
  },
  {
    id: 'event-styling',
    title: 'Event Styling & Decor',
    subtitle: 'Bespoke venue architecture & terrace dinner transformations',
    keywords: ['event', 'decor', 'styling', 'venue', 'wedding', 'dinner', 'terrace'],
    link: '/products?category=custom-orders',
    icon: Flame,
    tag: 'Bespoke'
  },
  {
    id: 'live-music',
    title: 'Live Acoustic Serenades',
    subtitle: 'Live violinist, saxophonist, or guitar serenade at your doorstep',
    keywords: ['music', 'musician', 'guitar', 'guitarist', 'singer', 'violin', 'sax', 'song'],
    link: '/surprise-services',
    icon: Music,
    tag: 'Add-on'
  },
];

// Curated Collections / Categories
const COLLECTIONS = [
  {
    name: "Oh My Bloom's",
    category: 'flower-arrangements',
    keywords: ['bloom', 'blooms', 'flower', 'flowers', 'bouquet', 'bouquets', 'roses', 'tulips', 'orchids', 'stems', 'lily', 'sunflower'],
    subtitle: 'Fresh floral bouquets & luxury stems',
    link: '/products?category=flower-arrangements',
  },
  {
    name: "Oh My Love's",
    category: 'gift-hampers',
    keywords: ['love', 'loves', 'hamper', 'hampers', 'gift', 'gifts', 'chocolate', 'chocolates', 'gourmet', 'crate', 'treats'],
    subtitle: 'Curated hampers & gourmet treats',
    link: '/products?category=gift-hampers',
  },
  {
    name: "Oh My Signature's",
    category: 'signature-collection',
    keywords: ['signature', 'signatures', 'exotic', 'cascade', 'hatbox', 'preserved', 'rare', 'vessels', 'forever'],
    subtitle: 'Rare exotic blooms & bespoke vessels',
    link: '/products?category=signature-collection',
  },
  {
    name: "Oh My Moment's",
    category: 'occasions',
    keywords: ['moment', 'moments', 'occasion', 'occasions', 'celebration', 'celebrations'],
    subtitle: 'Celebration bundles & occasion styling',
    link: '/products?category=occasions',
  },
  {
    name: "Oh My Customisation's",
    category: 'custom-orders',
    keywords: ['custom', 'customisation', 'customization', 'bespoke', 'tailored', 'personal atelier'],
    subtitle: 'Bespoke custom floral arrangements',
    link: '/products?category=custom-orders',
  },
];

// Popular Quick Search Tags
const POPULAR_SEARCH_TAGS = [
  { label: 'Red Roses', query: 'roses' },
  { label: 'Midnight Surprise', query: 'midnight' },
  { label: 'Anniversary', query: 'anniversary' },
  { label: 'Gift Hampers', query: 'hamper' },
  { label: 'Proposal', query: 'proposal' },
  { label: 'Sunflowers', query: 'sunflower' },
  { label: 'Subscriptions', query: 'subscriptions' },
];

interface SearchAutocompleteProps {
  placeholder?: string;
  className?: string;
  inputClassName?: string;
  autoExpand?: boolean;
  onNavigate?: () => void;
}

export function SearchAutocomplete({
  placeholder = 'Search luxury flowers, hampers, moments...',
  className,
  inputClassName,
  autoExpand = false,
  onNavigate,
}: SearchAutocompleteProps) {
  const [query, setQuery] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const [products, setProducts] = useState<any[]>([]);
  const [selectedIndex, setSelectedIndex] = useState<number>(-1);
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const navigate = useNavigate();

  // Load products cache for instantaneous suggestions
  useEffect(() => {
    let isMounted = true;
    async function loadCache() {
      try {
        const data = await getProducts();
        if (isMounted) {
          if (Array.isArray(data) && data.length > 0) {
            setProducts(data);
          } else {
            setProducts(mockProducts);
          }
        }
      } catch {
        if (isMounted) {
          setProducts(mockProducts);
        }
      }
    }
    loadCache();
    return () => {
      isMounted = false;
    };
  }, []);

  // Handle clicking outside to close
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Reset selected index on query change
  useEffect(() => {
    setSelectedIndex(-1);
  }, [query]);

  // Compute matched items
  const normalizedQuery = query.trim().toLowerCase();

  const matchingProducts = useMemo(() => {
    if (!normalizedQuery) return [];
    return products
      .filter((p) => {
        if (!p) return false;
        const name = String(p.name || '').toLowerCase();
        const desc = String(p.description || '').toLowerCase();
        const cat = String(p.category || '').toLowerCase();
        return (
          name.includes(normalizedQuery) ||
          desc.includes(normalizedQuery) ||
          cat.includes(normalizedQuery)
        );
      })
      .slice(0, 4);
  }, [normalizedQuery, products]);

  const matchingOccasions = useMemo(() => {
    if (!normalizedQuery) return [];
    return OCCASIONS.filter((occ) => {
      const title = occ.title.toLowerCase();
      const subtitle = occ.subtitle.toLowerCase();
      const kw = occ.keywords.some((k) => k.includes(normalizedQuery) || normalizedQuery.includes(k));
      return title.includes(normalizedQuery) || subtitle.includes(normalizedQuery) || kw;
    }).slice(0, 3);
  }, [normalizedQuery]);

  const matchingCollections = useMemo(() => {
    if (!normalizedQuery) return [];
    return COLLECTIONS.filter((col) => {
      const name = col.name.toLowerCase();
      const sub = col.subtitle.toLowerCase();
      const kw = col.keywords.some((k) => k.includes(normalizedQuery) || normalizedQuery.includes(k));
      return name.includes(normalizedQuery) || sub.includes(normalizedQuery) || kw;
    }).slice(0, 2);
  }, [normalizedQuery]);

  // Flatten searchable interactive items for keyboard navigation
  const flatSuggestions = useMemo(() => {
    const list: Array<{ type: 'product' | 'occasion' | 'collection'; link: string }> = [];
    matchingProducts.forEach((p) => list.push({ type: 'product', link: `/product/${p.slug}` }));
    matchingOccasions.forEach((o) => list.push({ type: 'occasion', link: o.link }));
    matchingCollections.forEach((c) => list.push({ type: 'collection', link: c.link }));
    return list;
  }, [matchingProducts, matchingOccasions, matchingCollections]);

  const handleSelectLink = (link: string) => {
    setIsOpen(false);
    setQuery('');
    if (onNavigate) onNavigate();
    navigate(link);
  };

  const handleSubmit = (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    if (selectedIndex >= 0 && selectedIndex < flatSuggestions.length) {
      handleSelectLink(flatSuggestions[selectedIndex].link);
      return;
    }
    if (query.trim()) {
      setIsOpen(false);
      const searchTarget = `/products?search=${encodeURIComponent(query.trim())}`;
      setQuery('');
      if (onNavigate) onNavigate();
      navigate(searchTarget);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!isOpen) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex((prev) => (prev < flatSuggestions.length - 1 ? prev + 1 : 0));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex((prev) => (prev > 0 ? prev - 1 : flatSuggestions.length - 1));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      handleSubmit();
    } else if (e.key === 'Escape') {
      setIsOpen(false);
    }
  };

  const handleClear = () => {
    setQuery('');
    setIsOpen(false);
    inputRef.current?.focus();
  };

  const hasMatches =
    matchingProducts.length > 0 || matchingOccasions.length > 0 || matchingCollections.length > 0;

  return (
    <div ref={containerRef} className={cn('relative w-full', className)}>
      <form onSubmit={handleSubmit} className="relative w-full">
        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none transition-colors group-focus-within:text-secondary" />
        <input
          ref={inputRef}
          type="search"
          placeholder={placeholder}
          value={query}
          onFocus={() => setIsOpen(true)}
          onChange={(e) => {
            setQuery(e.target.value);
            setIsOpen(true);
          }}
          onKeyDown={handleKeyDown}
          className={cn(
            'h-10 w-full rounded-full border border-input bg-muted/20 text-foreground pl-10 pr-9 text-sm outline-none transition-all duration-300',
            'focus:ring-2 focus:ring-secondary/60 focus:border-secondary focus:bg-white shadow-xs',
            autoExpand && 'w-80 lg:w-80 focus:w-96 lg:focus:w-[420px]',
            inputClassName
          )}
        />
        {query && (
          <button
            type="button"
            onClick={handleClear}
            className="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted/40 transition-colors"
            title="Clear search"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        )}
      </form>

      {/* Autocomplete Dropdown Popover */}
      {isOpen && (
        <div className="absolute left-0 right-0 top-full mt-2 z-50 bg-white/95 backdrop-blur-xl border border-secondary/25 rounded-2xl shadow-2xl overflow-hidden animate-in fade-in slide-in-from-top-2 duration-200 min-w-[320px] max-w-full md:max-w-[480px]">
          {/* Header indicator bar */}
          <div className="h-1 w-full bg-gradient-to-r from-secondary/40 via-secondary to-secondary/40" />

          <div className="max-h-[70vh] overflow-y-auto divide-y divide-border/40 text-foreground p-2">
            {/* EMPTY STATE: POPULAR SEARCHES & QUICK OCCASIONS */}
            {!normalizedQuery && (
              <div className="p-3 space-y-4">
                <div>
                  <div className="flex items-center gap-1.5 text-xs font-bold text-secondary uppercase tracking-wider mb-2.5">
                    <TrendingUp className="h-3.5 w-3.5" />
                    <span>Popular Searches</span>
                  </div>
                  <div className="flex flex-wrap gap-1.5">
                    {POPULAR_SEARCH_TAGS.map((tag) => (
                      <button
                        key={tag.label}
                        type="button"
                        onClick={() => {
                          setQuery(tag.query);
                          setIsOpen(true);
                          inputRef.current?.focus();
                        }}
                        className="px-3 py-1.5 rounded-full text-xs font-medium bg-muted/30 hover:bg-secondary/15 hover:text-secondary border border-border/50 transition-all active:scale-95 text-foreground/80"
                      >
                        {tag.label}
                      </button>
                    ))}
                  </div>
                </div>

                <div className="pt-2 border-t border-border/40">
                  <div className="flex items-center gap-1.5 text-xs font-bold text-secondary uppercase tracking-wider mb-2.5">
                    <Sparkles className="h-3.5 w-3.5" />
                    <span>Curated Occasions & Services</span>
                  </div>
                  <div className="grid grid-cols-1 gap-1">
                    {OCCASIONS.slice(0, 3).map((occ) => {
                      const IconComp = occ.icon;
                      return (
                        <div
                          key={occ.id}
                          onClick={() => handleSelectLink(occ.link)}
                          className="flex items-center justify-between p-2 rounded-xl hover:bg-secondary/10 cursor-pointer transition-colors group"
                        >
                          <div className="flex items-center gap-2.5 min-w-0">
                            <div className="h-8 w-8 rounded-lg bg-secondary/15 text-secondary flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                              <IconComp className="h-4 w-4" />
                            </div>
                            <div className="min-w-0">
                              <p className="text-xs font-semibold text-primary truncate group-hover:text-secondary transition-colors">
                                {occ.title}
                              </p>
                              <p className="text-[11px] text-muted-foreground truncate">{occ.subtitle}</p>
                            </div>
                          </div>
                          <span className="text-[10px] font-bold text-secondary bg-secondary/10 px-2 py-0.5 rounded-full uppercase shrink-0 ml-2">
                            {occ.tag}
                          </span>
                        </div>
                      );
                    })}
                  </div>
                </div>
              </div>
            )}

            {/* QUERY HAS RESULTS */}
            {normalizedQuery && hasMatches && (
              <div className="space-y-3 py-2">
                {/* 1. Products Section */}
                {matchingProducts.length > 0 && (
                  <div className="px-2">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-secondary px-2 mb-1.5">
                      Matching Products ({matchingProducts.length})
                    </p>
                    <div className="space-y-1">
                      {matchingProducts.map((product, pIdx) => {
                        const isSelected = selectedIndex === pIdx;
                        const productImg =
                          product.image ||
                          (Array.isArray(product.images) && product.images[0]) ||
                          'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_8fb5dcf8-22bd-4fbd-98ba-1611bfcdcc4d.jpg';

                        return (
                          <div
                            key={product.id || product.slug}
                            onClick={() => handleSelectLink(`/product/${product.slug}`)}
                            className={cn(
                              'flex items-center justify-between p-2 rounded-xl cursor-pointer transition-all group',
                              isSelected ? 'bg-secondary/20 ring-1 ring-secondary' : 'hover:bg-secondary/10'
                            )}
                          >
                            <div className="flex items-center gap-3 min-w-0">
                              <img
                                src={productImg}
                                alt={product.name}
                                className="h-11 w-11 rounded-lg object-cover border border-border/40 shrink-0 group-hover:scale-105 transition-transform"
                              />
                              <div className="min-w-0">
                                <p className="text-xs font-bold text-primary truncate group-hover:text-secondary transition-colors">
                                  {product.name}
                                </p>
                                <p className="text-[11px] text-muted-foreground truncate capitalize">
                                  {String(product.category || '').replace(/-/g, ' ')}
                                </p>
                              </div>
                            </div>
                            <div className="flex items-center gap-2 shrink-0 ml-2">
                              <span className="text-xs font-bold text-primary">
                                {formatINR(product.price)}
                              </span>
                              <ChevronRight className="h-3.5 w-3.5 text-muted-foreground group-hover:text-secondary group-hover:translate-x-0.5 transition-all" />
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}

                {/* 2. Occasions & Services Section */}
                {matchingOccasions.length > 0 && (
                  <div className="px-2 pt-2 border-t border-border/40">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-secondary px-2 mb-1.5">
                      Occasions & Services
                    </p>
                    <div className="space-y-1">
                      {matchingOccasions.map((occ, oIdx) => {
                        const itemIdx = matchingProducts.length + oIdx;
                        const isSelected = selectedIndex === itemIdx;
                        const IconComp = occ.icon;

                        return (
                          <div
                            key={occ.id}
                            onClick={() => handleSelectLink(occ.link)}
                            className={cn(
                              'flex items-center justify-between p-2 rounded-xl cursor-pointer transition-all group',
                              isSelected ? 'bg-secondary/20 ring-1 ring-secondary' : 'hover:bg-secondary/10'
                            )}
                          >
                            <div className="flex items-center gap-2.5 min-w-0">
                              <div className="h-8 w-8 rounded-lg bg-secondary/15 text-secondary flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                <IconComp className="h-4 w-4" />
                              </div>
                              <div className="min-w-0">
                                <p className="text-xs font-bold text-primary truncate group-hover:text-secondary transition-colors">
                                  {occ.title}
                                </p>
                                <p className="text-[11px] text-muted-foreground truncate">{occ.subtitle}</p>
                              </div>
                            </div>
                            <span className="text-[10px] font-bold text-secondary bg-secondary/10 px-2 py-0.5 rounded-full uppercase shrink-0 ml-2">
                              {occ.tag}
                            </span>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}

                {/* 3. Collections Section */}
                {matchingCollections.length > 0 && (
                  <div className="px-2 pt-2 border-t border-border/40">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-secondary px-2 mb-1.5">
                      Collections
                    </p>
                    <div className="space-y-1">
                      {matchingCollections.map((col, cIdx) => {
                        const itemIdx = matchingProducts.length + matchingOccasions.length + cIdx;
                        const isSelected = selectedIndex === itemIdx;

                        return (
                          <div
                            key={col.category}
                            onClick={() => handleSelectLink(col.link)}
                            className={cn(
                              'flex items-center justify-between p-2 rounded-xl cursor-pointer transition-all group',
                              isSelected ? 'bg-secondary/20 ring-1 ring-secondary' : 'hover:bg-secondary/10'
                            )}
                          >
                            <div className="flex items-center gap-2.5 min-w-0">
                              <div className="h-8 w-8 rounded-lg bg-amber-500/15 text-amber-500 flex items-center justify-center shrink-0">
                                <Layers className="h-4 w-4" />
                              </div>
                              <div className="min-w-0">
                                <p className="text-xs font-bold text-primary truncate group-hover:text-secondary transition-colors">
                                  {col.name}
                                </p>
                                <p className="text-[11px] text-muted-foreground truncate">{col.subtitle}</p>
                              </div>
                            </div>
                            <span className="text-[10px] font-bold text-muted-foreground bg-muted/40 px-2 py-0.5 rounded-full uppercase shrink-0 ml-2">
                              Collection
                            </span>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* NO MATCHES STATE */}
            {normalizedQuery && !hasMatches && (
              <div className="py-6 px-4 text-center space-y-2">
                <div className="h-10 w-10 mx-auto rounded-full bg-secondary/10 flex items-center justify-center text-secondary">
                  <Search className="h-5 w-5" />
                </div>
                <p className="text-sm font-bold text-primary">No exact matches for "{query}"</p>
                <p className="text-xs text-muted-foreground max-w-xs mx-auto">
                  Try searching for flowers, roses, hampers, proposals, or midnight delivery.
                </p>
                <div className="pt-2">
                  <button
                    type="button"
                    onClick={() => handleSelectLink('/products')}
                    className="text-xs font-bold text-secondary hover:underline inline-flex items-center gap-1"
                  >
                    Browse All Collections <ArrowRight className="h-3 w-3" />
                  </button>
                </div>
              </div>
            )}
          </div>

          {/* Bottom Action Footer */}
          {normalizedQuery && (
            <div
              onClick={() => handleSubmit()}
              className="p-3 bg-muted/20 border-t border-border/50 flex items-center justify-between cursor-pointer hover:bg-secondary/15 transition-colors group"
            >
              <div className="flex items-center gap-2 text-xs font-semibold text-primary group-hover:text-secondary">
                <Search className="h-3.5 w-3.5 text-secondary" />
                <span>
                  See all catalog results for <strong className="font-bold underline text-secondary">"{query}"</strong>
                </span>
              </div>
              <ArrowRight className="h-4 w-4 text-secondary group-hover:translate-x-1 transition-transform" />
            </div>
          )}
        </div>
      )}
    </div>
  );
}
