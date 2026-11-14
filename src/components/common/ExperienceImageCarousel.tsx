import React, { useState, useEffect, useCallback } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

interface ExperienceImageCarouselProps {
  images?: string[];
  fallbackImage?: string;
  alt: string;
  className?: string;
  imgClassName?: string;
  intervalMs?: number;
  showControls?: boolean;
  children?: React.ReactNode;
}

export default function ExperienceImageCarousel({
  images = [],
  fallbackImage = '/images/moments/moment_event.jpg',
  alt,
  className,
  imgClassName,
  intervalMs = 10000, // 10-second auto-roll as requested
  showControls = true,
  children
}: ExperienceImageCarouselProps) {
  // Normalize images list
  const validImages = React.useMemo(() => {
    if (!Array.isArray(images) || images.length === 0) {
      return fallbackImage ? [fallbackImage] : [];
    }
    const filtered = images.filter(Boolean);
    return filtered.length > 0 ? filtered : (fallbackImage ? [fallbackImage] : []);
  }, [images, fallbackImage]);

  const [currentIndex, setCurrentIndex] = useState(0);
  const [isPaused, setIsPaused] = useState(false);

  // Auto-roll every 10 seconds if more than 1 image
  useEffect(() => {
    if (validImages.length <= 1 || isPaused) return;

    const timer = setInterval(() => {
      setCurrentIndex((prev) => (prev + 1) % validImages.length);
    }, intervalMs);

    return () => clearInterval(timer);
  }, [validImages.length, intervalMs, isPaused]);

  // Keep index within bounds if list changes
  useEffect(() => {
    if (currentIndex >= validImages.length) {
      setCurrentIndex(0);
    }
  }, [validImages.length, currentIndex]);

  const handleNext = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setCurrentIndex((prev) => (prev + 1) % validImages.length);
  }, [validImages.length]);

  const handlePrev = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setCurrentIndex((prev) => (prev - 1 + validImages.length) % validImages.length);
  }, [validImages.length]);

  const handleDotClick = useCallback((idx: number, e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setCurrentIndex(idx);
  }, []);

  return (
    <div
      className={cn("relative overflow-hidden group select-none", className)}
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
    >
      {/* Slides */}
      {validImages.map((src, idx) => {
        const isCurrent = idx === currentIndex;
        return (
          <img
            key={`${src}-${idx}`}
            src={src}
            alt={`${alt} slide ${idx + 1}`}
            loading={idx === 0 ? "eager" : "lazy"}
            className={cn(
              "absolute inset-0 w-full h-full object-cover transition-opacity duration-700 ease-in-out",
              isCurrent ? "opacity-100 z-0" : "opacity-0 pointer-events-none -z-10",
              imgClassName
            )}
            onError={(e) => {
              if (fallbackImage) {
                (e.currentTarget as HTMLImageElement).src = fallbackImage;
              }
            }}
          />
        );
      })}

      {/* Overlays / badges / headers provided as children */}
      {children}

      {/* Multi-image interactive controls: Next / Prev arrows & Dots */}
      {showControls && validImages.length > 1 && (
        <>
          {/* Previous image button */}
          <button
            type="button"
            onClick={handlePrev}
            aria-label="Previous image"
            className="absolute left-2.5 top-1/2 -translate-y-1/2 z-20 w-8 h-8 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center backdrop-blur-md border border-white/20 transition-all duration-300 opacity-90 sm:opacity-0 sm:group-hover:opacity-100 hover:scale-110 shadow-lg cursor-pointer"
          >
            <ChevronLeft className="w-4 h-4 text-white" />
          </button>

          {/* Next image button */}
          <button
            type="button"
            onClick={handleNext}
            aria-label="Next image"
            className="absolute right-2.5 top-1/2 -translate-y-1/2 z-20 w-8 h-8 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center backdrop-blur-md border border-white/20 transition-all duration-300 opacity-90 sm:opacity-0 sm:group-hover:opacity-100 hover:scale-110 shadow-lg cursor-pointer"
          >
            <ChevronRight className="w-4 h-4 text-white" />
          </button>

          {/* Slide dots and counter */}
          <div className="absolute bottom-3 left-1/2 -translate-x-1/2 z-20 flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-black/55 backdrop-blur-md border border-white/15 pointer-events-auto">
            {validImages.map((_, idx) => (
              <button
                key={idx}
                type="button"
                onClick={(e) => handleDotClick(idx, e)}
                aria-label={`Jump to image ${idx + 1}`}
                className={cn(
                  "h-1.5 rounded-full transition-all duration-300 cursor-pointer",
                  idx === currentIndex
                    ? "w-4 bg-amber-400 shadow-sm"
                    : "w-1.5 bg-white/50 hover:bg-white"
                )}
              />
            ))}
          </div>
        </>
      )}
    </div>
  );
}
