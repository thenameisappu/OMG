import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Sparkles, Calendar, Gift, Heart, Star, Check, ArrowRight,
  Crown, Repeat, Bell, ChevronDown, ChevronUp, Flower2, Clock
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { formatINR } from '@/lib/currency';
import { useCart } from '@/contexts/CartContext';
import { useAuth } from '@/contexts/AuthContext';
import { useToast } from '@/hooks/use-toast';
import { subscriptionService } from '@/services/api';

// ─── Static plan data (also fetched from API) ────────────────
const DEFAULT_PLANS = [
  {
    id: 1,
    name: 'Monthly Bloom',
    slug: 'monthly-bloom',
    tagline: 'Fresh joy, every month',
    frequency: 'monthly',
    deliveries_per_year: 12,
    price_per_delivery: 999,
    total_price: 11988,
    savings_percent: 0,
    is_popular: false,
    features_list: [
      '1 curated delivery per month',
      'Occasion-timed delivery',
      'Handpicked seasonal blooms',
      'Premium packaging & ribbon',
      'Digital occasion reminder',
      'Free delivery within Bangalore',
    ],
    badge: null,
    color: 'from-emerald-400 to-teal-500',
    lightColor: 'bg-emerald-50',
    borderColor: 'border-emerald-200',
    accentColor: 'text-emerald-600',
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_8fb5dcf8-22bd-4fbd-98ba-1611bfcdcc4d.jpg',
  },
  {
    id: 2,
    name: 'Quarterly Celebration',
    slug: 'quarterly-celebration',
    tagline: 'Four grand moments a year',
    frequency: 'quarterly',
    deliveries_per_year: 4,
    price_per_delivery: 1799,
    total_price: 7196,
    savings_percent: 20,
    is_popular: true,
    features_list: [
      '1 premium delivery every quarter',
      'Larger luxury arrangements',
      'Seasonal exclusive hampers',
      'Personalized message card',
      'Photo delivery confirmation',
      'Free priority delivery',
    ],
    badge: 'Most Popular',
    color: 'from-amber-400 to-orange-500',
    lightColor: 'bg-amber-50',
    borderColor: 'border-amber-300',
    accentColor: 'text-amber-600',
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_3556e18d-69b0-4c22-93c1-29efba584217.jpg',
  },
  {
    id: 3,
    name: 'Annual Romance',
    slug: 'annual-romance',
    tagline: 'The grandest single gesture',
    frequency: 'yearly',
    deliveries_per_year: 1,
    price_per_delivery: 3999,
    total_price: 3999,
    savings_percent: 33,
    is_popular: false,
    features_list: [
      '1 grand annual delivery',
      'Bespoke signature arrangement',
      'Complimentary add-on upgrade',
      'Dedicated florist consultation',
      'Premium keepsake packaging',
      'Express same-day delivery option',
    ],
    badge: 'Best Value',
    color: 'from-rose-400 to-pink-600',
    lightColor: 'bg-rose-50',
    borderColor: 'border-rose-200',
    accentColor: 'text-rose-600',
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_14558096-74be-4c1a-a8a2-e0334e6050d9.jpg',
  },
];

const OCCASION_TYPES = [
  { value: 'Birthday', label: '🎂 Birthday', icon: '🎂' },
  { value: 'Anniversary', label: '💍 Anniversary', icon: '💍' },
  { value: 'Valentine\'s Day', label: '❤️ Valentine\'s Day', icon: '❤️' },
  { value: 'Mother\'s Day', label: '🌸 Mother\'s Day', icon: '🌸' },
  { value: 'Wedding', label: '💐 Wedding', icon: '💐' },
  { value: 'Proposal', label: '💎 Proposal', icon: '💎' },
  { value: 'Custom Occasion', label: '✨ Custom Occasion', icon: '✨' },
];

const FREQUENCY_LABELS: Record<string, string> = {
  monthly: 'Monthly',
  quarterly: 'Quarterly',
  yearly: 'Annually',
};

