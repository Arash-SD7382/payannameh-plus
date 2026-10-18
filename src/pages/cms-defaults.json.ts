import type { APIRoute } from 'astro';
import { buildGroups, defaultsFor } from '../i18n/cmsSchema';

// Built once at build time. The panel reads it to show the original texts and field names.
export const GET: APIRoute = () =>
  new Response(
    JSON.stringify({ groups: buildGroups(), defaults: { fa: defaultsFor('fa'), en: defaultsFor('en') } }),
    { headers: { 'Content-Type': 'application/json; charset=utf-8' } }
  );
