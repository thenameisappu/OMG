import { useState, useEffect } from 'react';
import { ChevronLeft, ChevronRight, Sparkles, Award, Star, Quote, ArrowUpRight } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface TeamMember {
  id: number;
  name: string;
  role: string;
  department: string;
  tagline: string;
  headline: string;
  story: string;
  image: string;
  highlights: string[];
  experience: string;
}

export const TEAM_MEMBERS: TeamMember[] = [
  {
    id: 1,
    name: 'Rahul Kumar',
    role: 'Founder & CEO, Oh My Gudness',
    department: 'Executive Leadership',
    tagline: 'Vision & Innovation',
    headline: 'Elevating the Art of Giving Across Bangalore',
    story: 'Floral finesse meets bespoke luxury at Oh My Gudness. We embarked on this journey steered by innovation, quality, and emotive design — blossoming into Bangalore’s quintessential destination for all things floral and celebratory. With a desire to revolutionize gift giving and create timeless aesthetics, we bring together passion and perfection to satiate distinct fantasies and enable experiences that hold true to one’s spirit.',
    image: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=800&q=80',
    highlights: ['15+ Years in Luxury Floristry', '50,000+ Celebrations Curated', 'Bespoke Experience Architect'],
    experience: '15+ Years Experience',
  },
  {
    id: 2,
    name: 'Priya Sharma',
    role: 'Head of Floral Design & Master Florist',
    department: 'Floral Couture',
    tagline: 'Artistry & Botanical Styling',
    headline: 'Where Botanical Elegance Meets Couture Artistry',
    story: 'Every flower possesses its own rhythm, poetry, and character. Sourcing farm-fresh, premium-grade blooms from the finest growers, we curate each arrangement with couture-level precision and artistic harmony. For me, crafting a bouquet is about capturing a feeling — transforming fresh petals and fragrant stems into an ethereal experience that lingers in the heart forever.',
    image: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=800&q=80',
    highlights: ['Master Certified Floral Stylist', 'Exotic & Dutch Bloom Specialist', 'Luxury Event Decorator'],
    experience: '10+ Years Experience',
  },
  {
    id: 3,
    name: 'Arjun Nair',
    role: 'Director of Surprise Experiences',
    department: 'Experience Architecture',
    tagline: 'Moments & Grand Gestures',
    headline: 'Architecting Unforgettable Memories & Grand Surprises',
    story: 'A truly memorable surprise is a symphony of secrecy, impeccable timing, and sensory wonder. Whether it’s an intimate midnight candlelight serenade or an awe-inspiring rooftop proposal under the stars, our team orchestrates every single detail seamlessly so that you can simply be present in the magic of the moment.',
    image: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=800&q=80',
    highlights: ['1,000+ Bespoke Proposals Managed', 'Midnight Surprise Specialist', 'End-to-End Production'],
    experience: '8+ Years Experience',
  },
  {
    id: 4,
    name: 'Sneha Reddy',
    role: 'Lead Concierge & Client Experience',
    department: 'Client Relations',
    tagline: 'Hospitality & White-Glove Care',
    headline: 'Delivering White-Glove Care & Flawless Precision',
    story: 'True luxury lies in the unseen care — understanding our clients’ intimate stories, anticipating every need, and providing personalized recommendations with utmost discretion. From same-day express deliveries to custom gift curations, we treat every order as a personal promise to bring pure joy to your loved ones.',
    image: 'https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?auto=format&fit=crop&w=800&q=80',
    highlights: ['24/7 Dedicated Concierge Support', 'Same-Day Express Coordination', 'VIP Personal Gifting Advisor'],
    experience: '7+ Years Experience',
  },
];

interface TeamShowcaseProps {
  className?: string;
  autoPlay?: boolean;
}

