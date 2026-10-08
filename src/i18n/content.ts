import { getCollection } from 'astro:content';
import type { Lang } from './ui';
// Files in src/content/projects/fa/ are Persian; files directly in src/content/projects/ are English.
export async function projectsOwn(lang: Lang) {
  const all = await getCollection('projects');
  return all
    .filter((e) => (e.slug.startsWith('fa/') ? 'fa' : 'en') === lang)
    .map((e) => ({ ...e, key: e.slug.replace(/^fa\//, ''), itemLang: lang, foreign: false }))
    .sort((a, b) => +b.data.date - +a.data.date);
}
// Every project, in the visitor's language when it exists, otherwise in the other language (marked foreign).
export async function projectsFor(lang: Lang) {
  const own = await projectsOwn(lang);
  const other = await projectsOwn(lang === 'fa' ? 'en' : 'fa');
  const have = new Set(own.map((p) => p.key));
  const extra = other.filter((p) => !have.has(p.key)).map((p) => ({ ...p, foreign: true }));
  return [...own, ...extra].sort((a, b) => +b.data.date - +a.data.date);
}