const FAQ_DATA = [
  {
    q: 'How does the occasion-based subscription work?',
    a: 'You choose your special occasion date (e.g., a birthday on March 15th). We use that date to schedule every delivery around it — so your loved one always receives fresh blooms right on time.',
  },
  {
    q: 'Can I change the delivery address after subscribing?',
    a: 'Yes! Contact us via WhatsApp or email at least 5 days before the next scheduled delivery and we\'ll update your delivery details.',
  },
  {
    q: 'What happens if I want to cancel?',
    a: 'You can cancel anytime from your Profile → Subscriptions. Cancellations take effect before the next scheduled delivery.',
  },
  {
    q: 'Are the arrangements the same every time?',
    a: 'Never! Our master florists curate a unique, seasonal arrangement for every delivery. Each one is a fresh surprise.',
  },
  {
    q: 'Do you deliver outside Bangalore?',
    a: 'Currently, subscriptions are available within Bangalore city limits. We\'re expanding soon — stay tuned!',
  },
];

export default function Subscriptions() {
  const { user } = useAuth();
  const { addToCart } = useCart();
  const { toast } = useToast();
  const navigate = useNavigate();

  const [plans, setPlans] = useState(DEFAULT_PLANS);
  const [selectedPlan, setSelectedPlan] = useState<typeof DEFAULT_PLANS[0]>(DEFAULT_PLANS[1]); // Quarterly pre-selected
  const [occasionType, setOccasionType] = useState('Birthday');
  const [occasionDate, setOccasionDate] = useState('');
  const [recipientName, setRecipientName] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [openFaq, setOpenFaq] = useState<number | null>(null);

  // Fetch plans from backend
  useEffect(() => {
    subscriptionService.getPlans().then((data) => {
      if (data && data.length > 0) {
        // Merge backend data with frontend style config
        const merged = DEFAULT_PLANS.map((dp) => {
          const backend = data.find((b: any) => b.slug === dp.slug);
          if (!backend) return dp;
          return {
            ...dp,
            price_per_delivery: parseFloat(backend.price_per_delivery) || dp.price_per_delivery,
            total_price: parseFloat(backend.total_price) || dp.total_price,
            savings_percent: parseInt(backend.savings_percent) || dp.savings_percent,
            features_list: backend.features_list?.length ? backend.features_list : dp.features_list,
          };
        });
        setPlans(merged);
        // Keep quarterly as default
        setSelectedPlan(merged[1]);
      }
    }).catch(() => {/* use defaults */});
  }, []);

  const handleSubscribeNow = async () => {
    if (!occasionDate) {
      toast({ title: 'Occasion date required', description: 'Please select your special occasion date.', variant: 'destructive' });
      return;
    }
    if (!occasionType) {
      toast({ title: 'Occasion type required', description: 'Please select an occasion type.', variant: 'destructive' });
      return;
    }

    setIsSubmitting(true);
    try {
      // Add subscription as a cart item then navigate to checkout
      const subscriptionItem = {
        id: `sub-${selectedPlan.slug}-${Date.now()}`,
        name: `${selectedPlan.name} Subscription — ${occasionType}`,
        slug: `subscription-${selectedPlan.slug}`,
        description: `${FREQUENCY_LABELS[selectedPlan.frequency]} floral delivery subscription. Occasion: ${occasionType} on ${occasionDate}. ${recipientName ? 'For: ' + recipientName + '.' : ''} ${selectedPlan.deliveries_per_year} delivery/deliveries per year.`,
        price: selectedPlan.total_price,
        category: 'subscription',
        image: selectedPlan.image,
        stock_status: 'in_stock' as const,
        stock_quantity: 99,
        is_active: 1,
        quantity: 1,
        // Extra metadata for checkout display
        _subscription: {
          plan_id: selectedPlan.id,
          plan_name: selectedPlan.name,
          frequency: selectedPlan.frequency,
          occasion_type: occasionType,
          occasion_date: occasionDate,
          recipient_name: recipientName,
          deliveries_per_year: selectedPlan.deliveries_per_year,
          price_per_delivery: selectedPlan.price_per_delivery,
        }
      };

      addToCart(subscriptionItem as any, 1);

      toast({
        title: '🌸 Subscription Added!',
        description: `${selectedPlan.name} for ${occasionType} — completing your setup at checkout.`,
      });

      navigate('/checkout');
    } catch (err: any) {
      toast({
        title: 'Something went wrong',
        description: err.message || 'Please try again.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const today = new Date().toISOString().split('T')[0];

  return (
    <div className="min-h-screen bg-[#0A0A0F] text-white selection:bg-rose-500 selection:text-white font-sans">
      {/* ── Ambient blobs ─────────────────────────────────── */}
      <div className="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div className="absolute top-[-8%] left-[10%] w-[600px] h-[600px] rounded-full bg-rose-600/12 blur-[160px]" />
        <div className="absolute top-[50%] right-[-5%] w-[500px] h-[500px] rounded-full bg-amber-500/10 blur-[140px]" />
        <div className="absolute bottom-[-5%] left-[30%] w-[700px] h-[700px] rounded-full bg-purple-600/8 blur-[180px]" />
      </div>

      <div className="relative z-10">

        {/* ── HERO ──────────────────────────────────────────── */}
        <section className="relative min-h-[80vh] flex items-center justify-center pt-20 pb-28 overflow-hidden">
          {/* Background image */}
          <div className="absolute inset-0">
            <img
              src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_66ade087-5eac-4fb3-83db-431e8df6b554.jpg"
              alt="Luxury Floral Subscription"
              className="w-full h-full object-cover opacity-20"
            />
            <div className="absolute inset-0 bg-gradient-to-b from-[#0A0A0F]/60 via-[#0A0A0F]/80 to-[#0A0A0F]" />
          </div>

          <div className="container relative z-10 text-center max-w-4xl px-4">
            <div className="inline-flex items-center gap-2 px-5 py-2 rounded-full border border-rose-500/40 bg-rose-500/10 text-rose-300 text-xs font-bold tracking-widest uppercase mb-8 backdrop-blur-md">
              <Repeat className="h-4 w-4 text-rose-400 animate-spin" style={{ animationDuration: '4s' }} />
              Occasion-Timed Floral Subscriptions
            </div>

            <h1 className="text-5xl md:text-7xl font-bold font-serif tracking-tight text-white mb-6 leading-[1.08]">
              Never Miss<br />
              <span className="bg-gradient-to-r from-rose-400 via-pink-400 to-amber-400 bg-clip-text text-transparent italic">
                a Special Moment
              </span>
            </h1>

            <p className="text-lg md:text-2xl text-white/70 max-w-2xl mx-auto leading-relaxed mb-12 font-light">
              Choose a subscription plan, set your occasion date, and we'll deliver a handcrafted luxury floral arrangement to your loved one — every single time, without fail.
            </p>

            {/* Quick stats */}
            <div className="grid grid-cols-3 gap-6 max-w-lg mx-auto text-center border-t border-white/10 pt-10">
              <div>
                <p className="text-2xl font-bold bg-gradient-to-r from-rose-400 to-pink-400 bg-clip-text text-transparent">100%</p>
                <p className="text-xs text-white/50 uppercase tracking-wider mt-1">Occasion-Timed</p>
              </div>
              <div>
                <p className="text-2xl font-bold bg-gradient-to-r from-amber-400 to-orange-400 bg-clip-text text-transparent">1,200+</p>
                <p className="text-xs text-white/50 uppercase tracking-wider mt-1">Happy Subscribers</p>
              </div>
              <div>
                <p className="text-2xl font-bold bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">4.9 ★</p>
                <p className="text-xs text-white/50 uppercase tracking-wider mt-1">Customer Rating</p>
              </div>
            </div>
          </div>
        </section>

        {/* ── HOW IT WORKS ──────────────────────────────────── */}
        <section className="py-20 border-b border-white/5">
          <div className="container max-w-5xl px-4">
            <div className="text-center mb-14">
              <span className="text-rose-400 font-bold text-xs tracking-widest uppercase">Simple & Magical</span>
              <h2 className="text-3xl md:text-5xl font-bold font-serif text-white mt-2">How It Works</h2>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
              {[
                { step: '01', icon: Gift, title: 'Pick Your Plan', desc: 'Choose Monthly, Quarterly, or Annual. Each plan is crafted for a different style of gifting.' },
                { step: '02', icon: Calendar, title: 'Set Your Occasion', desc: 'Tell us the occasion type and the date. We handle all the scheduling around it, automatically.' },
                { step: '03', icon: Flower2, title: 'We Deliver the Magic', desc: 'On each scheduled date, a fresh, handcrafted luxury arrangement arrives at your loved one\'s doorstep.' },
              ].map(({ step, icon: Icon, title, desc }) => (
                <div key={step} className="bg-white/5 border border-white/10 rounded-2xl p-8 hover:border-rose-500/30 hover:-translate-y-1 transition-all duration-300 relative group">
                  <span className="text-5xl font-serif font-bold text-white/10 group-hover:text-rose-500/20 transition-colors absolute top-6 right-7">{step}</span>
                  <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 w-fit mb-5">
                    <Icon className="h-6 w-6 text-rose-400" />
                  </div>
                  <h3 className="text-lg font-bold text-white mb-2">{title}</h3>
                  <p className="text-sm text-white/60 leading-relaxed">{desc}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ── PLAN CARDS ────────────────────────────────────── */}
        <section className="py-24 border-b border-white/5">
          <div className="container max-w-6xl px-4">
            <div className="text-center mb-16">
              <span className="text-rose-400 font-bold text-xs tracking-widest uppercase">Choose Your Plan</span>
              <h2 className="text-3xl md:text-5xl font-bold font-serif text-white mt-2 mb-4">
                Subscription <span className="bg-gradient-to-r from-rose-400 to-amber-400 bg-clip-text text-transparent italic">Tiers</span>
              </h2>
              <p className="text-white/60 max-w-xl mx-auto">Every plan includes handcrafted arrangements, premium packaging, and on-time delivery.</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
              {plans.map((plan) => {
                const isSelected = selectedPlan.id === plan.id;
                return (
                  <div
                    key={plan.id}
                    onClick={() => setSelectedPlan(plan)}
                    className={cn(
                      'relative rounded-3xl border-2 cursor-pointer transition-all duration-300 overflow-hidden flex flex-col',
                      isSelected
                        ? 'border-rose-400 shadow-2xl shadow-rose-500/20 scale-[1.02]'
                        : 'border-white/10 hover:border-white/25 hover:-translate-y-1'
                    )}
                  >
                    {/* Image Header */}
                    <div className="relative aspect-[16/9] overflow-hidden">
                      <img src={plan.image} alt={plan.name} className="w-full h-full object-cover" />
                      <div className="absolute inset-0 bg-gradient-to-t from-[#0A0A0F]/90 via-[#0A0A0F]/30 to-transparent" />
                      <div className="absolute bottom-4 left-5 right-5 flex justify-between items-end">
                        <div>
                          <p className="text-xs font-bold uppercase tracking-widest text-rose-300 mb-1">
                            {FREQUENCY_LABELS[plan.frequency]}
                          </p>
                          <h3 className="text-xl font-bold font-serif text-white">{plan.name}</h3>
                        </div>
                        {plan.badge && (
                          <span className={cn(
                            'text-[10px] font-extrabold px-3 py-1.5 rounded-full uppercase tracking-wider',
                            plan.badge === 'Most Popular'
                              ? 'bg-amber-400 text-black'
                              : 'bg-white/20 backdrop-blur-md text-white border border-white/30'
                          )}>
                            {plan.badge}
                          </span>
                        )}
                      </div>
                    </div>

                    {/* Body */}
                    <div className="flex flex-col flex-1 p-6 bg-[#111118]">
                      <p className="text-xs italic text-rose-300/80 mb-4">{plan.tagline}</p>

                      {/* Pricing */}
                      <div className="mb-5 pb-5 border-b border-white/10">
                        <div className="flex items-baseline gap-2">
                          <span className="text-3xl font-extrabold text-white">
                            {formatINR(plan.price_per_delivery)}
                          </span>
                          <span className="text-xs text-white/50">/ delivery</span>
                        </div>
                        <p className="text-xs text-white/40 mt-1">
                          {formatINR(plan.total_price)} total · {plan.deliveries_per_year} delivery/yr
                        </p>
                        {plan.savings_percent > 0 && (
                          <span className="inline-block mt-2 text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 rounded-full px-2.5 py-0.5 font-bold">
                            Save {plan.savings_percent}% vs individual orders
                          </span>
                        )}
                      </div>

                      {/* Features */}
                      <ul className="space-y-2.5 flex-1">
                        {plan.features_list.map((feat) => (
                          <li key={feat} className="flex items-start gap-2.5 text-sm text-white/70">
                            <Check className="h-4 w-4 text-rose-400 shrink-0 mt-0.5" />
                            <span>{feat}</span>
                          </li>
                        ))}
                      </ul>

                      {/* Select indicator */}
                      <div className={cn(
                        'mt-5 py-2.5 rounded-full text-center text-sm font-bold transition-all',
                        isSelected
                          ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white'
                          : 'bg-white/5 text-white/40 border border-white/10'
                      )}>
                        {isSelected ? '✓ Selected' : 'Select Plan'}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </section>

        {/* ── BOOKING CONFIGURATOR ──────────────────────────── */}
        <section className="py-24 border-b border-white/5">
          <div className="container max-w-3xl px-4">
            <div className="bg-[#111118] rounded-3xl border border-white/10 p-8 md:p-12 shadow-2xl relative overflow-hidden">
              {/* Glow accent */}
              <div className="absolute top-0 right-0 w-64 h-64 bg-rose-500/10 rounded-full blur-3xl pointer-events-none" />

              <div className="text-center mb-10">
                <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs font-bold uppercase tracking-widest mb-4">
                  <Sparkles className="h-3.5 w-3.5" /> Configure Your Subscription
                </div>
                <h2 className="text-3xl md:text-4xl font-bold font-serif text-white">
                  Set Your{' '}
                  <span className="bg-gradient-to-r from-rose-400 to-amber-400 bg-clip-text text-transparent italic">
                    Occasion Details
                  </span>
                </h2>
                <p className="text-white/50 mt-3 max-w-md mx-auto text-sm">
                  Tell us when the magic should happen, and we'll handle everything from there.
                </p>
              </div>

              <div className="space-y-6">
                {/* Selected Plan Summary */}
                <div className="bg-white/5 border border-white/10 rounded-2xl p-5 flex items-center justify-between">
                  <div>
                    <p className="text-xs text-white/40 uppercase tracking-wider mb-1">Selected Plan</p>
                    <p className="text-lg font-bold text-white">{selectedPlan.name}</p>
                    <p className="text-xs text-rose-300">{FREQUENCY_LABELS[selectedPlan.frequency]} delivery · {formatINR(selectedPlan.price_per_delivery)}/delivery</p>
                  </div>
                  <div className="text-right">
                    <p className="text-xs text-white/40 mb-1">Total</p>
                    <p className="text-2xl font-extrabold text-white">{formatINR(selectedPlan.total_price)}</p>
                  </div>
                </div>

                {/* Occasion Type */}
                <div className="space-y-2">
                  <label className="text-xs font-bold text-white/70 uppercase tracking-wider">
                    Occasion Type <span className="text-rose-400">*</span>
                  </label>
                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    {OCCASION_TYPES.map((occ) => (
                      <button
                        key={occ.value}
                        type="button"
                        onClick={() => setOccasionType(occ.value)}
                        className={cn(
                          'px-3 py-3 rounded-xl border text-sm font-medium transition-all text-left',
                          occasionType === occ.value
                            ? 'bg-rose-500/20 border-rose-400 text-white'
                            : 'bg-white/5 border-white/10 text-white/60 hover:border-white/25 hover:text-white'
                        )}
                      >
                        <span className="text-base mr-1.5">{occ.icon}</span>
                        <span className="text-xs">{occ.value}</span>
                      </button>
                    ))}
                  </div>
                </div>

                {/* Occasion Date */}
                <div className="space-y-2">
                  <label className="text-xs font-bold text-white/70 uppercase tracking-wider flex items-center gap-2">
                    <Calendar className="h-4 w-4 text-rose-400" />
                    Occasion Date <span className="text-rose-400">*</span>
                    <span className="text-[10px] text-white/30 font-normal normal-case">— Day & Month your celebrations fall on</span>
                  </label>
                  <input
                    type="date"
                    min={today}
                    value={occasionDate}
                    onChange={(e) => setOccasionDate(e.target.value)}
                    className="w-full h-14 bg-white/5 border border-white/15 rounded-xl text-white px-4 text-sm focus:border-rose-400 focus:outline-none focus:ring-1 focus:ring-rose-400/30 transition-all [color-scheme:dark]"
                  />
                  {occasionDate && (
                    <p className="text-xs text-emerald-400 flex items-center gap-1.5 mt-1">
                      <Check className="h-3.5 w-3.5" />
                      First delivery will be scheduled around <strong>{new Date(occasionDate).toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' })}</strong>
                    </p>
                  )}
                </div>

                {/* Recipient Name (optional) */}
                <div className="space-y-2">
                  <label className="text-xs font-bold text-white/70 uppercase tracking-wider">
                    Recipient Name <span className="text-white/30 font-normal normal-case">(optional)</span>
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. Priya, Mom, My Love..."
                    value={recipientName}
                    onChange={(e) => setRecipientName(e.target.value)}
                    className="w-full h-14 bg-white/5 border border-white/15 rounded-xl text-white placeholder:text-white/25 px-4 text-sm focus:border-rose-400 focus:outline-none focus:ring-1 focus:ring-rose-400/30 transition-all"
                  />
                </div>

                {/* CTA */}
                <Button
                  size="lg"
                  disabled={isSubmitting || !occasionDate}
                  onClick={handleSubscribeNow}
                  className="w-full h-16 rounded-full bg-gradient-to-r from-rose-500 via-pink-500 to-rose-600 text-white font-extrabold text-lg hover:brightness-110 shadow-xl shadow-rose-500/30 transition-all hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isSubmitting ? (
                    <span className="flex items-center gap-2">
                      <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                      Setting up your subscription...
                    </span>
                  ) : (
                    <span className="flex items-center gap-2">
                      Subscribe Now — {formatINR(selectedPlan.total_price)}
                      <ArrowRight className="h-5 w-5" />
                    </span>
                  )}
                </Button>

                <p className="text-center text-xs text-white/30 flex items-center justify-center gap-1.5">
                  <Bell className="h-3.5 w-3.5 text-rose-400/60" />
                  You'll confirm delivery address & payment at checkout. Cancel anytime.
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* ── FEATURE HIGHLIGHTS ─────────────────────────────── */}
        <section className="py-20 border-b border-white/5">
          <div className="container max-w-5xl px-4">
            <div className="text-center mb-14">
              <h2 className="text-3xl md:text-5xl font-bold font-serif text-white">
                Why OMG <span className="bg-gradient-to-r from-rose-400 to-amber-400 bg-clip-text text-transparent italic">Subscriptions?</span>
              </h2>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
              {[
                { icon: Clock, title: 'Always On Time', desc: 'Deliveries scheduled around your occasion date — never late, never missed.' },
                { icon: Star, title: 'Handcrafted Each Time', desc: 'Every arrangement is unique, freshly created by our master florists for that moment.' },
                { icon: Crown, title: 'Premium Quality', desc: 'Only the freshest, farm-direct blooms and luxury-grade ingredients in every delivery.' },
                { icon: Heart, title: 'Thoughtful Gifting', desc: 'Show your loved ones they\'re remembered every occasion, without any extra effort from you.' },
              ].map(({ icon: Icon, title, desc }) => (
                <div key={title} className="text-center p-6 rounded-2xl bg-white/3 border border-white/8 hover:border-rose-500/20 transition-all">
                  <div className="inline-flex p-4 rounded-full bg-gradient-to-br from-rose-500/20 to-pink-500/10 border border-rose-500/20 mb-4">
                    <Icon className="h-7 w-7 text-rose-400" />
                  </div>
                  <h3 className="font-bold text-white mb-2">{title}</h3>
                  <p className="text-xs text-white/50 leading-relaxed">{desc}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ── TESTIMONIALS ──────────────────────────────────── */}
        <section className="py-20 border-b border-white/5">
          <div className="container max-w-4xl px-4">
            <div className="text-center mb-12">
              <span className="text-rose-400 font-bold text-xs tracking-widest uppercase">Subscriber Stories</span>
              <h2 className="text-3xl font-bold font-serif text-white mt-2">What Our Subscribers Say</h2>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              {[
                {
                  name: 'Kavitha R.', location: 'Koramangala', review: 'I subscribed for my mom\'s birthday and she was so moved! Every month she gets fresh flowers — she tells all her friends about it.',
                  plan: 'Monthly Bloom', rating: 5,
                },
                {
                  name: 'Arjun M.', location: 'Indiranagar', review: 'The quarterly plan is perfect for our anniversary. My wife doesn\'t know it\'s automated — she thinks I remember every time! 😄',
                  plan: 'Quarterly Celebration', rating: 5,
                },
                {
                  name: 'Sneha P.', location: 'Whitefield', review: 'The Annual Romance arrangement was breathtaking. A full-scale bespoke hamper delivered on our 10th anniversary — unforgettable.',
                  plan: 'Annual Romance', rating: 5,
                },
              ].map((t) => (
                <div key={t.name} className="bg-[#111118] border border-white/10 rounded-2xl p-6 hover:border-rose-500/20 transition-all">
                  <div className="flex items-center gap-0.5 mb-4">
                    {[1,2,3,4,5].map(i => (
                      <Star key={i} className={`h-4 w-4 ${i <= t.rating ? 'fill-amber-400 text-amber-400' : 'text-white/10'}`} />
                    ))}
                  </div>
                  <p className="text-sm text-white/70 leading-relaxed mb-5">"{t.review}"</p>
                  <div className="flex items-center justify-between border-t border-white/10 pt-4">
                    <div>
                      <p className="text-sm font-bold text-white">{t.name}</p>
                      <p className="text-xs text-white/40">{t.location}</p>
                    </div>
                    <span className="text-[10px] bg-rose-500/15 text-rose-300 border border-rose-500/20 rounded-full px-2.5 py-1 font-bold">
                      {t.plan}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ── FAQ ───────────────────────────────────────────── */}
        <section className="py-20 border-b border-white/5">
          <div className="container max-w-3xl px-4">
            <div className="text-center mb-12">
              <span className="text-rose-400 font-bold text-xs tracking-widest uppercase">Got Questions?</span>
              <h2 className="text-3xl font-bold font-serif text-white mt-2">Frequently Asked</h2>
            </div>
            <div className="space-y-3">
              {FAQ_DATA.map((faq, idx) => (
                <div
                  key={idx}
                  className="bg-[#111118] border border-white/10 rounded-2xl overflow-hidden hover:border-rose-500/20 transition-all"
                >
                  <button
                    onClick={() => setOpenFaq(openFaq === idx ? null : idx)}
                    className="w-full flex items-center justify-between p-6 text-left"
                  >
                    <span className="font-semibold text-white pr-4">{faq.q}</span>
                    {openFaq === idx
                      ? <ChevronUp className="h-5 w-5 text-rose-400 shrink-0" />
                      : <ChevronDown className="h-5 w-5 text-white/30 shrink-0" />
                    }
                  </button>
                  {openFaq === idx && (
                    <div className="px-6 pb-6 text-sm text-white/60 leading-relaxed border-t border-white/5 pt-4">
                      {faq.a}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ── FINAL CTA ─────────────────────────────────────── */}
        <section className="py-24">
          <div className="container max-w-3xl px-4 text-center">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-rose-500/30 bg-rose-500/10 text-rose-300 text-xs font-bold uppercase tracking-widest mb-6">
              <Sparkles className="h-3.5 w-3.5" /> Start Gifting Today
            </div>
            <h2 className="text-4xl md:text-5xl font-bold font-serif text-white mb-6">
              Your Loved One Deserves<br />
              <span className="bg-gradient-to-r from-rose-400 to-amber-400 bg-clip-text text-transparent italic">to Feel Remembered</span>
            </h2>
            <p className="text-white/60 text-lg mb-10">
              Set it once. Let us deliver joy, again and again, on every occasion that matters.
            </p>
            <Button
              size="lg"
              onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
              className="h-16 px-14 rounded-full bg-gradient-to-r from-rose-500 to-pink-600 text-white font-bold text-lg hover:brightness-110 shadow-xl shadow-rose-500/25 hover:scale-105 transition-all"
            >
              Choose Your Plan <ArrowRight className="ml-2 h-5 w-5" />
            </Button>
          </div>
        </section>

      </div>
    </div>
  );
}