export function TeamShowcase({ className, autoPlay = false }: TeamShowcaseProps) {
  const [activeIndex, setActiveIndex] = useState(0);
  const [isPaused, setIsPaused] = useState(false);
  const [imgError, setImgError] = useState<Record<number, boolean>>({});

  const currentMember = TEAM_MEMBERS[activeIndex];

  const handleNext = () => {
    setActiveIndex((prev) => (prev + 1) % TEAM_MEMBERS.length);
  };

  const handlePrev = () => {
    setActiveIndex((prev) => (prev - 1 + TEAM_MEMBERS.length) % TEAM_MEMBERS.length);
  };

  useEffect(() => {
    if (!autoPlay || isPaused) return;
    const interval = setInterval(handleNext, 7000);
    return () => clearInterval(interval);
  }, [autoPlay, isPaused, activeIndex]);

  return (
    <div
      className={cn('w-full max-w-6xl mx-auto space-y-8', className)}
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
    >
      {/* ── Person Selector Tabs (One Card For One Person) ── */}
      <div className="flex flex-wrap items-center justify-center gap-2 md:gap-3 p-1.5 bg-muted/40 backdrop-blur-sm rounded-2xl border border-border/70 max-w-fit mx-auto">
        {TEAM_MEMBERS.map((member, idx) => {
          const isActive = idx === activeIndex;
          return (
            <button
              key={member.id}
              onClick={() => setActiveIndex(idx)}
              className={cn(
                'flex items-center gap-2.5 px-4 py-2 rounded-xl text-xs md:text-sm font-medium transition-all duration-300',
                isActive
                  ? 'bg-white dark:bg-card text-primary shadow-md border border-secondary/30 ring-2 ring-secondary/20 scale-[1.02]'
                  : 'text-muted-foreground hover:text-primary hover:bg-white/60 dark:hover:bg-card/50'
              )}
            >
              <img
                src={member.image}
                alt={member.name}
                className={cn(
                  'w-7 h-7 rounded-full object-cover ring-1',
                  isActive ? 'ring-secondary' : 'ring-border'
                )}
                onError={() => setImgError((prev) => ({ ...prev, [member.id]: true }))}
              />
              <span className="font-semibold">{member.name}</span>
              <span
                className={cn(
                  'hidden sm:inline-block text-[11px] px-2 py-0.5 rounded-full font-normal',
                  isActive
                    ? 'bg-secondary/15 text-secondary font-semibold'
                    : 'bg-muted text-muted-foreground'
                )}
              >
                {member.tagline}
              </span>
            </button>
          );
        })}
      </div>

      {/* ── Main Editorial Persona Card (Matching Interflora Image 2 Reference) ── */}
      <div className="relative rounded-3xl bg-[#FCF8F3] dark:bg-[#1A1816] border border-[#EADBCE] dark:border-secondary/20 p-6 sm:p-8 md:p-12 lg:p-14 shadow-xl overflow-hidden transition-all duration-500">
        {/* Subtle Decorative Background Watermark */}
        <Quote className="absolute -right-6 -bottom-6 w-48 h-48 text-[#EEDBCE]/40 dark:text-secondary/5 pointer-events-none rotate-12" />

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 md:gap-12 lg:gap-14 items-center relative z-10">
          
          {/* Left Column: Layered Architectural Framed Portrait Photo */}
          <div className="lg:col-span-5 flex justify-center lg:justify-start">
            <div className="relative w-full max-w-[340px] sm:max-w-[380px] pt-4 pl-4 sm:pt-6 sm:pl-6">
              {/* Layered Offset Background Frame (Identical to Image 2 design) */}
              <div className="absolute top-0 left-0 w-[90%] h-[92%] bg-[#E8D5C8] dark:bg-[#342721] rounded-2xl sm:rounded-3xl border border-[#D8C0B0] dark:border-secondary/30 transform -rotate-1 transition-transform duration-500 group-hover:rotate-0" />
              
              {/* Secondary delicate gold frame accent */}
              <div className="absolute -bottom-2 -right-2 w-24 h-24 border-r-2 border-b-2 border-secondary/40 rounded-br-2xl pointer-events-none hidden sm:block" />

              {/* Main Portrait Photo */}
              <div className="relative z-10 w-full aspect-[4/5] rounded-2xl sm:rounded-3xl overflow-hidden shadow-2xl bg-muted border-2 border-white dark:border-card">
                {!imgError[currentMember.id] ? (
                  <img
                    key={currentMember.id}
                    src={currentMember.image}
                    alt={currentMember.name}
                    className="w-full h-full object-cover object-center animate-fadeIn transition-transform duration-700 hover:scale-105"
                    onError={() => setImgError((prev) => ({ ...prev, [currentMember.id]: true }))}
                  />
                ) : (
                  <div className="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-[#E8D5C8] to-[#FCF8F3] text-primary p-6 text-center">
                    <span className="font-serif text-3xl font-bold">{currentMember.name.slice(0, 2)}</span>
                    <span className="text-xs text-muted-foreground mt-2">{currentMember.name}</span>
                  </div>
                )}

                {/* Experience Badge Overlay */}
                <div className="absolute bottom-3 left-3 right-3 bg-white/95 dark:bg-card/95 backdrop-blur-md px-3.5 py-2 rounded-xl shadow-lg border border-border/50 flex items-center justify-between">
                  <div className="flex items-center gap-1.5 text-xs font-semibold text-primary">
                    <Award className="w-3.5 h-3.5 text-secondary" />
                    <span>{currentMember.experience}</span>
                  </div>
                  <span className="text-[10px] uppercase font-bold tracking-wider text-secondary bg-secondary/10 px-2 py-0.5 rounded-md">
                    {currentMember.department}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Right Column: Editorial Typography & Philosophy */}
          <div className="lg:col-span-7 flex flex-col justify-between space-y-6">
            
            {/* Top Tag & Italic Editorial Headline */}
            <div className="space-y-3">
              <div className="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.25em] text-[#8C6D58] dark:text-secondary">
                <Sparkles className="w-3.5 h-3.5 text-secondary" />
                <span>Our Leadership &amp; Creative Craft</span>
              </div>

              {/* Italic Serif Highlight Headline (Like Interflora "Celebrating 100 Incredible Years") */}
              <h3 className="font-serif italic text-2xl sm:text-3xl lg:text-4xl leading-tight text-[#8F5A42] dark:text-[#E2AB65] font-normal transition-all duration-300">
                "{currentMember.headline}"
              </h3>
            </div>

            {/* Rich Editorial Story Paragraph */}
            <p className="text-[#554339] dark:text-muted-foreground/90 text-sm sm:text-base lg:text-[17px] leading-relaxed font-normal">
              {currentMember.story}
            </p>

            {/* Highlights Chips */}
            <div className="flex flex-wrap gap-2 pt-1">
              {currentMember.highlights.map((highlight, i) => (
                <div
                  key={i}
                  className="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg bg-white/80 dark:bg-card/80 border border-[#E0CFBF] dark:border-border text-[#634E42] dark:text-foreground font-medium shadow-sm"
                >
                  <Star className="w-3 h-3 text-secondary fill-secondary" />
                  <span>{highlight}</span>
                </div>
              ))}
            </div>

            {/* Person Bio, Name & Title Signature Footer */}
            <div className="pt-5 border-t border-[#EADBCE] dark:border-secondary/20 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <h4 className="font-serif text-2xl sm:text-3xl font-bold text-primary tracking-tight">
                  {currentMember.name}
                </h4>
                <p className="text-[#8C6D58] dark:text-secondary text-sm sm:text-base font-medium mt-0.5">
                  {currentMember.role}
                </p>
              </div>

              {/* Prev / Next Navigation Controls */}
              <div className="flex items-center gap-2.5 self-end sm:self-center">
                <button
                  onClick={handlePrev}
                  className="w-10 h-10 rounded-full bg-white dark:bg-card border border-[#D8C0B0] dark:border-secondary/30 text-primary hover:bg-secondary hover:text-black hover:border-secondary flex items-center justify-center transition-all duration-300 shadow-sm hover:scale-105 active:scale-95"
                  aria-label="Previous Team Member"
                >
                  <ChevronLeft className="w-5 h-5" />
                </button>
                
                <span className="text-xs font-semibold text-[#8C6D58] dark:text-muted-foreground px-1">
                  0{activeIndex + 1} / 0{TEAM_MEMBERS.length}
                </span>

                <button
                  onClick={handleNext}
                  className="w-10 h-10 rounded-full bg-white dark:bg-card border border-[#D8C0B0] dark:border-secondary/30 text-primary hover:bg-secondary hover:text-black hover:border-secondary flex items-center justify-center transition-all duration-300 shadow-sm hover:scale-105 active:scale-95"
                  aria-label="Next Team Member"
                >
                  <ChevronRight className="w-5 h-5" />
                </button>
              </div>
            </div>

          </div>

        </div>

        {/* Carousel indicator dots below */}
        <div className="flex justify-center gap-2 mt-8 pt-4 border-t border-[#EADBCE]/50 dark:border-border/30">
          {TEAM_MEMBERS.map((_, index) => (
            <button
              key={index}
              onClick={() => setActiveIndex(index)}
              className={cn(
                'h-2 rounded-full transition-all duration-300',
                activeIndex === index
                  ? 'bg-secondary w-8 shadow-sm'
                  : 'bg-[#D8C0B0] dark:bg-muted-foreground/30 w-2 hover:bg-secondary/60'
              )}
              aria-label={`Go to slide ${index + 1}`}
            />
          ))}
        </div>
      </div>
    </div>
  );
}
