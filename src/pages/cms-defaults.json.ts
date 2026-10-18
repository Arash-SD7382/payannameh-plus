import type { APIRoute } from 'astro';
import { buildGroups, defaultsFor, type SvcInfo } from '../i18n/cmsSchema';
import { servicesOwn } from '../i18n/content';

// Built once at build time. The panel reads it to show the original texts and field names.
export const GET: APIRoute = async () => {
  const fa = await servicesOwn('fa');
  const en = await servicesOwn('en');
  const pick = (list: typeof fa) => Object.fromEntries(list.map((e) => [e.key, { title: e.data.title, excerpt: e.data.excerpt }]));
  const svc: SvcInfo = { slugs: fa.map((e) => e.key), fa: pick(fa), en: pick(en) };
  return new Response(
    JSON.stringify({ groups: buildGroups(svc), defaults: { fa: defaultsFor('fa', svc), en: defaultsFor('en', svc), all: defaultsFor('all', svc) } }),
    { headers: { 'Content-Type': 'application/json; charset=utf-8' } }
  );
};
