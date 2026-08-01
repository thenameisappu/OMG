// Centralized Application Configuration

/**
 * Single configurable Base URL for API requests.
 * Reads from VITE_API_BASE_URL or VITE_BASE_URL or BASE_URL in .env, defaulting to '/backend'.
 */
export const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL ||
  import.meta.env.VITE_BASE_URL ||
  import.meta.env.BASE_URL ||
  '/backend';

/**
 * Site / Backend Host Base URL.
 * Reads from VITE_SITE_URL or VITE_BACKEND_URL or VITE_BASE_URL in .env.
 */
export const SITE_URL =
  import.meta.env.VITE_SITE_URL ||
  import.meta.env.VITE_BACKEND_URL ||
  import.meta.env.VITE_BASE_URL ||
  (typeof window !== 'undefined' ? window.location.origin : '');
