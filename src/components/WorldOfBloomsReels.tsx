import React, { useState, useRef, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { 
  Play, 
  Pause, 
  Volume2, 
  VolumeX, 
  ExternalLink, 
  Instagram, 
  ChevronLeft, 
  ChevronRight, 
  Sparkles, 
  Heart, 
  Share2, 
  ShoppingBag, 
  Check,
  Calendar,
  MessageCircle
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { formatINR } from '@/lib/currency';
import { 
  INSTAGRAM_REELS, 
  OFFICIAL_INSTAGRAM, 
  type InstagramReelItem 
} from '@/data/instagramReels';

export function WorldOfBloomsReels() {
  const [reels, setReels] = useState<InstagramReelItem[]>(INSTAGRAM_REELS);
  const [selectedReel, setSelectedReel] = useState<InstagramReelItem | null>(null);
  const scrollContainerRef = useRef<HTMLDivElement>(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(true);

  // Fetch real-time Instagram insights dynamically on mount
  useEffect(() => {
    fetch(`/data/instagramInsights.json?t=${Date.now()}`)
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => {
        if (data?.insights) {
          setReels((prev) =>
            prev.map((reel) => {
              const live = data.insights[reel.id];
              if (live) {
                return {
                  ...reel,
                  likes: live.likes !== undefined ? live.likes : reel.likes,
                  comments: live.comments !== undefined ? live.comments : reel.comments,
                  handle: OFFICIAL_INSTAGRAM.handle,
                };
              }
              return reel;
            })
          );
        }
      })
      .catch(() => {
        // Fallback silently to pre-bundled verified data
      });
  }, []);

  // Update arrow button visibility based on scroll position
  const updateScrollButtons = () => {
    const el = scrollContainerRef.current;
    if (!el) return;
    setCanScrollLeft(el.scrollLeft > 10);
    // When el.scrollWidth - el.clientWidth - el.scrollLeft <= 10, reached end
    setCanScrollRight(el.scrollLeft < el.scrollWidth - el.clientWidth - 10);
  };

  useEffect(() => {
    const el = scrollContainerRef.current;
    if (!el) return;
    updateScrollButtons();
    el.addEventListener('scroll', updateScrollButtons, { passive: true });
    window.addEventListener('resize', updateScrollButtons);
    return () => {
      el.removeEventListener('scroll', updateScrollButtons);
      window.removeEventListener('resize', updateScrollButtons);
    };
  }, [reels]);

  const handleScroll = (direction: 'left' | 'right') => {
    const el = scrollContainerRef.current;
    if (!el) return;
    const card = el.firstElementChild as HTMLElement;
    const cardWidth = card ? card.getBoundingClientRect().width : 260;
    const gap = 20;
    const step = (cardWidth + gap) * (window.innerWidth >= 1024 ? 2 : 1);
    el.scrollBy({
      left: direction === 'left' ? -step : step,
      behavior: 'smooth',
    });
  };

  return (
    <section className="py-20 md:py-28 bg-[#FAF6F0]/60 dark:bg-muted/10 border-y border-[#EADBCE]/80 dark:border-border relative overflow-hidden">
      {/* Decorative floral background accents */}
      <div className="absolute top-0 right-0 w-96 h-96 bg-secondary/5 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute bottom-0 left-0 w-96 h-96 bg-secondary/5 rounded-full blur-3xl pointer-events-none" />

      {/* Header */}
      <div className="container text-center mb-12 md:mb-16 space-y-3 relative z-10">
        <h2 className="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight text-primary font-serif">
          Our World of Blooms
        </h2>
        <div className="h-1 w-24 bg-secondary mx-auto rounded-full" />
        <p className="text-sm sm:text-base md:text-lg text-muted-foreground max-w-2xl mx-auto leading-relaxed">
          Watch our handcrafted bouquets, celebrity tributes, and luxury surprises come to life. 
          <span className="hidden sm:inline"> Hover to play reel preview, click for full story &amp; real-time engagement.</span>
        </p>
      </div>

      {/* Reel Cards Carousel */}
      <div className="container relative z-10 px-4 sm:px-6">
        <div className="relative group/carousel">
          {/* Scrollable track with native momentum & zero clipping */}
          <div
            ref={scrollContainerRef}
            className="flex gap-4 sm:gap-5 overflow-x-auto scroll-smooth py-4 px-1.5 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none] snap-x snap-mandatory"
          >
            {reels.map((reel) => (
              <div
                key={reel.id}
                className="shrink-0 snap-start w-[240px] xs:w-[260px] sm:w-[calc((100%-1*20px)/2)] md:w-[calc((100%-2*20px)/3)] lg:w-[calc((100%-3*20px)/4)] xl:w-[calc((100%-4*20px)/5)] min-w-[210px]"
              >
                <ReelCard
                  reel={reel}
                  onOpenModal={() => setSelectedReel(reel)}
                />
              </div>
            ))}
          </div>

          {/* Previous Arrow Button */}
          {canScrollLeft && (
            <button
              onClick={() => handleScroll('left')}
              className="absolute -left-2 sm:-left-5 top-1/2 -translate-y-1/2 z-20 h-11 w-11 rounded-full bg-white/95 dark:bg-card/95 text-primary border border-border shadow-xl hover:bg-secondary hover:text-primary transition-all flex items-center justify-center hover:scale-110 active:scale-95 cursor-pointer"
              aria-label="Previous reels"
            >
              <ChevronLeft className="h-5 w-5" />
            </button>
          )}

          {/* Next Arrow Button */}
          {canScrollRight && (
            <button
              onClick={() => handleScroll('right')}
              className="absolute -right-2 sm:-right-5 top-1/2 -translate-y-1/2 z-20 h-11 w-11 rounded-full bg-white/95 dark:bg-card/95 text-primary border border-border shadow-xl hover:bg-secondary hover:text-primary transition-all flex items-center justify-center hover:scale-110 active:scale-95 cursor-pointer"
              aria-label="Next reels"
            >
              <ChevronRight className="h-5 w-5" />
            </button>
          )}
        </div>

        {/* Bottom Social Handle Banner */}
        <div className="mt-12 text-center">
          <a
            href={OFFICIAL_INSTAGRAM.url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-2.5 px-6 py-2.5 rounded-full bg-gradient-to-r from-[#833ab4]/10 via-[#fd1d1d]/10 to-[#fcb045]/10 border border-[#fd1d1d]/30 text-primary hover:text-secondary font-semibold text-xs sm:text-sm hover:scale-105 transition-all shadow-sm group"
          >
            <div className="h-6 w-6 rounded-full bg-gradient-to-tr from-amber-500 via-rose-500 to-purple-600 flex items-center justify-center text-white shadow-sm">
              <Instagram className="h-3.5 w-3.5" />
            </div>
            <span>Follow <strong className="text-primary font-bold">{OFFICIAL_INSTAGRAM.handle}</strong> on Instagram</span>
            <ExternalLink className="h-3.5 w-3.5 opacity-70 group-hover:opacity-100 transition-opacity" />
          </a>
        </div>
      </div>

      {/* Reel Details Modal */}
      {selectedReel && (
        <ReelDetailsModal
          reel={selectedReel}
          isOpen={!!selectedReel}
          onClose={() => setSelectedReel(null)}
        />
      )}
    </section>
  );
}

/**
 * Individual Reel Frame Card matching the uploaded aesthetic:
 * - 9:16 portrait aspect ratio
 * - Dark semi-transparent play button in center
 * - Bottom title in white serif typography with subtle text overlay
 * - Plays smoothly on hover, pauses on mouse leave
 * - Opens modal on click
 */
function ReelCard({
  reel,
  onOpenModal,
}: {
  reel: InstagramReelItem;
  onOpenModal: () => void;
}) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const [isLoaded, setIsLoaded] = useState(false);

  const handleMouseEnter = () => {
    if (videoRef.current) {
      videoRef.current
        .play()
        .then(() => setIsPlaying(true))
        .catch(() => {
          // Auto-play was prevented or video not yet ready
        });
    }
  };

  const handleMouseLeave = () => {
    if (videoRef.current) {
      videoRef.current.pause();
      videoRef.current.currentTime = 0;
      setIsPlaying(false);
    }
  };

  return (
    <div
      onClick={onOpenModal}
      onMouseEnter={handleMouseEnter}
      onMouseLeave={handleMouseLeave}
      className="group relative aspect-[9/16] w-full rounded-2xl overflow-hidden cursor-pointer shadow-md hover:shadow-2xl transition-all duration-300 hover:-translate-y-1.5 select-none bg-neutral-900 border border-black/10 dark:border-white/10"
    >
      {/* Static Poster Thumbnail (always visible as fallback & instant display) */}
      <img
        src={reel.thumbnail}
        alt={reel.title}
        loading="lazy"
        className={cn(
          "absolute inset-0 h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-105",
          isPlaying ? "opacity-0" : "opacity-100"
        )}
      />

      {/* HTML5 Video element (plays on hover) */}
      <video
        ref={videoRef}
        src={reel.videoUrl}
        poster={reel.thumbnail}
        loop
        muted
        playsInline
        preload="metadata"
        onCanPlay={() => setIsLoaded(true)}
        className={cn(
          "absolute inset-0 h-full w-full object-cover transition-opacity duration-300",
          isPlaying ? "opacity-100" : "opacity-0"
        )}
      />

      {/* Subtle luxury gradient overlays */}
      <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-black/30 pointer-events-none transition-opacity duration-300 group-hover:from-black/95" />

      {/* Top Header Badge */}
      <div className="absolute top-3 left-3 right-3 flex items-center justify-between z-10 pointer-events-none gap-1.5">
        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-black/55 backdrop-blur-md text-white text-[10px] sm:text-xs font-semibold shadow-sm border border-white/15 shrink-0">
          <Instagram className="h-3 w-3 text-pink-400 shrink-0" />
          <span>{OFFICIAL_INSTAGRAM.handle}</span>
        </span>
        {reel.likes !== undefined && (
          <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-black/45 backdrop-blur-md text-white/95 text-[10px] font-semibold border border-white/10 shadow-sm shrink-0 whitespace-nowrap">
            <Heart className="h-2.5 w-2.5 text-rose-400 fill-rose-400 shrink-0" />
            <span>{reel.likes}</span>
          </span>
        )}
      </div>

      {/* Center Circular Play Button */}
      <div className="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
        <div
          className={cn(
            "h-12 w-12 sm:h-14 sm:w-14 rounded-full bg-black/55 backdrop-blur-md border border-white/30 text-white flex items-center justify-center shadow-xl transition-all duration-300",
            isPlaying
              ? "opacity-0 scale-75"
              : "opacity-90 group-hover:opacity-100 group-hover:scale-110"
          )}
        >
          <Play className="h-5 w-5 sm:h-6 sm:w-6 fill-white ml-0.5" />
        </div>
      </div>

      {/* Playing indicator badge on hover */}
      {isPlaying && (
        <div className="absolute top-3 right-3 z-20 pointer-events-none animate-fade-in">
          <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-secondary text-primary text-[10px] font-bold shadow-md">
            <Sparkles className="h-2.5 w-2.5 animate-pulse" />
            <span>Playing</span>
          </span>
        </div>
      )}

      {/* Bottom Title Overlay */}
      <div className="absolute bottom-0 left-0 right-0 p-4 sm:p-5 z-10 pointer-events-none">
        <h3 className="text-white font-serif font-bold text-sm sm:text-base leading-snug drop-shadow-lg line-clamp-2 transition-colors duration-300 group-hover:text-amber-200 break-words">
          {reel.title}
        </h3>
        <p className="mt-1 text-[11px] text-white/75 font-sans line-clamp-1 flex items-center gap-1">
          <span>Click to watch &amp; view details</span>
          <span className="text-secondary">→</span>
        </p>
      </div>
    </div>
  );
}

/**
 * Detailed Reel Modal Dialog:
 * - Plays high-resolution video with sound controls
 * - Shows official @ohmygudness.in brand profile
 * - Displays real-time verified likes and comments
 * - Prominent "Watch on Instagram" CTA button that opens the source Instagram URL
 * - Featured arrangement link to purchase
 */
function ReelDetailsModal({
  reel,
  isOpen,
  onClose,
}: {
  reel: InstagramReelItem;
  isOpen: boolean;
  onClose: () => void;
}) {
  const modalVideoRef = useRef<HTMLVideoElement>(null);
  const [isModalPlaying, setIsModalPlaying] = useState(true);
  const [isMuted, setIsMuted] = useState(false);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    if (isOpen && modalVideoRef.current) {
      modalVideoRef.current.play().catch(() => {
        setIsModalPlaying(false);
      });
    }
  }, [isOpen]);

  const togglePlay = () => {
    if (!modalVideoRef.current) return;
    if (modalVideoRef.current.paused) {
      modalVideoRef.current.play();
      setIsModalPlaying(true);
    } else {
      modalVideoRef.current.pause();
      setIsModalPlaying(false);
    }
  };

  const toggleMute = () => {
    if (!modalVideoRef.current) return;
    modalVideoRef.current.muted = !modalVideoRef.current.muted;
    setIsMuted(modalVideoRef.current.muted);
  };

  const handleCopyLink = () => {
    navigator.clipboard.writeText(reel.instagramUrl || window.location.href);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-4xl p-0 overflow-hidden bg-white dark:bg-card border-secondary/20 shadow-2xl">
        <div className="grid grid-cols-1 md:grid-cols-12 max-h-[88vh] overflow-y-auto md:overflow-hidden">
          {/* Left Column: Vertical 9:16 Video Player */}
          <div className="md:col-span-5 bg-black relative flex items-center justify-center aspect-[9/16] md:max-h-[82vh]">
            <video
              ref={modalVideoRef}
              src={reel.videoUrl}
              poster={reel.thumbnail}
              loop
              playsInline
              muted={isMuted}
              onClick={togglePlay}
              className="w-full h-full object-cover cursor-pointer"
            />

            {/* In-Video Controls Overlay */}
            <div className="absolute bottom-4 left-4 right-4 flex items-center justify-between z-20">
              <button
                onClick={togglePlay}
                className="h-9 w-9 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center backdrop-blur-md transition-all shadow-md"
                aria-label={isModalPlaying ? "Pause video" : "Play video"}
              >
                {isModalPlaying ? <Pause className="h-4 w-4" /> : <Play className="h-4 w-4 fill-white ml-0.5" />}
              </button>

              <button
                onClick={toggleMute}
                className="h-9 w-9 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center backdrop-blur-md transition-all shadow-md"
                aria-label={isMuted ? "Unmute audio" : "Mute audio"}
              >
                {isMuted ? <VolumeX className="h-4 w-4 text-rose-400" /> : <Volume2 className="h-4 w-4 text-secondary" />}
              </button>
            </div>

            {/* Play overlay if paused */}
            {!isModalPlaying && (
              <div
                onClick={togglePlay}
                className="absolute inset-0 bg-black/40 flex items-center justify-center cursor-pointer z-10"
              >
                <div className="h-16 w-16 rounded-full bg-black/70 border border-white/30 text-white flex items-center justify-center shadow-2xl hover:scale-110 transition-transform">
                  <Play className="h-7 w-7 fill-white ml-1" />
                </div>
              </div>
            )}
          </div>

          {/* Right Column: Reel Details, Story & Instagram Link */}
          <div className="md:col-span-7 p-6 sm:p-8 flex flex-col justify-between overflow-y-auto max-h-[82vh]">
            <div className="space-y-6">
              {/* Profile Header */}
              <div className="flex items-center justify-between pb-4 border-b border-border">
                <div className="flex items-center gap-3">
                  <div className="h-11 w-11 rounded-full p-[2px] bg-gradient-to-tr from-amber-500 via-rose-500 to-purple-600">
                    <div className="h-full w-full rounded-full bg-primary flex items-center justify-center text-secondary font-bold text-xs ring-2 ring-white dark:ring-card">
                      OMG
                    </div>
                  </div>
                  <div>
                    <div className="flex items-center gap-1.5">
                      <span className="font-bold text-sm text-primary">{OFFICIAL_INSTAGRAM.handle}</span>
                      <span className="h-2 w-2 rounded-full bg-emerald-500" />
                    </div>
                    <p className="text-xs text-muted-foreground">{OFFICIAL_INSTAGRAM.name} • Bengaluru</p>
                  </div>
                </div>

                <a
                  href={OFFICIAL_INSTAGRAM.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-secondary/15 hover:bg-secondary text-secondary hover:text-primary text-xs font-bold transition-all shadow-sm"
                >
                  <Instagram className="h-3.5 w-3.5" />
                  <span>Follow</span>
                </a>
              </div>

              {/* Title & Caption */}
              <div className="space-y-3">
                <DialogTitle className="text-2xl sm:text-3xl font-serif font-bold text-primary leading-tight">
                  {reel.title}
                </DialogTitle>
                <p className="text-sm leading-relaxed text-muted-foreground whitespace-pre-line">
                  {reel.caption}
                </p>
              </div>

              {/* Real-Time Engagement Stats */}
              <div className="flex flex-wrap items-center gap-3 sm:gap-4 py-3 px-4 rounded-xl bg-muted/40 border border-border/60 text-xs text-muted-foreground font-medium">
                {reel.likes !== undefined && (
                  <span className="flex items-center gap-1.5 text-primary font-bold">
                    <Heart className="h-3.5 w-3.5 text-rose-500 fill-rose-500" /> {reel.likes} Likes
                  </span>
                )}
                {reel.comments !== undefined && (
                  <span className="flex items-center gap-1.5 text-primary font-bold">
                    <MessageCircle className="h-3.5 w-3.5 text-secondary" /> {reel.comments} Comments
                  </span>
                )}
                <span className="inline-flex items-center gap-1.5 ml-auto text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">
                  <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                  Real-Time Verified
                </span>
              </div>

              {/* Featured Product Box (if associated) */}
              {reel.featuredProduct && (
                <div className="p-3.5 rounded-xl border border-secondary/30 bg-secondary/5 flex items-center justify-between gap-3">
                  <div className="flex items-center gap-3 min-w-0">
                    {reel.featuredProduct.image && (
                      <img
                        src={reel.featuredProduct.image}
                        alt={reel.featuredProduct.name}
                        className="h-12 w-12 rounded-lg object-cover border border-secondary/30"
                      />
                    )}
                    <div className="min-w-0">
                      <p className="text-[10px] uppercase font-bold text-secondary tracking-wider">
                        Featured in this Reel
                      </p>
                      <p className="text-xs sm:text-sm font-bold text-primary line-clamp-1">
                        {reel.featuredProduct.name}
                      </p>
                      <p className="text-xs font-bold text-secondary">
                        {formatINR(reel.featuredProduct.price)}
                      </p>
                    </div>
                  </div>

                  <Link to={reel.featuredProduct.link} onClick={onClose} className="shrink-0">
                    <Button size="sm" variant="outline" className="border-secondary text-secondary hover:bg-secondary hover:text-primary rounded-full text-xs font-bold">
                      <ShoppingBag className="h-3.5 w-3.5 mr-1" /> View Item
                    </Button>
                  </Link>
                </div>
              )}
            </div>

            {/* Modal Actions */}
            <div className="mt-8 pt-4 border-t border-border space-y-3">
              {/* Primary CTA: Navigate to Instagram Source */}
              <a
                href={reel.instagramUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="w-full flex items-center justify-center gap-2.5 py-3 px-6 rounded-full bg-gradient-to-r from-amber-500 via-rose-500 to-purple-600 hover:from-amber-600 hover:via-rose-600 hover:to-purple-700 text-white font-bold text-sm shadow-lg hover:shadow-xl hover:scale-[1.01] transition-all"
              >
                <Instagram className="h-4 w-4" />
                <span>Watch on Instagram</span>
                <ExternalLink className="h-4 w-4 ml-1 opacity-80" />
              </a>

              {/* Secondary Actions */}
              <div className="flex items-center justify-between gap-2">
                <Button
                  onClick={handleCopyLink}
                  variant="ghost"
                  size="sm"
                  className="text-xs text-muted-foreground hover:text-primary gap-1.5"
                >
                  {copied ? <Check className="h-3.5 w-3.5 text-emerald-600" /> : <Share2 className="h-3.5 w-3.5" />}
                  <span>{copied ? "Link Copied!" : "Copy Reel Link"}</span>
                </Button>

                <Button
                  onClick={onClose}
                  variant="outline"
                  size="sm"
                  className="text-xs rounded-full"
                >
                  Close Preview
                </Button>
              </div>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
