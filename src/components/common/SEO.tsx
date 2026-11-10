import React, { useEffect } from 'react';
import { SITE_URL } from '@/config';

export interface SEOProps {
  title?: string;
  description?: string;
  image?: string;
  url?: string;
  type?: 'website' | 'product' | 'article';
  keywords?: string;
  price?: number | string;
  currency?: string;
  availability?: 'in stock' | 'out of stock' | 'preorder';
  category?: string;
  sku?: string;
}

const DEFAULT_TITLE = 'OMG – Oh My Gudness | Luxury Floral Arrangements & Gifts in Bangalore';
const DEFAULT_DESCRIPTION =
  "Bangalore's premier floral & surprise house. Handcrafted fresh bouquets, luxury hampers, and bespoke surprise experiences with guaranteed 2-hour express delivery.";
const DEFAULT_IMAGE = `${SITE_URL || 'https://ohmygudness.in'}/images/hero/hero_luxury_store.jpg`;
const SITE_NAME = 'OMG – Oh My Gudness';

function setMetaTag(selector: string, attributeName: string, attributeValue: string, content: string) {
  let element = document.head.querySelector(selector) as HTMLMetaElement | null;
  if (!element) {
    element = document.createElement('meta');
    element.setAttribute(attributeName, attributeValue);
    document.head.appendChild(element);
  }
  element.setAttribute('content', content);
}

function setCanonical(href: string) {
  let element = document.head.querySelector('link[rel="canonical"]') as HTMLLinkElement | null;
  if (!element) {
    element = document.createElement('link');
    element.setAttribute('rel', 'canonical');
    document.head.appendChild(element);
  }
  element.setAttribute('href', href);
}

function setJsonLd(id: string, data: object) {
  let script = document.getElementById(id) as HTMLScriptElement | null;
  if (!script) {
    script = document.createElement('script');
    script.id = id;
    script.type = 'application/ld+json';
    document.head.appendChild(script);
  }
  script.text = JSON.stringify(data);
}

export const SEO: React.FC<SEOProps> = ({
  title,
  description,
  image,
  url,
  type = 'website',
  keywords,
  price,
  currency = 'INR',
  availability = 'in stock',
  category,
  sku,
}) => {
  useEffect(() => {
    const baseSiteUrl = SITE_URL || 'https://ohmygudness.in';
    const finalTitle = title ? (title.includes('OMG') ? title : `${title} | OMG`) : DEFAULT_TITLE;
    const finalDescription = description || DEFAULT_DESCRIPTION;
    let finalImage = image || DEFAULT_IMAGE;
    if (finalImage.startsWith('/')) {
      finalImage = `${baseSiteUrl}${finalImage}`;
    }
    const finalUrl = url ? (url.startsWith('http') ? url : `${baseSiteUrl}${url.startsWith('/') ? '' : '/'}${url}`) : window.location.href;

    // 1. Standard Document Title & Description
    document.title = finalTitle;
    setMetaTag('meta[name="description"]', 'name', 'description', finalDescription);
    if (keywords) {
      setMetaTag('meta[name="keywords"]', 'name', 'keywords', keywords);
    }
    setCanonical(finalUrl);

    // 2. OpenGraph Meta Tags
    setMetaTag('meta[property="og:site_name"]', 'property', 'og:site_name', SITE_NAME);
    setMetaTag('meta[property="og:title"]', 'property', 'og:title', finalTitle);
    setMetaTag('meta[property="og:description"]', 'property', 'og:description', finalDescription);
    setMetaTag('meta[property="og:image"]', 'property', 'og:image', finalImage);
    setMetaTag('meta[property="og:image:alt"]', 'property', 'og:image:alt', finalTitle);
    setMetaTag('meta[property="og:url"]', 'property', 'og:url', finalUrl);
    setMetaTag('meta[property="og:type"]', 'property', 'og:type', type);
    setMetaTag('meta[property="og:locale"]', 'property', 'og:locale', 'en_IN');

    // 3. Twitter Card Meta Tags
    setMetaTag('meta[name="twitter:card"]', 'name', 'twitter:card', 'summary_large_image');
    setMetaTag('meta[name="twitter:title"]', 'name', 'twitter:title', finalTitle);
    setMetaTag('meta[name="twitter:description"]', 'name', 'twitter:description', finalDescription);
    setMetaTag('meta[name="twitter:image"]', 'name', 'twitter:image', finalImage);

    // 4. E-Commerce Product Meta Tags
    if (type === 'product' && price) {
      setMetaTag('meta[property="product:price:amount"]', 'property', 'product:price:amount', String(price));
      setMetaTag('meta[property="product:price:currency"]', 'property', 'product:price:currency', currency);
      setMetaTag('meta[property="product:availability"]', 'property', 'product:availability', availability);
      if (category) {
        setMetaTag('meta[property="product:category"]', 'property', 'product:category', category);
      }

      // JSON-LD Product Schema for Google Search Snippets
      setJsonLd('product-jsonld', {
        '@context': 'https://schema.org/',
        '@type': 'Product',
        name: title || 'Luxury Floral Product',
        image: [finalImage],
        description: finalDescription,
        sku: sku || 'OMG-LUXURY',
        brand: {
          '@type': 'Brand',
          name: 'Oh My Gudness',
        },
        offers: {
          '@type': 'Offer',
          url: finalUrl,
          priceCurrency: currency,
          price: price,
          availability: availability === 'in stock' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
          itemCondition: 'https://schema.org/NewCondition',
          seller: {
            '@type': 'Organization',
            name: 'Oh My Gudness',
          },
        },
      });
    }

    // Cleanup on unmount or navigation back
    return () => {
      const prodJsonLd = document.getElementById('product-jsonld');
      if (prodJsonLd) prodJsonLd.remove();
    };
  }, [title, description, image, url, type, keywords, price, currency, availability, category, sku]);

  return null;
};

export default SEO;

