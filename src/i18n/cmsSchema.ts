import { ui } from './ui';
import { team, groups as svcGroups } from '../data/services';

// Describes every site text the owner can edit from the panel (/panel/).
// Defaults always come from ui.ts, so the panel can show the original text and "reset to default" works.
// A template element becomes editable by carrying data-cms="<key>" with one of the keys below.

export type Field = { key: string; label: string; type: 'text' | 'textarea'; shared?: boolean; ltr?: boolean };

// Pages whose Google title/description the owner can edit. `file` is relative to the site root.
export const metaPages = [
  { key: 'home', label: 'صفحه اول', file: 'index.html' },
  { key: 'projects', label: 'صفحه پروژه‌ها', file: 'projects/index.html' },
  { key: 'services', label: 'صفحه خدمات', file: 'services/index.html' },
  { key: 'about', label: 'صفحه درباره ما', file: 'about/index.html' },
  { key: 'contact', label: 'صفحه تماس', file: 'contact/index.html' },
] as const;

export function metaDefault(lang: 'fa' | 'en', key: string): { title: string; desc: string } {
  const t: any = ui[lang];
  const title: Record<string, string> = { home: t.brand, projects: t.nav.projects, services: t.nav.services, about: t.nav.about, contact: t.contactUs };
  const desc: Record<string, string> = { home: t.metaDesc, projects: t.projectsDesc, services: t.servicesDesc, about: t.aboutDesc, contact: t.contactDesc };
  const base = title[key];
  return { title: base === t.brand ? base : `${base} | ${t.brand}`, desc: desc[key] };
}
// Service pages are read from the content folder at build time (see cms-defaults.json.ts).
export type SvcInfo = { slugs: string[]; fa: Record<string, { title: string; excerpt: string }>; en: Record<string, { title: string; excerpt: string }> };
export type Group = { id: string; title: string; hint?: string; fields: Field[]; list?: 'faqlist' };

const f = (key: string, label: string, type: 'text' | 'textarea' = 'text', extra: Partial<Field> = {}): Field => ({ key, label, type, ...extra });

export function getPath(obj: any, key: string): string | undefined {
  const v = key.split('.').reduce((o: any, k) => (o == null ? undefined : o[k]), obj);
  return typeof v === 'string' ? v : undefined;
}

const count = (name: 'stats' | 'abilities' | 'steps' | 'faq') =>
  Math.min((ui.fa as any)[name].length, (ui.en as any)[name].length);

