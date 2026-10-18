import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
// TODO: replace with your real domain after deploying
export default defineConfig({ build: { inlineStylesheets: 'always' }, site: 'https://payannameh-plus.netlify.app', integrations: [sitemap({ filter: (page) => !page.includes('/cms-tpl/'), i18n: { defaultLocale: 'fa', locales: { fa: 'fa-IR', en: 'en-US' } } })] });
