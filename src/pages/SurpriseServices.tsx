import { useState, useEffect, useRef } from "react";
import { useSearchParams } from 'react-router-dom';
import { cn } from "@/lib/utils";
import {
  Calendar,
  MessageSquare,
  Sparkles,
  Music,
  Camera,
  Gift,
  Heart,
  CheckCircle2,
  ArrowRight,
  Sliders,
  ShieldCheck,
  PhoneCall,
  MapPin,
  Check,
  AlertCircle
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { useAuth } from '@/contexts/AuthContext';
import { inquiryService, surpriseService } from '@/services/api';

const ICON_MAP: Record<string, any> = {
  Music: Music,
  Camera: Camera,
  Gift: Gift,
  Sparkles: Sparkles,
  Heart: Heart,
  Star: Sparkles,
};

export default function SurpriseServices() {
  const { user } = useAuth();
  const { toast } = useToast();
  const [searchParams] = useSearchParams();
  const formRef = useRef<HTMLDivElement>(null);

  // Dynamic Data States
  const [packages, setPackages] = useState<any[]>([]);
  const [upgrades, setUpgrades] = useState<any[]>([]);
  const [selectedPackage, setSelectedPackage] = useState<any>(null);

  // Requirement 4: NO upgrades selected by default!
  const [selectedAddons, setSelectedAddons] = useState<number[]>([]);
  const [loadingData, setLoadingData] = useState(true);

  // Requirement 7: Pincode state and validation
  const [pincode, setPincode] = useState('');
  const [pincodeStatus, setPincodeStatus] = useState<{ valid?: boolean; message?: string; loading?: boolean }>({});

  const [formData, setFormData] = useState({
    name: user?.name || '',
    email: user?.email || '',
    contactNo: user?.phone || '',
    eventType: searchParams.get('eventType') || 'Proposal Planning & Surprise',
    serviceName: '',
    address: '',
    city: 'Bengaluru',
    pincode: '',
    message: ''
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    fetchSurpriseData();
  }, []);

  const fetchSurpriseData = async () => {
    try {
      setLoadingData(true);
      const res = await surpriseService.getData();
      if (res && res.success) {
        setPackages(res.experiences || []);
        if (res.experiences && res.experiences.length > 0) {
          setSelectedPackage(res.experiences[0]);
        }
        setUpgrades(res.upgrades || []);
      }
    } catch (e) {
      console.error("Failed to load surprise configuration:", e);
    } finally {
      setLoadingData(false);
    }
  };

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

  // Requirement 5: Calculate estimated total price dynamically
  const baseCost = selectedPackage ? (selectedPackage.base_price || selectedPackage.basePrice || 0) : 0;
  const addonsCost = selectedAddons.reduce((sum, upgId) => {
    const upg = upgrades.find(u => u.id === upgId);
    return sum + (upg ? (upg.price || 0) : 0);
  }, 0);
  const totalPrice = baseCost + addonsCost;

  const toggleAddon = (upgId: number) => {
    setSelectedAddons(prev =>
      prev.includes(upgId) ? prev.filter(id => id !== upgId) : [...prev, upgId]
    );
  };

  // Requirement 7: Pincode field validation
  const handlePincodeChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const rawVal = e.target.value;
    // Only allow numeric digits, max length 6
    const numericOnly = rawVal.replace(/\D/g, '').slice(0, 6);
    setPincode(numericOnly);
    setFormData(prev => ({ ...prev, pincode: numericOnly }));

    if (errors.pincode) setErrors(prev => ({ ...prev, pincode: '' }));

    if (numericOnly.length === 6) {
      verifyPincode(numericOnly);
    } else {
      setPincodeStatus({});
    }
  };

  const verifyPincode = async (code: string) => {
    setPincodeStatus({ loading: true });
    try {
      const res = await surpriseService.checkPincode(code);
      if (res && res.valid) {
        setPincodeStatus({
          valid: true,
          message: "✅ Delivery Available",
          detail: "Same-day delivery is available for your location.",
          loading: false
        });
      } else {
        setPincodeStatus({
          valid: false,
          message: "❌ Sorry, we currently deliver only within Bengaluru.",
          loading: false
        });
      }
    } catch (err) {
      setPincodeStatus({
        valid: false,
        message: "❌ Sorry, we currently deliver only within Bengaluru.",
        loading: false
      });
    }
  };

  const scrollToFormWithPackage = (pkgTitle: string) => {
    setFormData(prev => ({
      ...prev,
      eventType: pkgTitle,
      message: `I'm interested in booking the "${pkgTitle}" package (Estimated Total: ₹${totalPrice.toLocaleString('en-IN')}). Please contact me with details.`
    }));
    formRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  const validateForm = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.name.trim()) newErrors.name = 'Name is required';
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

    // Pincode validation
    if (!pincode) {
      newErrors.pincode = 'Pincode is required';
    } else if (pincode.length !== 6) {
      newErrors.pincode = 'Pincode must be exactly 6 numeric digits';
    } else if (pincodeStatus.valid === false) {
      newErrors.pincode = 'We currently deliver only within Bengaluru';
    }

    if (!formData.message.trim()) {
      newErrors.message = 'Please tell us about your event details';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateForm()) {
      toast({
        title: "Validation Error",
        description: "Please enter a valid 6-digit Bengaluru pincode and fill out all required fields.",
        variant: "destructive",
      });
      return;
    }

    setIsSubmitting(true);
    try {
      const selectedAddonNames = selectedAddons
        .map(id => upgrades.find(u => u.id === id)?.name)
        .filter(Boolean)
        .join(', ');

      const data = await inquiryService.submit({
        ...formData,
        estimatedCost: totalPrice,
        selectedPackage: selectedPackage ? selectedPackage.title : 'Custom',
        addons: selectedAddonNames || 'None selected'
      });

      if (data.error) throw new Error(data.error);

      toast({
        title: "✨ Surprise Request Sent!",
        description: "Our Secret Experience Architect will contact you discreetly within 2 hours.",
      });

      setFormData({
        name: user?.name || '',
        email: user?.email || '',
        contactNo: user?.phone || '',
        eventType: 'Proposal Planning & Surprise',
        serviceName: '',
        address: '',
        city: 'Bengaluru',
        pincode: '',
        message: ''
      });
      setPincode('');
      setPincodeStatus({});
      setErrors({});
    } catch (err: any) {
      toast({
        title: 'Submission Failed',
        description: err.message || 'Please try again or call us directly.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleChange = (field: string, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    if (errors[field]) setErrors(prev => ({ ...prev, [field]: '' }));
  };

  return (
    <div className="min-h-screen bg-[#070D0A] text-white selection:bg-amber-500 selection:text-black font-sans">
      {/* Ambient background glow accents */}
      <div className="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div className="absolute top-[-10%] left-[20%] w-[500px] h-[500px] rounded-full bg-amber-500/10 blur-[140px]" />
        <div className="absolute top-[40%] right-[-5%] w-[600px] h-[600px] rounded-full bg-emerald-600/10 blur-[160px]" />
        <div className="absolute bottom-[-10%] left-[30%] w-[500px] h-[500px] rounded-full bg-amber-600/10 blur-[150px]" />
      </div>

      <div className="relative z-10">
        {/* HERO SECTION */}
        <section className="relative min-h-[85vh] flex items-center justify-center pt-16 pb-24 overflow-hidden border-b border-amber-500/20">
          <div className="absolute inset-0">
            <img
              src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_0d1f5bf9-c3e6-4678-b943-f496a294145d.jpg"
              alt="Luxury Surprise Event Decor"
              className="w-full h-full object-cover opacity-25 filter brightness-75 scale-105 transition-transform duration-10000 hover:scale-110"
            />
            <div className="absolute inset-0 bg-gradient-to-b from-[#070D0A]/70 via-[#070D0A]/90 to-[#070D0A]" />
          </div>

          <div className="container relative z-10 text-center max-w-5xl px-4">
            <div className="inline-flex items-center gap-2 px-5 py-2 rounded-full border border-amber-500/40 bg-amber-500/10 text-amber-300 font-semibold text-xs md:text-sm tracking-widest uppercase mb-8 backdrop-blur-md gold-glow">
              <Sparkles className="h-4 w-4 text-amber-400 animate-pulse" />
              Bespoke Surprise & Event Styling
            </div>

            <h1 className="text-5xl md:text-7xl lg:text-8xl font-bold font-serif tracking-tight text-white mb-8 leading-[1.08]">
              Mastering the Art of <br />
              <span className="gold-gradient-text italic font-serif">Unforgettable Magic</span>
            </h1>

            <p className="text-lg md:text-2xl text-emerald-100/80 max-w-3xl mx-auto leading-relaxed mb-12 font-light">
              From secret candlelit proposals to grand midnight celebrations. We design, coordinate, and orchestrate breathtaking moments that leave your loved ones speechless.
            </p>

            <div className="flex flex-wrap items-center justify-center gap-6">
              <Button
                size="lg"
                className="h-16 px-10 rounded-full bg-gradient-to-r from-amber-400 via-amber-500 to-amber-600 text-black font-bold text-lg hover:brightness-110 transition-all shadow-lg hover:scale-105 gold-glow"
                onClick={() => formRef.current?.scrollIntoView({ behavior: 'smooth' })}
              >
                Plan Your Surprise Now
                <ArrowRight className="ml-2 h-5 w-5" />
              </Button>
              <a href="tel:+918147736396">
                <Button
                  size="lg"
                  className="h-16 px-10 rounded-full bg-gradient-to-r from-amber-400 via-amber-500 to-amber-600 text-black font-bold text-lg hover:brightness-110 transition-all shadow-lg hover:scale-105 gold-glow"
                >
                  <PhoneCall className="mr-2 h-5 w-5" />
                  Direct Consultation
                </Button>
              </a>
            </div>

            {/* Quick Metrics */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-6 mt-16 pt-12 border-t border-amber-500/20 text-center">
              <div className="space-y-1">
                <p className="text-3xl md:text-4xl font-bold gold-gradient-text">100%</p>
                <p className="text-xs uppercase tracking-wider text-emerald-200/60">Secret & Flawless</p>
              </div>
              <div className="space-y-1">
                <p className="text-3xl md:text-4xl font-bold gold-gradient-text">1,200+</p>
                <p className="text-xs uppercase tracking-wider text-emerald-200/60">Surprises Executed</p>
              </div>
              <div className="space-y-1">
                <p className="text-3xl md:text-4xl font-bold gold-gradient-text">4.9 ★</p>
                <p className="text-xs uppercase tracking-wider text-emerald-200/60">Customer Rating</p>
              </div>
              <div className="space-y-1">
                <p className="text-3xl md:text-4xl font-bold gold-gradient-text">2 Hours</p>
                <p className="text-xs uppercase tracking-wider text-emerald-200/60">Fast Specialist Reply</p>
              </div>
            </div>
          </div>
        </section>

        {/* INTERACTIVE EXPERIENCE ESTIMATOR & BUILDER */}
        <section className="py-24 bg-black/40 border-b border-amber-500/10">
          <div className="container max-w-6xl px-4">
            <div className="text-center mb-16 space-y-4">
              <span className="text-amber-400 font-semibold text-xs tracking-widest uppercase">Interactive Builder</span>
              <h2 className="text-4xl md:text-5xl font-bold font-serif text-white">
                Customize Your <span className="gold-gradient-text italic">Surprise Package</span>
              </h2>
              <p className="text-emerald-100/70 max-w-2xl mx-auto">
                Select your preferred surprise concept and add optional live performance or media upgrades for instant pricing estimation.
              </p>
            </div>

            {loadingData ? (
              <div className="text-center py-16 text-amber-400 font-semibold text-sm animate-pulse">
                Loading live surprise experience builder...
              </div>
            ) : (
              <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                {/* Package Selector */}
                <div className="lg:col-span-7 space-y-6">
                  <h3 className="text-xl font-bold text-amber-300 flex items-center gap-2">
                    <Sliders className="h-5 w-5 text-amber-400" />
                    1. Choose Base Surprise Experience
                  </h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {packages.map((pkg) => {
                      const isSelected = selectedPackage && selectedPackage.id === pkg.id;
                      const price = pkg.base_price || pkg.basePrice || 0;
                      return (
                        <div
                          key={pkg.id}
                          onClick={() => setSelectedPackage(pkg)}
                          className={cn(
                            "p-5 rounded-2xl cursor-pointer transition-all duration-300 relative border flex flex-col justify-between",
                            isSelected
                              ? "bg-amber-500/15 border-amber-400 shadow-lg gold-glow scale-[1.02]"
                              : "bg-emerald-950/20 border-amber-500/20 hover:border-amber-500/50 hover:bg-emerald-900/20"
                          )}
                        >
                          <div>
                            <div className="flex justify-between items-start mb-2">
                              {pkg.badge && (
                                <span className="text-xs font-bold px-2.5 py-1 rounded-full bg-amber-400/20 text-amber-300 border border-amber-400/30">
                                  {pkg.badge}
                                </span>
                              )}
                              <span className="text-lg font-bold text-amber-400 ml-auto">
                                ₹{price.toLocaleString('en-IN')}
                              </span>
                            </div>
                            <h4 className="text-lg font-bold text-white mb-2">{pkg.title}</h4>
                            {pkg.subtitle && <p className="text-xs text-amber-200/80 italic mb-2">{pkg.subtitle}</p>}
                            <p className="text-xs text-emerald-100/70 line-clamp-3 leading-relaxed mb-4">
                              {pkg.description}
                            </p>
                          </div>
                          <div className="text-xs font-semibold flex items-center text-amber-300 pt-2 border-t border-white/5">
                            {isSelected ? '✓ Selected Experience' : 'Click to select'}
                          </div>
                        </div>
                      );
                    })}
                  </div>

                  {/* Addons Selection */}
                  <div className="pt-6 space-y-4">
                    <h3 className="text-xl font-bold text-amber-300 flex items-center gap-2">
                      <Sparkles className="h-5 w-5 text-amber-400" />
                      2. Add Magical Experience Upgrades
                    </h3>
                    <p className="text-xs text-emerald-100/60 italic">
                      Select optional upgrades below. No upgrade is pre-selected.
                    </p>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                      {upgrades.map((addon) => {
                        const IconComponent = ICON_MAP[addon.icon] || Sparkles;
                        const isSelected = selectedAddons.includes(addon.id);
                        const price = addon.price || 0;
                        return (
                          <div
                            key={addon.id}
                            onClick={() => toggleAddon(addon.id)}
                            className={cn(
                              "p-4 rounded-xl cursor-pointer transition-all flex items-center justify-between border",
                              isSelected
                                ? "bg-amber-500/20 border-amber-400 text-white shadow"
                                : "bg-emerald-950/10 border-amber-500/15 text-emerald-100/80 hover:border-amber-500/30"
                            )}
                          >
                            <div className="flex items-center gap-3">
                              <div className={cn("p-2 rounded-lg", isSelected ? "bg-amber-400 text-black" : "bg-white/5 text-amber-400")}>
                                <IconComponent className="h-5 w-5" />
                              </div>
                              <div>
                                <p className="text-sm font-semibold">{addon.name}</p>
                                {addon.description && (
                                  <p className="text-[11px] text-emerald-100/60 line-clamp-1">{addon.description}</p>
                                )}
                                <p className="text-xs text-amber-400/90 font-medium">+₹{price.toLocaleString('en-IN')}</p>
                              </div>
                            </div>
                            <div className={cn("w-5 h-5 rounded-full border flex items-center justify-center text-xs font-bold shrink-0 ml-2", isSelected ? "border-amber-400 bg-amber-400 text-black" : "border-white/30")}>
                              {isSelected ? '✓' : ''}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                </div>

                {/* Estimate Summary Card */}
                <div className="lg:col-span-5 sticky top-28">
                  {selectedPackage && (
                    <div className="bg-[#0B1A13] p-8 rounded-3xl border border-amber-500/30 shadow-2xl relative overflow-hidden">
                      <div className="absolute top-0 right-0 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl pointer-events-none" />

                      <div className="aspect-video rounded-xl overflow-hidden mb-6 border border-amber-500/20 relative">
                        <img
                          src={selectedPackage.image}
                          alt={selectedPackage.title}
                          className="w-full h-full object-cover"
                        />
                        <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent" />
                        <div className="absolute bottom-3 left-4 right-4 flex justify-between items-center text-white">
                          <span className="text-sm font-bold font-serif">{selectedPackage.title}</span>
                          <span className="text-xs bg-amber-500 text-black px-2 py-0.5 rounded font-bold uppercase">Included</span>
                        </div>
                      </div>

                      <h3 className="text-2xl font-bold font-serif text-white mb-4">Estimated Summary</h3>

                      <div className="space-y-3 border-b border-white/10 pb-6 mb-6 text-sm text-emerald-100/80">
                        <div className="flex justify-between">
                          <span>{selectedPackage.title}</span>
                          <span className="font-semibold text-white">₹{(selectedPackage.base_price || selectedPackage.basePrice || 0).toLocaleString('en-IN')}</span>
                        </div>
                        {selectedAddons.map(id => {
                          const addon = upgrades.find(a => a.id === id);
                          if (!addon) return null;
                          return (
                            <div key={id} className="flex justify-between text-xs text-amber-300/90">
                              <span>+ {addon.name}</span>
                              <span>₹{(addon.price || 0).toLocaleString('en-IN')}</span>
                            </div>
                          );
                        })}
                      </div>

                      <div className="flex items-baseline justify-between mb-8">
                        <div>
                          <p className="text-xs text-emerald-200/60 uppercase tracking-widest font-semibold">Estimated Total</p>
                          <p className="text-xs text-amber-400 italic">Includes on-site styling & coordination</p>
                        </div>
                        <p className="text-3xl font-extrabold gold-gradient-text">
                          ₹{totalPrice.toLocaleString('en-IN')}
                        </p>
                      </div>

                      <Button
                        size="lg"
                        className="w-full h-14 rounded-full bg-gradient-to-r from-amber-400 to-amber-600 text-black font-bold text-base hover:brightness-110 shadow-lg gold-glow"
                        onClick={() => scrollToFormWithPackage(selectedPackage.title)}
                      >
                        Reserve This Custom Experience
                        <ArrowRight className="ml-2 h-5 w-5" />
                      </Button>

                      <p className="text-[11px] text-center text-emerald-200/50 mt-4 flex items-center justify-center gap-1">
                        <ShieldCheck className="h-4 w-4 text-amber-400" />
                        Zero Obligation • Instant Specialist Callback
                      </p>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        </section>

        {/* HOW IT WORKS PROCESS */}
        <section className="py-24 border-b border-amber-500/10 relative">
          <div className="container max-w-5xl px-4">
            <div className="text-center mb-16 space-y-4">
              <span className="text-amber-400 font-semibold text-xs tracking-widest uppercase">Secret Blueprint</span>
              <h2 className="text-4xl md:text-5xl font-bold font-serif text-white">
                How We Craft Your <span className="gold-gradient-text italic">Surprise</span>
              </h2>
              <p className="text-emerald-100/70 max-w-xl mx-auto">
                3 seamless steps to execute a flawless, high-emotion surprise without stress.
              </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
              <ProcessCard
                number="01"
                title="The Secret Brief"
                description="Share your vision, partner's personality, and desired emotional impact. We design a bespoke itinerary."
                icon={MessageSquare}
              />
              <ProcessCard
                number="02"
                title="Moodboard & Styling"
                description="Our florists & decor team curate rare blooms, neon styling, candle arrangements, and sound setups."
                icon={Sparkles}
              />
              <ProcessCard
                number="03"
                title="Flawless Reveal"
                description="Our hidden on-site coordinators handle setup, timing, music cueing, and photo capture flawlessly."
                icon={CheckCircle2}
              />
            </div>
          </div>
        </section>

        {/* PHOTO SHOWCASE GALLERY */}
        <section className="py-24 bg-black/50 border-b border-amber-500/10">
          <div className="container max-w-6xl px-4">
            <div className="text-center mb-16 space-y-4">
              <span className="text-amber-400 font-semibold text-xs tracking-widest uppercase">Past Magical Moments</span>
              <h2 className="text-4xl md:text-5xl font-bold font-serif text-white">
                Captured <span className="gold-gradient-text italic">Memories</span>
              </h2>
              <p className="text-emerald-100/70 max-w-xl mx-auto">
                Real reactions, candlelit walkways, and unforgettable "YES!" moments created by OMG.
              </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="group relative aspect-[4/5] rounded-2xl overflow-hidden border border-amber-500/20 shadow-xl">
                <img
                  src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_0d1f5bf9-c3e6-4678-b943-f496a294145d.jpg"
                  alt="Candlelit Romantic Proposal"
                  className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent p-6 flex flex-col justify-end">
                  <span className="text-xs font-bold text-amber-400 uppercase tracking-widest mb-1">Proposal Surprise</span>
                  <h4 className="text-lg font-bold text-white">Romantic Rose Archway</h4>
                </div>
              </div>

              <div className="group relative aspect-[4/5] rounded-2xl overflow-hidden border border-amber-500/20 shadow-xl md:translate-y-6">
                <img
                  src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_71ed4da9-9ce1-4430-b4a5-15e4fbfc8ba3.jpg"
                  alt="Midnight Doorstep Surprise"
                  className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent p-6 flex flex-col justify-end">
                  <span className="text-xs font-bold text-amber-400 uppercase tracking-widest mb-1">Midnight Surprise</span>
                  <h4 className="text-lg font-bold text-white">Doorstep Violinist Serenade</h4>
                </div>
              </div>

              <div className="group relative aspect-[4/5] rounded-2xl overflow-hidden border border-amber-500/20 shadow-xl">
                <img
                  src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_14558096-74be-4c1a-a8a2-e0334e6050d9.jpg"
                  alt="Private Rooftop Dining Setup"
                  className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent p-6 flex flex-col justify-end">
                  <span className="text-xs font-bold text-amber-400 uppercase tracking-widest mb-1">Anniversary Styling</span>
                  <h4 className="text-lg font-bold text-white">Glass Canopy Candlelight</h4>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* BOOKING / INQUIRY FORM */}
        <section ref={formRef} className="py-24 relative">
          <div className="container max-w-4xl px-4">
            <div className="bg-[#0B1A13] rounded-3xl p-8 md:p-14 border border-amber-500/30 shadow-2xl relative">
              <div className="text-center mb-12 space-y-3">
                <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-400/10 text-amber-300 text-xs font-bold uppercase tracking-widest border border-amber-400/20">
                  <Calendar className="h-4 w-4" />
                  Reserve Your Date
                </div>
                <h3 className="text-3xl md:text-5xl font-bold font-serif text-white">
                  Let's Create Something <span className="gold-gradient-text italic">Extraordinary</span>
                </h3>
                <p className="text-emerald-100/70 text-base max-w-lg mx-auto">
                  Fill out your details below and our lead surprise architect will contact you within 2 hours.
                </p>
              </div>

              <form onSubmit={handleSubmit} className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="space-y-2">
                    <label className="text-xs font-bold text-amber-200/90 uppercase tracking-wider">
                      Your Full Name <span className="text-amber-400">*</span>
                    </label>
                    <Input
                      placeholder="e.g. Rahul Sharma"
                      className={cn(
                        "h-14 bg-black/40 border-amber-500/20 text-white placeholder:text-white/30 rounded-xl focus:border-amber-400 focus:ring-amber-400/20",
                        errors.name && "border-red-500"
                      )}
                      value={formData.name}
                      onChange={(e) => handleChange('name', e.target.value)}
                    />
                    {errors.name && <p className="text-xs text-red-400">{errors.name}</p>}
                  </div>

                  <div className="space-y-2">
                    <label className="text-xs font-bold text-amber-200/90 uppercase tracking-wider">
                      Email Address <span className="text-amber-400">*</span>
                    </label>
                    <Input
                      type="email"
                      placeholder="rahul@example.com"
                      className={cn(
                        "h-14 bg-black/40 border-amber-500/20 text-white placeholder:text-white/30 rounded-xl focus:border-amber-400 focus:ring-amber-400/20",
                        errors.email && "border-red-500"
                      )}
                      value={formData.email}
                      onChange={(e) => handleChange('email', e.target.value)}
                    />
                    {errors.email && <p className="text-xs text-red-400">{errors.email}</p>}
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="space-y-2">
                    <label className="text-xs font-bold text-amber-200/90 uppercase tracking-wider">
                      Contact Number <span className="text-amber-400">*</span>
                    </label>
                    <Input
                      type="tel"
                      placeholder="+91 98765 43210"
                      className={cn(
                        "h-14 bg-black/40 border-amber-500/20 text-white placeholder:text-white/30 rounded-xl focus:border-amber-400 focus:ring-amber-400/20",
                        errors.contactNo && "border-red-500"
                      )}
                      value={formData.contactNo}
                      onChange={(e) => handleChange('contactNo', e.target.value)}
                    />
                    {errors.contactNo && <p className="text-xs text-red-400">{errors.contactNo}</p>}
                  </div>

                  <div className="space-y-2">
                    <label className="text-xs font-bold text-amber-200/90 uppercase tracking-wider">
                      Surprise Experience Type
                    </label>
                    <select
                      className="w-full h-14 rounded-xl bg-black/40 border border-amber-500/20 text-white px-4 text-sm focus:border-amber-400 outline-none"
                      value={formData.eventType}
                      onChange={(e) => handleChange('eventType', e.target.value)}
                    >
                      {packages.length > 0 ? (
                        packages.map(pkg => (
                          <option key={pkg.id} value={pkg.title} className="bg-gray-900 text-white">
                            {pkg.title}
                          </option>
                        ))
                      ) : (
                        <option className="bg-gray-900 text-white">Proposal Planning & Surprise</option>
                      )}
                    </select>
                  </div>
                </div>

                {/* REQUIREMENT 7: BENGALURU PINCODE VALIDATION FIELD */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="space-y-2">
                    <label className="text-xs font-bold text-amber-200/90 uppercase tracking-wider flex items-center justify-between">
                      <span>Bengaluru Delivery Pincode <span className="text-amber-400">*</span></span>
                    </label>
                    <div className="relative">
                      <Input
                        type="text"
                        maxLength={6}
                        placeholder="e.g. 560038"
                        className={cn(
                          "h-14 bg-black/40 border-amber-500/20 text-white placeholder:text-white/30 rounded-xl focus:border-amber-400 font-mono tracking-widest text-base pl-11",
                          errors.pincode && "border-red-500",
                          pincodeStatus.valid === true && "border-emerald-500 focus:border-emerald-400",
                          pincodeStatus.valid === false && "border-rose-500 focus:border-rose-400"
                        )}
                        value={pincode}
                        onChange={handlePincodeChange}
                      />
                      <MapPin className="absolute left-4 top-4 h-5 w-5 text-amber-400/60 pointer-events-none" />
                    </div>

                    {/* Live Validation Messages */}
                    {pincodeStatus.loading && (
                      <p className="text-xs text-amber-300 animate-pulse flex items-center gap-1">
                        Verifying Bengaluru service area...
                      </p>
                    )}
                    {pincodeStatus.valid === true && (
                      <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs space-y-0.5">
                        <p className="font-bold flex items-center gap-1.5 text-emerald-300">
                          <Check className="h-4 w-4 shrink-0 text-emerald-400" />
                          <span>{pincodeStatus.message}</span>
                        </p>
                        <p className="text-[11px] text-emerald-400/90 pl-5 font-medium">
                          {pincodeStatus.detail || "Same-day delivery is available for your location."}
                        </p>
                      </div>
                    )}
                    {pincodeStatus.valid === false && (
                      <div className="p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2">
                        <AlertCircle className="h-4 w-4 shrink-0 text-rose-400" />
                        <span>{pincodeStatus.message}</span>
                      </div>
                    )}
                    {errors.pincode && !pincodeStatus.message && (
                      <p className="text-xs text-red-400">{errors.pincode}</p>
                    )}
                  </div>

                  <div className="space-y-2">
                    <label className="text-xs font-bold text-amber-200/90 uppercase tracking-wider">
                      Delivery Address / Venue Details
                    </label>
                    <Input
                      placeholder="e.g. Hotel / Resort / Private Rooftop Address"
                      className="h-14 bg-black/40 border-amber-500/20 text-white placeholder:text-white/30 rounded-xl focus:border-amber-400"
                      value={formData.address}
                      onChange={(e) => handleChange('address', e.target.value)}
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <label className="text-xs font-bold text-amber-200/90 uppercase tracking-wider">
                    Tell Us About Your Vision <span className="text-amber-400">*</span>
                  </label>
                  <Textarea
                    placeholder="Share any special story, date, preferences, or secrets we should know..."
                    className={cn(
                      "min-h-[130px] bg-black/40 border-amber-500/20 text-white placeholder:text-white/30 rounded-xl focus:border-amber-400",
                      errors.message && "border-red-500"
                    )}
                    value={formData.message}
                    onChange={(e) => handleChange('message', e.target.value)}
                  />
                  {errors.message && <p className="text-xs text-red-400">{errors.message}</p>}
                </div>

                <Button
                  type="submit"
                  disabled={isSubmitting}
                  className="w-full h-16 rounded-full bg-gradient-to-r from-amber-400 via-amber-500 to-amber-600 text-black font-extrabold text-lg hover:brightness-110 shadow-xl gold-glow mt-4"
                >
                  {isSubmitting ? 'Submitting Your Brief...' : 'Request Private Specialist Call'}
                </Button>
              </form>
            </div>
          </div>
        </section>
      </div>
    </div>
  );
}

function ProcessCard({ number, title, description, icon: Icon }: { number: string; title: string; description: string; icon: any }) {
  return (
    <div className="bg-[#0B1A13] p-8 rounded-2xl border border-amber-500/20 hover:border-amber-500/50 transition-all hover:-translate-y-2 duration-300 relative group">
      <div className="flex justify-between items-start mb-6">
        <span className="text-4xl font-serif font-bold text-amber-400/40 group-hover:text-amber-400 transition-colors">
          {number}
        </span>
        <div className="p-3 rounded-xl bg-amber-400/10 text-amber-400 border border-amber-400/20">
          <Icon className="h-6 w-6" />
        </div>
      </div>
      <h4 className="text-xl font-bold text-white mb-3">{title}</h4>
      <p className="text-sm text-emerald-100/70 leading-relaxed">{description}</p>
    </div>
  );
}
