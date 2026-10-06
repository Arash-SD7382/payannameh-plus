import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
// TODO: replace with your real domain after deploying
export default defineConfig({ site: 'https://payannameh-plus.netlify.app', integrations: [sitemap()] });
