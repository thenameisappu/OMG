import { Link } from 'react-router-dom';
import { Instagram, Facebook, Youtube, Mail, Phone, MapPin } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useState } from 'react';
import { useToast } from '@/hooks/use-toast';
import { newsletterService } from '@/services/api';

export default function Footer() {
  const { toast } = useToast();
  const [email, setEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleNewsletterSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email) return;

    setIsSubmitting(true);
    try {
      const data = await newsletterService.subscribe(email);

      toast({
        title: "Subscribed!",
        description: data.message || "Thank you for subscribing to our newsletter.",
      });
      setEmail('');
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || "Something went wrong. Please try again.";
      toast({
        title: "Subscription Failed",
        description: errorMessage,
        variant: "destructive",
      });
    } finally {
      setIsSubmitting(false);
    }
  };
  return (
    <footer className="bg-primary text-primary-foreground pt-16 pb-8">
      {/* Newsletter Section */}
      <div className="container mb-16">
          <div className="bg-secondary/10 rounded-2xl p-6 md:p-12 text-center border border-secondary/20">
            <h3 className="text-2xl md:text-3xl font-bold mb-4">Stay in Bloom</h3>
            <p className="text-muted-foreground mb-6 max-w-xl mx-auto text-sm md:text-base">
              Subscribe to our newsletter for exclusive offers, floral tips, and early access to new collections.
            </p>
            <form onSubmit={handleNewsletterSubmit} className="flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
              <Input
                type="email"
                placeholder="Enter your email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="h-12 bg-white text-foreground flex-1"
                required
              />
              <Button type="submit" variant="secondary" className="h-12 px-8 whitespace-nowrap" disabled={isSubmitting}>
                {isSubmitting ? '...' : 'Subscribe'}
              </Button>
            </form>
          </div>
      </div>

      <div className="container grid grid-cols-2 md:grid-cols-4 gap-8 border-b border-border pb-12">
        <div className="space-y-4">
          <div className="flex items-center gap-2">
            <img
              src="/images/logo/footer-logo.png"
              alt="OMG (Oh My Gudness) Logo"
              className="h-14 w-auto object-contain"
            />
          </div>
          <p className="text-sm text-muted-foreground leading-relaxed">
            Delivering luxury floral arrangements, hampers, and surprise event planning in Bangalore, Karnataka.
            Crafting unforgettable moments since 2026.
          </p>
          <div className="flex items-center gap-4 pt-2">
            <a
              href="https://instagram.com/ohmygudness.in/"
              target="_blank"
              rel="noopener noreferrer"
            >
              <Instagram className="h-5 w-5 cursor-pointer hover:text-secondary transition-colors" />
            </a>

            <a
              href="https://www.facebook.com/people/Oh-My-Gudness/61591674733037/"
              target="_blank"
              rel="noopener noreferrer"
            >
              <Facebook className="h-5 w-5 cursor-pointer hover:text-secondary transition-colors" />
            </a>

            <a
              href="https://www.youtube.com/@ohmygudness_in"
              target="_blank"
              rel="noopener noreferrer"
            >
              <Youtube className="h-5 w-5 cursor-pointer hover:text-secondary transition-colors" />
            </a>
          </div>

        </div>

        <div>
          <h4 className="font-semibold mb-6 text-secondary">Quick Links</h4>
          <ul className="space-y-4 text-sm text-muted-foreground">
            <li><Link to="/about" className="hover:text-secondary transition-colors">About Us</Link></li>
            <li><Link to="/products" className="hover:text-secondary transition-colors">Shop Collection's</Link></li>
            <li><Link to="/products?category=flower-arrangements" className="hover:text-secondary transition-colors">Oh My Bloom's</Link></li>
            <li><Link to="/products?category=gift-hampers" className="hover:text-secondary transition-colors">Oh My Love's</Link></li>
            <li><Link to="/products?category=signature-collection" className="hover:text-secondary transition-colors">Oh My Signature's</Link></li>
            <li><Link to="/products?category=occasions" className="hover:text-secondary transition-colors">Oh My Moment's</Link></li>

          </ul>
        </div>

        <div>
          <h4 className="font-semibold mb-6 text-secondary">Customer Care</h4>
          <ul className="space-y-4 text-sm text-muted-foreground">
            <li><Link to="/delivery-info" className="hover:text-secondary transition-colors">Delivery Info</Link></li>
            <li><Link to="/faq" className="hover:text-secondary transition-colors">FAQ</Link></li>
            <li><Link to="/terms?tab=terms" className="hover:text-secondary transition-colors">Terms & Conditions</Link></li>
            <li><Link to="/terms?tab=privacy" className="hover:text-secondary transition-colors">Privacy Policy</Link></li>
            <li><Link to="/terms?tab=refund" className="hover:text-secondary transition-colors">Refund & Cancellation</Link></li>
            <li><Link to="/terms?tab=return" className="hover:text-secondary transition-colors">Return Policy</Link></li>
            <li><Link to="/terms?tab=shipping" className="hover:text-secondary transition-colors">Shipping Policy</Link></li>
          </ul>
        </div>

        <div className="space-y-4">
          <h4 className="font-semibold mb-6 text-secondary">Contact Us</h4>
          <a href="tel:+918147736396" className="flex items-center gap-3 text-sm text-muted-foreground hover:text-secondary transition-colors w-fit">
            <Phone className="h-4 w-4 text-secondary" />
            <span>+91 8147736396</span>
          </a>
          <a href="mailto:info@ohmygudness.in" className="flex items-center gap-3 text-sm text-muted-foreground hover:text-secondary transition-colors w-fit">
            <Mail className="h-4 w-4 text-secondary" />
            <span>info@ohmygudness.in</span>
          </a>
          <div className="flex items-center gap-3 text-sm text-muted-foreground">
            <MapPin className="h-4 w-4 text-secondary" />
            <span>Bangalore, Karnataka</span>
          </div>
        </div>
      </div>
      <div className="container mt-8 text-center text-xs text-muted-foreground">
        &copy; 2026 OMG (Oh My Gudness). All rights reserved.
      </div>
    </footer>
  );
}
