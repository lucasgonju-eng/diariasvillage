import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
  publicDir: false,
  build: {
    outDir: resolve(import.meta.dirname, 'public/assets/admin-dist'),
    emptyOutDir: true,
    manifest: 'manifest.json',
    minify: 'esbuild',
    sourcemap: false,
    rollupOptions: {
      input: {
        admin: resolve(import.meta.dirname, 'frontend/admin/main.ts'),
      },
    },
  },
});
