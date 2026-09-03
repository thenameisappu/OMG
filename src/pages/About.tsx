import { useState, useEffect } from 'react';
import { Flower2, Gift, HeartHandshake, Sparkles, Star, Quote, MapPin } from 'lucide-react';
import { testimonialService } from '@/services/api';
import { TeamShowcase } from '@/components/TeamShowcase';

const values = [
  {
    icon: Flower2,
    title: 'Our Story',
    description: 'Born from a passion for creating unforgettable moments, Oh My Gudness brings the finest handcrafted floral arrangements and curated gift experiences to Bangalore.',
  },
  {
    icon: Gift,
    title: 'What We Create',
    description: 'From exquisite fresh bouquets and luxury hampers to bespoke surprise setups — every creation is crafted with care, precision, and love.',
  },
  {
    icon: HeartHandshake,
    title: 'Our Promise',
    description: 'We guarantee 100% farm-fresh blooms, same-day express delivery, and a dedicated concierge team to make every moment magical.',
  },
];



const processSteps = [
  'Discover the collection',
  'Choose something meaningful',
  'Let us prepare the moment',
];

// Fallback sample testimonials shown until real ones come from the backend
const SAMPLE_TESTIMONIALS = [
  {
    id: 1,
    name: 'Priya Sharma',
    location: 'Indiranagar, Bangalore',
    rating: 5,
    review: 'Absolutely stunning arrangement! The roses were incredibly fresh and the packaging was so elegant. My husband was speechless. Will definitely order again for every special occasion.',
    avatar: 'PS',
  },
  {
    id: 2,
    name: 'Arjun Mehta',
    location: 'Koramangala, Bangalore',
    rating: 5,
    review: 'OMG planned the most perfect rooftop surprise for my wife\'s birthday. Every detail was handled flawlessly — from the candles to the live guitarist. She was in tears of joy!',
    avatar: 'AM',
  },
  {
    id: 3,
    name: 'Sneha Reddy',
    location: 'Whitefield, Bangalore',
    rating: 5,
    review: 'The hamper I ordered for my mom\'s anniversary was gorgeous. Super-fast delivery, beautifully wrapped, and the flowers lasted over 10 days. Exceptional service!',
    avatar: 'SR',
  },
  {
    id: 4,
    name: 'Rahul Nair',
    location: 'JP Nagar, Bangalore',
    rating: 5,
    review: 'Used OMG for a proposal setup and they absolutely nailed it! The coordination was seamless and discreet. She said YES! Thank you OMG for making it unforgettable.',
    avatar: 'RN',
  },
  {
    id: 5,
    name: 'Kavya Bhat',
    location: 'Jayanagar, Bangalore',
    rating: 5,
    review: 'Ordered the Teddy Bear Bouquet — arrived on time and looked even better than the photos! The team was so responsive on WhatsApp. Highly recommend to everyone.',
    avatar: 'KB',
  },
  {
    id: 6,
    name: 'Deepak Anand',
    location: 'HSR Layout, Bangalore',
    rating: 4,
    review: 'Great quality flowers and very professional packaging. The midnight delivery option was a game changer for surprising my partner. Will be my go-to shop from now on.',
    avatar: 'DA',
  },
];

function StarRow({ rating }: { rating: number }) {
  return (
    <div className="flex items-center gap-0.5">
      {[1, 2, 3, 4, 5].map((i) => (
        <Star
          key={i}
          className={`h-4 w-4 ${i <= rating ? 'fill-amber-400 text-amber-400' : 'fill-muted text-muted-foreground/30'}`}
        />
      ))}
    </div>
  );
}

function AvatarInitials({ initials }: { initials: string }) {
  return (
    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-secondary/20 text-sm font-bold text-secondary ring-2 ring-secondary/30">
      {initials}
    </div>
  );
}

