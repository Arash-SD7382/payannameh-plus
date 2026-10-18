import type { APIRoute } from 'astro';
import { metaPages, metaDefault } from '../i18n/cmsSchema';
import { ui } from '../i18n/ui';

// Read by the server (cms-api/patch.php) to know which built pages carry an editable title/description,
// and what the original values are. Not used by visitors.
export const GET: APIRoute = () => {
  const pages: any[] = [];
  for (const lang of ['fa', 'en'] as const) {
    for (const pg of metaPages) {
      const d = metaDefault(lang, pg.key);
      pages.push({ lang, key: pg.key, file: (lang === 'en' ? 'en/' : '') + pg.file, title: d.title, desc: d.desc, faq: pg.key === 'home' });
    }
  }
  const faq = { fa: (ui.fa as any).faq, en: (ui.en as any).faq };
  return new Response(JSON.stringify({ v: 1, pages, faq }), { headers: { 'Content-Type': 'application/json; charset=utf-8' } });
};
