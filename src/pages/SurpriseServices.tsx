import { useState, useEffect } from "react";
import { useSearchParams } from 'react-router-dom';
import { cn } from "@/lib/utils";
import {
  Calendar,
  Users,
  MessageSquare
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { useAuth } from '@/contexts/AuthContext';

export default function SurpriseServices() {
  const { user } = useAuth();
  const { toast } = useToast();
  const [searchParams] = useSearchParams();
  const [formData, setFormData] = useState({
    name: user?.name || '',
    email: user?.email || '',
    contactNo: user?.phone || '',
    eventType: searchParams.get('eventType') || 'Proposal Planning & Surprise',
    serviceName: '',
    address: '',
    city: '',
    message: ''
  });

  // Auto-fill when user data is available
  useEffect(() => {
    if (user) {
      setFormData(prev => ({
        ...prev,
        name: prev.name || user.name || '',
        email: prev.email || user.email || '',
        contactNo: prev.contactNo || user.phone || '',
      }));
    }
  }, [user]);

  useEffect(() => {
    const eventType = searchParams.get('eventType');
    if (eventType) {
      setFormData(prev => ({ ...prev, eventType }));
    }
  }, [searchParams]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  const validateForm = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.name.trim()) {
      newErrors.name = 'Name is required';
    }

    if (!formData.email.trim()) {
      newErrors.email = 'Email is required';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = 'Please enter a valid email address';
    }

    if (!formData.contactNo.trim()) {
      newErrors.contactNo = 'Contact number is required';
    } else if (!/^[+\d][\d\s\-]{6,19}$/.test(formData.contactNo.trim())) {
      newErrors.contactNo = 'Please enter a valid contact number';
    }

    if (!formData.message.trim()) {
      newErrors.message = 'Please tell us about your event';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) {
      toast({
        title: "Validation Error",
        description: "Please fill in all required fields correctly.",
        variant: "destructive",
      });
      return;
    }

    setIsSubmitting(true);

    try {
      const res = await fetch('/backend/inquiries.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData),
      });

      const text = await res.text();
      let data: any = {};
      try { data = JSON.parse(text); } catch { /* non-JSON response */ }

      if (!res.ok) throw new Error(data.error || `Server error (${res.status})`);

      toast({
        title: 'Inquiry Submitted!',
        description: 'Our event specialist will contact you within 24 hours.',
      });
      setFormData({
        name: user?.name || '',
        email: user?.email || '',
        contactNo: user?.phone || '',
        eventType: 'Proposal Planning & Surprise',
        serviceName: '',
        address: '',
        city: '',
        message: ''
      });
      setErrors({});
    } catch (err: any) {
      toast({
        title: 'Submission Failed',
        description: err.message || 'Please try again.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleChange = (field: string, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Clear error when user starts typing
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }));
    }
  };
  return (
    <div className="flex flex-col min-h-screen bg-background">
      {/* Hero */}
      <section className="relative h-[60vh] flex items-center justify-center overflow-hidden bg-primary text-white">
        <div className="absolute inset-0 opacity-40">
          <img
            src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_0d1f5bf9-c3e6-4678-b943-f496a294145d.jpg"
            alt="Luxury Event Decor"
            className="w-full h-full object-cover"
          />
        </div>
        <div className="container relative z-10 text-center">
          <Badge className="bg-secondary text-primary font-bold px-4 py-1 mb-6">Event Service</Badge>
          <h1 className="text-5xl md:text-7xl font-bold font-serif-luxury italic mb-6">Mastering the Art of <span className="gradient-text">Surprise</span></h1>
          <p className="text-xl text-white/80 max-w-2xl mx-auto leading-relaxed">
            From intimate proposals to grand celebrations, we design and coordinate unforgettable moments that take their breath away.
          </p>
        </div>
      </section>

      {/* Process Section */}
      <section className="py-24 bg-primary text-white">
        <div className="container">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div>
              <h2 className="text-4xl font-bold font-serif-luxury italic mb-8">How It Works</h2>
              <div className="space-y-8">
                <ProcessStep
                  number="01"
                  title="The Consultation"
                  description="Share your vision with our event team. We listen to your story and understand the sentiment you want to convey."
                />
                <ProcessStep
                  number="02"
                  title="The Design"
                  description="Our planners create a detailed mood board and itinerary, selecting the perfect blooms, venue, and vendors."
                />
                <ProcessStep
                  number="03"
                  title="The Execution"
                  description="On the big day, our team manages everything behind the scenes, ensuring every petal is in place for the big reveal."
                />
              </div>
            </div>
            <div className="relative">
              <div className="aspect-[4/5] rounded-2xl overflow-hidden gold-border p-2">
                <img src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_71ed4da9-9ce1-4430-b4a5-15e4fbfc8ba3.jpg" alt="Event Planning" className="h-full w-full object-cover rounded-xl" />
              </div>
              <div className="absolute -bottom-10 -left-10 bg-secondary p-8 rounded-2xl hidden md:block">
                <p className="text-primary font-bold text-4xl mb-2">99%</p>
                <p className="text-primary/70 text-sm font-medium">Surprise Success Rate</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Contact Form */}
      <section className="py-24 bg-white">
        <div className="container max-w-4xl">
          <div className="bg-white luxury-shadow rounded-3xl overflow-hidden border">
            <div className="grid grid-cols-1 md:grid-cols-2">
              <div className="p-12 bg-primary text-white space-y-8">
                <h3 className="text-3xl font-bold">Start Your Journey</h3>
                <p className="text-white/70">Fill out the form and a dedicated event specialist will contact you within 24 hours.</p>

                <div className="space-y-6">
                  <div className="flex items-center gap-4">
                    <MessageSquare className="h-5 w-5 text-secondary" />
                    <span>Expert Guidance</span>
                  </div>
                  <div className="flex items-center gap-4">
                    <Users className="h-5 w-5 text-secondary" />
                    <span>Dedicated Team</span>
                  </div>
                  <div className="flex items-center gap-4">
                    <Calendar className="h-5 w-5 text-secondary" />
                    <span>Flexible Dates</span>
                  </div>
                </div>
              </div>
              <div className="p-12 bg-white">
                <form onSubmit={handleSubmit} className="space-y-6">
                  <div className="space-y-2">
                    <label htmlFor="ev-name" className="text-sm font-medium text-foreground">
                      Your Name <span className="text-destructive">*</span>
                    </label>
                    <Input
                      id="ev-name"
                      placeholder="Enter your name"
                      className={cn("h-12", errors.name && "border-destructive")}
                      value={formData.name}
                      onChange={(e) => handleChange('name', e.target.value)}
                    />
                    {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                  </div>
                  <div className="space-y-2">
                    <label htmlFor="ev-email" className="text-sm font-medium text-foreground">
                      Email Address <span className="text-destructive">*</span>
                    </label>
                    <Input
                      id="ev-email"
                      type="email"
                      placeholder="Enter your email"
                      className={cn("h-12", errors.email && "border-destructive")}
                      value={formData.email}
                      onChange={(e) => handleChange('email', e.target.value)}
                    />
                    {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                  </div>
                  <div className="space-y-2">
                    <label htmlFor="ev-contact" className="text-sm font-medium text-foreground">
                      Contact No <span className="text-destructive">*</span>
                    </label>
                    <Input
                      id="ev-contact"
                      type="tel"
                      placeholder="Enter your contact number"
                      className={cn("h-12", errors.contactNo && "border-destructive")}
                      value={formData.contactNo}
                      onChange={(e) => handleChange('contactNo', e.target.value)}
                    />
                    {errors.contactNo && <p className="text-sm text-destructive">{errors.contactNo}</p>}
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-2">
                      <label htmlFor="ev-address" className="text-sm font-medium text-foreground">Address</label>
                      <Input
                        id="ev-address"
                        placeholder="Area, Street, Sector"
                        className="h-12"
                        value={formData.address}
                        onChange={(e) => handleChange('address', e.target.value)}
                      />
                    </div>
                    <div className="space-y-2">
                      <label htmlFor="ev-city" className="text-sm font-medium text-foreground">City</label>
                      <Input
                        id="ev-city"
                        placeholder="Enter City"
                        className="h-12"
                        value={formData.city}
                        onChange={(e) => handleChange('city', e.target.value)}
                      />
                    </div>
                  </div>

                  <div className="space-y-2">
                    <label htmlFor="ev-type" className="text-sm font-medium text-foreground">Event Type</label>
                    <select
                      id="ev-type"
                      className="w-full h-12 rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-secondary"
                      value={formData.eventType}
                      onChange={(e) => handleChange('eventType', e.target.value)}
                    >
                      <option>Proposal Planning & Surprise</option>
                      <option>Anniversary Dinner</option>
                      <option>Birthday Celebration</option>
                      <option>Midnight Surprises</option>
                      <option>Bespoke Gifting</option>
                      <option>Event Styling</option>
                      <option>Corporate Gala</option>
                      <option>Live Musicians</option>
                      <option>Moments Captured</option>
                      <option>Other</option>
                    </select>
                  </div>
                  {formData.eventType === 'Other' && (
                    <div className="space-y-2">
                      <label htmlFor="ev-service" className="text-sm font-medium text-foreground">
                        Event Name <span className="text-destructive">*</span>
                      </label>
                      <Input
                        id="ev-service"
                        placeholder="Enter your event name"
                        className="h-12"
                        value={formData.serviceName}
                        onChange={(e) => handleChange('serviceName', e.target.value)}
                      />
                    </div>
                  )}
                  <div className="space-y-2">
                    <label htmlFor="ev-msg" className="text-sm font-medium text-foreground">
                      Tell us more <span className="text-destructive">*</span>
                    </label>
                    <Textarea
                      id="ev-msg"
                      placeholder="Tell us about your dream surprise..."
                      className={cn("min-h-[100px]", errors.message && "border-destructive")}
                      value={formData.message}
                      onChange={(e) => handleChange('message', e.target.value)}
                    />
                    {errors.message && <p className="text-sm text-destructive">{errors.message}</p>}
                  </div>
                  <Button
                    type="submit"
                    variant="secondary"
                    className="w-full h-14 font-bold text-lg rounded-full mt-4"
                    disabled={isSubmitting}
                  >
                    {isSubmitting ? 'Submitting...' : 'Inquire Now'}
                  </Button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}

function ProcessStep({ number, title, description }: { number: string; title: string; description: string }) {
  return (
    <div className="flex gap-6">
      <span className="text-4xl font-bold text-secondary/40 font-serif-luxury italic leading-none">{number}</span>
      <div>
        <h4 className="text-xl font-bold mb-2">{title}</h4>
        <p className="text-white/60 text-sm leading-relaxed">{description}</p>
      </div>
    </div>
  );
}

function Badge({ children, className }: { children: React.ReactNode; className?: string }) {
  return (
    <span className={cn("inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold transition-colors", className)}>
      {children}
    </span>
  );
}