export function buildGroups(): Group[] {
  const stats: Field[] = [];
  for (let i = 0; i < count('stats'); i++) {
    stats.push(f(`stats.${i}.0`, `آمار ${i + 1}: عدد`), f(`stats.${i}.1`, `آمار ${i + 1}: عنوان زیر عدد`));
  }
  const abilities: Field[] = [f('abilitiesTitle', 'عنوان بخش')];
  for (let i = 0; i < count('abilities'); i++) {
    abilities.push(f(`abilities.${i}.1`, `کارت ${i + 1}: عنوان`), f(`abilities.${i}.2`, `کارت ${i + 1}: توضیح`, 'textarea'));
  }
  const steps: Field[] = [f('stepsTitle', 'عنوان بخش'), f('stepsSub', 'توضیح زیر عنوان')];
  for (let i = 0; i < count('steps'); i++) {
    steps.push(f(`steps.${i}.0`, `مرحله ${i + 1}: عنوان`), f(`steps.${i}.1`, `مرحله ${i + 1}: توضیح`, 'textarea'));
  }
  const faq: Field[] = [f('faqTitle', 'عنوان بخش'), f('faqSub', 'توضیح زیر عنوان')];
  const meta: Field[] = [];
  metaPages.forEach((pg) => {
    meta.push(f(`meta.${pg.key}.title`, `${pg.label} — عنوان در گوگل`), f(`meta.${pg.key}.desc`, `${pg.label} — توضیح در گوگل`, 'textarea'));
  });
  const nav: Field[] = [f('nav.projects', 'منو: پروژه‌ها'), f('nav.services', 'منو: خدمات'), f('nav.about', 'منو: درباره ما'), f('nav.contact', 'منو: تماس'), f('explore', 'عنوان ستون لینک‌ها در فوتر'), f('contact', 'عنوان ستون تماس در فوتر')];
  const people: Field[] = [];
  team.forEach((p, i) => {
    const n = `${i + 1}: ${p.fa}`;
    people.push(f(`team.${i}.name`, `نفر ${n} — نام`));
    people.push(f(`team.${i}.phone`, `نفر ${i + 1} — شماره تلفن (مثل +989121234567)`, 'text', { shared: true, ltr: true }));
    if (p.telegram) people.push(f(`team.${i}.telegram`, `نفر ${i + 1} — آی‌دی تلگرام (بدون @)`, 'text', { shared: true, ltr: true }));
    if (p.whatsapp) people.push(f(`team.${i}.whatsapp`, `نفر ${i + 1} — شماره واتساپ (بدون +، مثل 989121234567)`, 'text', { shared: true, ltr: true }));
    if (p.bale) people.push(f(`team.${i}.bale`, `نفر ${i + 1} — آی‌دی بله (یا شماره با +)`, 'text', { shared: true, ltr: true }));
  });
  const list: Field[] = [];
  svcGroups.forEach((g, gi) => {
    list.push(f(`grp.${gi}.t`, `دسته ${gi + 1}: عنوان (${g.fa})`));
    g.items.forEach((it, ii) => list.push(f(`grp.${gi}.${ii}`, `${g.fa} — مورد ${ii + 1}`)));
  });
  return [
    { id: 'hero', title: 'بالای صفحه اول', fields: [
      f('heroTitle', 'عنوان اصلی', 'textarea'), f('heroText', 'متن زیر عنوان', 'textarea'),
      f('viewProjects', 'دکمه اول'), f('ourServices', 'دکمه دوم'),
    ] },
    { id: 'stats', title: 'اعداد و آمار', hint: 'عددهای کنار هم بالای صفحه اول، مثل سال سابقه یا تعداد پروژه.', fields: stats },
    { id: 'intro', title: 'معرفی و خدمات (صفحه اول)', fields: [
      f('introText', 'متن معرفی کوتاه', 'textarea'), f('readMore', 'لینک ادامه مطلب'),
      f('whatWeHelp', 'عنوان بخش خدمات'), f('seeAll', 'لینک مشاهده همه خدمات'),
    ] },
    { id: 'svclist', title: 'فهرست کامل خدمات', hint: 'همان فهرست دسته‌بندی‌شده در پایین صفحه‌ی خدمات.', fields: list },
    { id: 'abilities', title: 'کارت‌های «کنار شما هستیم»', hint: 'آیکن هر کارت ثابت است و فقط متن آن تغییر می‌کند.', fields: abilities },
    { id: 'steps', title: 'مراحل سفارش', fields: steps },
    { id: 'projects', title: 'بخش پروژه‌ها (صفحه اول)', fields: [
      f('featured', 'عنوان بخش پروژه‌های منتخب'), f('latest', 'عنوان بخش آخرین پروژه‌ها'),
      f('browseAll', 'لینک مشاهده همه پروژه‌ها'), f('noProjects', 'متن زمانی که پروژه‌ای نیست'),
    ] },
    { id: 'faq', title: 'سؤالات متداول', hint: 'سؤال‌ها را اضافه، حذف و جابه‌جا کنید.', list: 'faqlist', fields: faq },
    { id: 'about', title: 'درباره ما', fields: [
      f('aboutTitle', 'عنوان صفحه'), f('about1', 'پاراگراف اول', 'textarea'), f('about2', 'پاراگراف دوم', 'textarea'),
    ] },
    { id: 'contact', title: 'صفحه تماس', fields: [f('contactUs', 'عنوان صفحه'), f('contactIntro', 'متن معرفی', 'textarea')] },
    { id: 'team', title: 'اطلاعات تماس تیم', hint: 'شماره‌ها و آی‌دی‌ها در بالا، پایین سایت، صفحه تماس و صفحه‌ی خدمات عوض می‌شوند. شماره و آی‌دی برای هر دو زبان یکی است، فقط نام جداگانه است.', fields: people },
    { id: 'nav', title: 'منوی بالا و عنوان‌های فوتر', fields: nav },
    { id: 'meta', title: 'عنوان و توضیح صفحه‌ها در گوگل', hint: 'همان چیزی که در نتایج جستجوی گوگل و پیش‌نمایش لینک دیده می‌شود. توضیح حدود ۱۲۰ تا ۱۶۰ نویسه خوب است.', fields: meta },
    { id: 'footer', title: 'پایین سایت (فوتر)', fields: [f('tagline', 'جمله زیر نام سایت', 'textarea'), f('rights', 'متن حقوق')] },
  ];
}

export function defaultsFor(lang: 'fa' | 'en' | 'all'): Record<string, string> {
  const out: Record<string, string> = {};
  for (const g of buildGroups()) {
    for (const fl of g.fields) {
      let v: string | undefined;
      if (lang === 'all') {
        if (!fl.shared) continue;
        const m = /^team\.(\d+)\.(\w+)$/.exec(fl.key);
        v = m ? (team[+m[1]] as any)[m[2]] : undefined;
      } else if (fl.shared) {
        continue;
      } else if (fl.key.startsWith('team.')) {
        const m = /^team\.(\d+)\.name$/.exec(fl.key);
        v = m ? (team[+m[1]] as any)[lang] : undefined;
      } else if (fl.key.startsWith('meta.')) {
        const m = /^meta\.(\w+)\.(title|desc)$/.exec(fl.key);
        v = m ? (metaDefault(lang, m[1]) as any)[m[2]] : undefined;
      } else if (fl.key.startsWith('grp.')) {
        const [, gi, x] = fl.key.split('.');
        const g = (svcGroups as any)[+gi];
        v = g ? (x === 't' ? g[lang] : g.items[+x] && g.items[+x][lang]) : undefined;
      } else {
        v = getPath(ui[lang], fl.key);
      }
      if (typeof v === 'string') out[fl.key] = v;
    }
  }
  if (lang !== 'all') out.faqlist = JSON.stringify((ui[lang] as any).faq);
  return out;
}