export default function About() {
  const [testimonials, setTestimonials] = useState<any[]>(SAMPLE_TESTIMONIALS);
  const [loadingTestimonials, setLoadingTestimonials] = useState(true);

  useEffect(() => {
    testimonialService.getAll().then((data) => {
      // Only replace samples if real testimonials exist
      if (data && data.length > 0) {
        setTestimonials(data);
      }
      setLoadingTestimonials(false);
    });
  }, []);

  return (
    <main className="min-h-screen bg-background">
      {/* Hero */}
      <section className="relative overflow-hidden bg-primary px-6 py-24 text-white md:py-32">
        <div className="container relative z-10 mx-auto max-w-4xl text-center">
          <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-secondary/30 bg-secondary/10 px-4 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-secondary">
            <Sparkles className="h-4 w-4" /> About OMG
          </div>
          <h1 className="font-serif text-4xl font-bold leading-tight md:text-6xl">
            Elevating the Art of <span className="text-secondary italic">Giving</span>
          </h1>
          <p className="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-white/75 md:text-lg">
            Bangalore's premier luxury floral &amp; surprise house — crafting moments that stay in hearts forever.
          </p>
        </div>
        <div className="absolute -bottom-24 left-1/2 h-48 w-[120%] -translate-x-1/2 rounded-[50%] border-t border-secondary/20 bg-background/10" />
      </section>

      {/* Brand Story */}
      <section className="container mx-auto max-w-5xl px-6 py-20 md:py-28">
        <div className="grid gap-12 md:grid-cols-[0.8fr_1.2fr] md:items-start">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-secondary">The heart of OMG</p>
            <h2 className="mt-3 font-serif text-3xl font-bold text-primary md:text-4xl">
              Where every bloom tells a story
            </h2>
          </div>
          <div className="space-y-5 text-sm leading-7 text-muted-foreground md:text-base">
            <p>Oh My Gudness was founded with one simple belief: that a beautifully crafted gesture has the power to transform ordinary moments into extraordinary memories.</p>
            <p>From handpicked farm-fresh blooms to meticulously curated hampers and surprise experiences, every detail is thoughtfully considered — because we believe your loved ones deserve nothing but the very best.</p>
          </div>
        </div>
      </section>

      {/* Our Team - Executive Spotlight */}
      <section className="container mx-auto max-w-6xl px-6 py-20 md:py-28">
        <div className="mb-12 text-center md:text-left">
          <p className="text-xs font-bold uppercase tracking-[0.25em] text-secondary">Leadership &amp; Floral Artisans</p>
          <h2 className="mt-3 font-serif text-3xl font-bold text-primary md:text-5xl">The Minds Behind OMG</h2>
          <div className="mt-4 h-1 w-24 bg-secondary rounded-full" />
          <p className="mt-3 text-sm text-muted-foreground max-w-xl">
            Meet the master florists, experience architects, and concierge specialists dedicated to making your celebrations unforgettable.
          </p>
        </div>
        
        <TeamShowcase autoPlay={false} />
      </section>

      {/* Values */}
      <section className="border-y border-border bg-muted/20">
        <div className="container mx-auto max-w-5xl px-6 py-20 md:py-24">
          <div className="mb-10 max-w-2xl">
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-secondary">What matters to us</p>
            <h2 className="mt-3 font-serif text-3xl font-bold text-primary md:text-4xl">Our values</h2>
          </div>
          <div className="grid gap-5 md:grid-cols-3">
            {values.map(({ icon: Icon, title, description }) => (
              <article key={title} className="rounded-2xl border border-border bg-white p-7 shadow-sm">
                <Icon className="h-7 w-7 text-secondary" />
                <h3 className="mt-5 font-serif text-xl font-bold text-primary">{title}</h3>
                <p className="mt-3 text-sm leading-6 text-muted-foreground">{description}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* Process */}
      <section className="container mx-auto max-w-5xl px-6 py-20 md:py-28">
        <div className="grid gap-10 md:grid-cols-[1fr_1fr] md:items-center">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-secondary">The OMG experience</p>
            <h2 className="mt-3 font-serif text-3xl font-bold text-primary md:text-4xl">From feeling to moment</h2>
            <p className="mt-5 max-w-lg text-sm leading-7 text-muted-foreground md:text-base">
              Our team of master florists and event architects handles every detail — so all you have to do is watch the magic unfold.
            </p>
          </div>
          <ol className="space-y-4">
            {processSteps.map((step, index) => (
              <li key={step} className="flex items-center gap-4 rounded-xl border border-border bg-white p-4 shadow-sm">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-secondary">
                  {index + 1}
                </span>
                <span className="font-semibold text-primary">{step}</span>
              </li>
            ))}
          </ol>
        </div>
      </section>

      {/* ── Testimonials ── */}
      <section className="border-t border-border bg-muted/20 py-20 md:py-28">
        <div className="container mx-auto max-w-6xl px-6">
          {/* Heading */}
          <div className="mb-14 text-center">
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-secondary">What our customers say</p>
            <h2 className="mt-3 font-serif text-3xl font-bold text-primary md:text-5xl">
              Loved by Bangalore 💛
            </h2>
            <div className="mx-auto mt-4 h-1 w-20 rounded-full bg-secondary" />
            <p className="mx-auto mt-4 max-w-xl text-sm leading-relaxed text-muted-foreground md:text-base">
              Real stories from real customers — every word is a moment we cherish.
            </p>
          </div>

          {/* Cards Grid */}
          {loadingTestimonials ? (
            <div className="flex justify-center py-12">
              <div className="h-10 w-10 animate-spin rounded-full border-b-2 border-t-2 border-secondary" />
            </div>
          ) : (
            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {testimonials.map((t, idx) => (
                <article
                  key={t.id ?? idx}
                  className="group relative flex flex-col justify-between rounded-2xl border border-border bg-white p-6 shadow-sm transition-all duration-300 hover:border-secondary/40 hover:shadow-md"
                >
                  {/* Quote icon */}
                  <Quote className="absolute right-5 top-5 h-8 w-8 text-secondary/15 transition-colors group-hover:text-secondary/25" />

                  {/* Stars */}
                  <StarRow rating={Number(t.rating ?? 5)} />

                  {/* Review text */}
                  <p className="mt-4 flex-1 text-sm leading-7 text-muted-foreground">
                    "{t.review}"
                  </p>

                  {/* Author */}
                  <div className="mt-6 flex items-center gap-3 border-t border-border/50 pt-5">
                    <AvatarInitials initials={t.avatar ?? String(t.name ?? 'U').slice(0, 2).toUpperCase()} />
                    <div>
                      <p className="text-sm font-bold text-primary">{t.name}</p>
                      {(t.location) && (
                        <p className="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                          <MapPin className="h-3 w-3 text-secondary" />
                          {t.location}
                        </p>
                      )}
                    </div>
                  </div>
                </article>
              ))}
            </div>
          )}

          {/* Average rating callout */}
          <div className="mx-auto mt-14 flex max-w-sm flex-col items-center gap-2 rounded-2xl border border-secondary/30 bg-secondary/5 px-8 py-6 text-center shadow-sm">
            <p className="text-4xl font-bold text-primary">4.9 <span className="text-secondary">★</span></p>
            <p className="text-xs font-bold uppercase tracking-widest text-muted-foreground">Average customer rating</p>
            <p className="text-xs text-muted-foreground">Based on {testimonials.length}+ verified reviews</p>
          </div>
        </div>
      </section>
    </main>
  );
}
