import type { APIRoute } from 'astro';
import { getCollection } from 'astro:content';
import { categories } from '../content/config';
import { ui, readMins } from '../i18n/ui';

// Read by the server (cms-api/items.php): the projects and services that were built into the site,
// plus the labels it needs to build the same pages for items added in the panel. Not used by visitors.
export const GET: APIRoute = async ({ site }) => {
  const out: any = { v: 1, site: site ? site.href.replace(/\/$/, '') : '', categories, projects: [], services: [], strings: {} };
  for (const e of await getCollection('projects')) {
    out.projects.push({ lang: e.slug.startsWith('fa/') ? 'fa' : 'en', key: e.slug.replace(/^fa\//, ''),
      data: { title: e.data.title, date: e.data.date.toISOString().slice(0, 10), category: e.data.category, excerpt: e.data.excerpt,
        cover: e.data.cover || '', coverAlt: e.data.coverAlt || '', video: e.data.video || '', featured: !!e.data.featured },
      body: e.body, mins: readMins(e.body) });
  }
  for (const e of await getCollection('services')) {
    out.services.push({ lang: e.slug.startsWith('fa/') ? 'fa' : 'en', key: e.slug.replace(/^fa\//, ''),
      data: { title: e.data.title, order: e.data.order, excerpt: e.data.excerpt, image: e.data.image, imageAlt: e.data.imageAlt || '' },
      body: e.body });
  }
  for (const lang of ['fa', 'en'] as const) {
    const t: any = ui[lang];
    out.strings[lang] = { dir: t.dir, brand: t.brand, home: t.home, nav: t.nav, cat: t.cat, by: t.by, minRead: t.minRead, prev: t.prev, next: t.next,
      onThisPage: t.onThisPage, related: t.related, relatedServices: t.relatedServices, playVideo: t.playVideo, videoFallback: t.videoFallback,
      readMoreBtn: t.readMoreBtn, foreignBadge: t.foreignBadge, featured: t.featured, latest: t.latest, noProjects: t.noProjects,
      ctaTitle: t.ctaTitle, ctaText: t.ctaText };
  }
  return new Response(JSON.stringify(out), { headers: { 'Content-Type': 'application/json; charset=utf-8' } });
};
