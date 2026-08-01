import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import svgr from 'vite-plugin-svgr';
import path from 'path';
import { miaodaDevPlugin } from "miaoda-sc-plugin";

export default defineConfig(({ mode }) => {
  // Load env file based on `mode` in the current working directory.
  const env = loadEnv(mode, process.cwd(), '');

  // Extract target domain from environment variables (.env)
  let rawUrl = env.VITE_BACKEND_URL || env.VITE_SITE_URL || env.VITE_BASE_URL || env.BASE_URL || 'https://ghostwhite-kudu-967584.hostingersite.com';
  // Strip trailing /backend if present to isolate origin
  let targetOrigin = rawUrl.replace(/\/backend\/?$/, '');

  return {
    base: './',
    plugins: [
      react(),
      svgr({
        svgrOptions: {
          icon: true,
          exportType: 'named',
          namedExport: 'ReactComponent',
        },
      }),
      miaodaDevPlugin()
    ],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, './src'),
      },
    },
    server: {
      host: '0.0.0.0',
      port: 5173,
      proxy: {
        // Dynamically proxied based on .env configuration
        '/backend': {
          target: targetOrigin,
          changeOrigin: true,
          secure: true,
          headers: {
            'Origin': 'http://localhost:5173'
          }
        },
      },
    },
  };
});
