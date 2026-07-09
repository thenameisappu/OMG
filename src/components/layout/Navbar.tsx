import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { ShoppingCart, Menu, Search, Heart, User, LogOut } from 'lucide-react';
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

const navItems = [
  { name: "Oh My Bloom's", href: '/products?category=flower-arrangements' },
  { name: "Oh My Love's", href: '/products?category=gift-hampers' },
  { name: "Oh My Signature's", href: '/products?category=signature-collection' },
  { name: "Oh My Celebration's", href: '/products?category=occasions' },
  { name: "Oh My Customisation's", href: '/products?category=custom-orders' },
];

export default function Navbar() {
  const [isOpen, setIsOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const location = useLocation();
  const navigate = useNavigate();
  const { cartCount } = useCart();
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
    <nav className="sticky top-0 z-50 w-full bg-background border-b border-border shadow-sm">
      {/* Main Header */}
      <div className="container flex h-20 items-center justify-between">
        <div className="flex items-center gap-8">
          <Link to="/" className="flex items-center gap-3">
            <img 
              src="https://miaoda-conversation-file.s3cdn.medo.dev/user-9f5q10y1rcao/conv-9f5q2s385ibk/20260206/file-9g120cd0i5fk.png" 
              alt="OMG (Oh My Gudness) Logo" 
              className="h-12 w-auto object-contain"
            />
          </Link>

          <div className="hidden lg:flex items-center relative">
            <form onSubmit={handleSearch} className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <input
                type="search"
                placeholder="Search for flowers, gifts..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="h-10 w-80 rounded-full border border-input bg-background text-foreground pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-secondary"
              />
            </form>
          </div>
        </div>

        <div className="flex items-center gap-4">
          <Link to="/wishlist">
            <Button variant="ghost" size="icon" className="text-foreground hover:text-secondary">
              <Heart className="h-5 w-5" />
            </Button>
          </Link>

          <Link to="/cart">
            <Button variant="ghost" size="icon" className="relative text-foreground hover:text-secondary">
              <ShoppingCart className="h-5 w-5" />
              {cartCount > 0 && (
                <span className="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-secondary text-[10px] font-bold text-primary">
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
              className="text-foreground hover:text-secondary"
              onClick={handleProfileClick}
            >
              <User className="h-5 w-5" />
            </Button>
          ) : (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="text-foreground hover:text-secondary">
                  <User className="h-5 w-5" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-48">
                <div className="px-2 py-1.5 text-sm font-semibold">
                  {user?.email}
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuItem>
                  <Link to="/profile" className="w-full">My Profile</Link>
                </DropdownMenuItem>
                <DropdownMenuItem>
                  <Link to="/orders" className="w-full">My Orders</Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem className="text-destructive" onClick={handleLogout}>
                  <LogOut className="mr-2 h-4 w-4" />
                  Logout
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          )}

          <Sheet open={isOpen} onOpenChange={setIsOpen}>
            <SheetTrigger asChild className="lg:hidden">
              <Button variant="ghost" size="icon" className="text-foreground">
                <Menu className="h-6 w-6" />
              </Button>
            </SheetTrigger>
            <SheetContent side="right" className="bg-background text-foreground">
              <div className="flex flex-col gap-6 pt-10">
                {navItems.map((item) => (
                  <Link
                    key={item.name}
                    to={item.href}
                    onClick={() => setIsOpen(false)}
                    className="text-lg font-medium hover:text-secondary transition-colors"
                  >
                    {item.name}
                  </Link>
                ))}
              </div>
            </SheetContent>
          </Sheet>
        </div>
      </div>

      {/* Category Bar - Horizontally Scrollable */}
      <div className="border-t border-border bg-white">
        <div className="container">
          <div className="relative">
            <div className="hidden lg:flex items-center gap-8 py-4 overflow-x-auto scrollbar-hide scroll-smooth">
              {navItems.map((item, index) => (
                <div key={item.name} className="flex items-center gap-8">
                  <Link
                    to={item.href}
                    className={cn(
                      "text-base font-medium transition-all duration-300 hover:text-secondary relative group whitespace-nowrap flex-shrink-0 px-2",
                      location.pathname + location.search === item.href ? "text-secondary" : "text-foreground/80"
                    )}
                  >
                    {item.name}
                    <span className={cn(
                    "absolute -bottom-4 left-0 h-0.5 bg-secondary transition-all duration-300",
                    location.pathname + location.search === item.href ? "w-full" : "w-0 group-hover:w-full"
                  )} />
                  </Link>
                  {index < navItems.length - 1 && (
                    <span className="text-muted-foreground/30">|</span>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </nav>
  );
}
