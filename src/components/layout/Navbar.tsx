import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { ShoppingCart, Menu, Search, Heart, User, LogOut, Phone, Sparkles, MapPin, PackageCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { useCart } from '@/contexts/CartContext';
import { useAuth } from '@/contexts/AuthContext';
import { useWishlist } from '@/contexts/WishlistContext';

const navItems = [
  { name: "Oh My Bloom's", href: '/products?category=flower-arrangements' },
  { name: "Oh My Love's", href: '/products?category=gift-hampers' },
  { name: "Oh My Signature's", href: '/products?category=signature-collection' },
  { name: "Oh My Moment's", href: '/products?category=occasions' },
  { name: "Oh My Customisation's", href: '/products?category=custom-orders' },
];

export default function Navbar() {
  const [isOpen, setIsOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const location = useLocation();
  const navigate = useNavigate();
  const { cartCount } = useCart();
  const { wishlistCount } = useWishlist();
  const { user, signOut, isAuthenticated } = useAuth();

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      navigate(`/products?search=${encodeURIComponent(searchQuery)}`);
      setSearchQuery('');
    }
  };

  const handleProfileClick = () => {
    navigate('/login');
  };

  const handleLogout = async () => {
    await signOut();
    navigate('/');
  };

  return (
    <nav className="sticky top-0 z-50 w-full bg-white/95 backdrop-blur-md border-b border-border shadow-sm">
      {/* Top Luxury Brand Utility Bar */}
      <div className="bg-primary text-white text-[11px] md:text-xs py-2 px-4 border-b border-secondary/20">
        <div className="container flex justify-between items-center">
          <div className="flex items-center gap-4">
            <span className="flex items-center gap-1 text-white/80">
              <MapPin className="h-3 w-3 text-secondary flex-shrink-0" />
              <span className="hidden sm:inline">Deliveries Active in Bangalore</span>
              <span className="sm:hidden">Bangalore Delivery</span>
            </span>
          </div>

          <div className="flex items-center gap-4 md:gap-6">
            <a href="tel:+918147736396" className="flex items-center gap-1 text-white/90 hover:text-secondary transition-colors font-medium">
              <Phone className="h-3 w-3 text-secondary" />
              <span className="hidden sm:inline">+91 8147736396</span>
              <span className="sm:hidden">Call Us</span>
            </a>
            {isAuthenticated && (
              <Link to="/orders" className="hidden md:flex items-center gap-1 text-white/80 hover:text-secondary transition-colors">
                <PackageCheck className="h-3.5 w-3.5 text-secondary" />
                <span>Track Order</span>
              </Link>
            )}
          </div>
        </div>
      </div>

      {/* Main Header */}
      <div className="container flex h-16 md:h-20 items-center justify-between gap-3">
        <div className="flex items-center gap-3 md:gap-8 min-w-0">
          <Link to="/" className="flex items-center gap-3 flex-shrink-0">
            <img
              src="/images/logo/logo-navbar.png"
              alt="OMG (Oh My Gudness) Logo"
              className="h-9 md:h-12 w-auto object-contain hover:scale-105 transition-transform"
            />
          </Link>

          {/* Desktop Search */}
          <div className="hidden lg:flex items-center relative">
            <form onSubmit={handleSearch} className="relative">
              <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <input
                type="search"
                placeholder="Search luxury flowers, hampers, moments..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="h-10 w-80 rounded-full border border-input bg-muted/20 text-foreground pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary transition-all"
              />
            </form>
          </div>
        </div>

        <div className="flex items-center gap-1 md:gap-3 flex-shrink-0">
          <Link to="/wishlist">
            <Button variant="ghost" size="icon" className="relative text-foreground hover:text-secondary hover:bg-secondary/10 rounded-full h-9 w-9 md:h-10 md:w-10">
              <Heart className="h-4 w-4 md:h-5 md:w-5" />
              {wishlistCount > 0 && (
                <span className="absolute -right-1 -top-1 flex h-4 w-4 md:h-5 md:w-5 items-center justify-center rounded-full bg-secondary text-[10px] font-extrabold text-primary shadow-md gold-glow">
                  {wishlistCount}
                </span>
              )}
            </Button>
          </Link>

          <Link to="/cart">
            <Button variant="ghost" size="icon" className="relative text-foreground hover:text-secondary hover:bg-secondary/10 rounded-full h-9 w-9 md:h-10 md:w-10">
              <ShoppingCart className="h-4 w-4 md:h-5 md:w-5" />
              {cartCount > 0 && (
                <span className="absolute -right-1 -top-1 flex h-4 w-4 md:h-5 md:w-5 items-center justify-center rounded-full bg-secondary text-[10px] font-extrabold text-primary shadow-md gold-glow">
                  {cartCount}
                </span>
              )}
            </Button>
          </Link>

          {/* Profile Dropdown */}
          {!isAuthenticated ? (
            <Button
              variant="ghost"
              size="icon"
              className="text-foreground hover:text-secondary hover:bg-secondary/10 rounded-full h-9 w-9 md:h-10 md:w-10"
              onClick={handleProfileClick}
            >
              <User className="h-4 w-4 md:h-5 md:w-5" />
            </Button>
          ) : (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="text-foreground hover:text-secondary hover:bg-secondary/10 rounded-full h-9 w-9 md:h-10 md:w-10">
                  <User className="h-4 w-4 md:h-5 md:w-5" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-52 p-2">
                <div className="px-3 py-2 text-xs font-semibold text-muted-foreground border-b border-border mb-1">
                  Logged in as <br />
                  <span className="text-sm font-bold text-primary truncate block">
                    {user?.name && user.name.trim() !== '' ? user.name : user?.email}
                  </span>
                </div>
                <DropdownMenuItem className="cursor-pointer py-2.5">
                  <Link to="/profile" className="w-full font-medium">My Profile</Link>
                </DropdownMenuItem>
                <DropdownMenuItem className="cursor-pointer py-2.5">
                  <Link to="/orders" className="w-full font-medium">My Orders</Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem className="text-destructive cursor-pointer py-2.5" onClick={handleLogout}>
                  <LogOut className="mr-2 h-4 w-4" />
                  Logout
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          )}

          <Sheet open={isOpen} onOpenChange={setIsOpen}>
            <SheetTrigger asChild className="lg:hidden">
              <Button variant="ghost" size="icon" className="text-foreground h-9 w-9">
                <Menu className="h-5 w-5" />
              </Button>
            </SheetTrigger>
            <SheetContent side="right" className="bg-primary text-white border-l border-secondary/20 p-6 flex flex-col justify-between">
              <div>
                <div className="flex items-center gap-3 mb-8 pt-4">
                  <img src="/images/logo/footer-logo.png" alt="OMG Logo" className="h-10 w-auto" />
                </div>
                <div className="flex flex-col gap-5">
                  {navItems.map((item) => (
                    <Link
                      key={item.name}
                      to={item.href}
                      onClick={() => setIsOpen(false)}
                      className="text-lg font-serif font-bold text-white/90 hover:text-secondary transition-colors border-b border-white/10 pb-3"
                    >
                      {item.name}
                    </Link>
                  ))}
                  <Link
                    to="/surprise-services"
                    onClick={() => setIsOpen(false)}
                    className="text-lg font-serif font-bold text-amber-300 hover:text-amber-200 transition-colors pt-2 flex items-center gap-2"
                  >
                    <Sparkles className="h-5 w-5 text-amber-400" />
                    Plan a Surprise
                  </Link>
                </div>
              </div>

              <div className="pt-6 border-t border-white/10 space-y-4">
                <a href="tel:+918147736396" className="flex items-center gap-3 text-secondary font-bold text-sm">
                  <Phone className="h-4 w-4" />
                  +91 8147736396
                </a>
                <p className="text-xs text-white/50">Bangalore's Premier Floral & Surprise House</p>
              </div>
            </SheetContent>
          </Sheet>
        </div>
      </div>

      {/* Mobile Search Bar */}
      <div className="lg:hidden border-t border-border bg-white px-4 py-2">
        <form onSubmit={handleSearch} className="relative">
          <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <input
            type="search"
            placeholder="Search flowers, hampers..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="h-9 w-full rounded-full border border-input bg-muted/20 text-foreground pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary transition-all"
          />
        </form>
      </div>

      {/* Category Bar - Desktop only, horizontally scrollable */}
      <div className="hidden lg:block border-t border-border bg-white">
        <div className="container">
          <div className="flex items-center gap-8 py-3.5 overflow-x-auto scrollbar-hide scroll-smooth">
            {navItems.map((item, index) => (
              <div key={item.name} className="flex items-center gap-8">
                <Link
                  to={item.href}
                  className={cn(
                    "text-sm font-semibold tracking-wide transition-all duration-300 hover:text-secondary relative group whitespace-nowrap flex-shrink-0 px-1",
                    location.pathname + location.search === item.href ? "text-secondary" : "text-foreground/80"
                  )}
                >
                  {item.name}
                  <span className={cn(
                    "absolute -bottom-3.5 left-0 h-0.5 bg-secondary transition-all duration-300",
                    location.pathname + location.search === item.href ? "w-full" : "w-0 group-hover:w-full"
                  )} />
                </Link>
                {index < navItems.length - 1 && (
                  <span className="text-muted-foreground/30 text-xs">|</span>
                )}
              </div>
            ))}
          </div>
        </div>
      </div>
    </nav>
  );
}
