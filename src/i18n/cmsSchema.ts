import { ui } from './ui';

// Describes every site text the owner can edit from the panel (/panel/).
// Defaults always come from ui.ts, so the panel can show the original text and "reset to default" works.
// A template element becomes editable by carrying data-cms="<key>" with one of the keys below.

export type Field = { key: string; label: string; type: 'text' | 'textarea' };
export type Group = { id: string; title: string; hint?: string; fields: Field[] };

const f = (key: string, label: string, type: 'text' | 'textarea' = 'text'): Field => ({ key, label, type });

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
  for (let i = 0; i < count('faq'); i++) {
    faq.push(f(`faq.${i}.0`, `سؤال ${i + 1}`), f(`faq.${i}.1`, `پاسخ ${i + 1}`, 'textarea'));
  }
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
    { id: 'abilities', title: 'کارت‌های «کنار شما هستیم»', hint: 'آیکن هر کارت ثابت است و فقط متن آن تغییر می‌کند.', fields: abilities },
    { id: 'steps', title: 'مراحل سفارش', fields: steps },
    { id: 'projects', title: 'بخش پروژه‌ها (صفحه اول)', fields: [
      f('featured', 'عنوان بخش پروژه‌های منتخب'), f('latest', 'عنوان بخش آخرین پروژه‌ها'),
      f('browseAll', 'لینک مشاهده همه پروژه‌ها'), f('noProjects', 'متن زمانی که پروژه‌ای نیست'),
    ] },
    { id: 'faq', title: 'سؤالات متداول', fields: faq },
    { id: 'about', title: 'درباره ما', fields: [
      f('aboutTitle', 'عنوان صفحه'), f('about1', 'پاراگراف اول', 'textarea'), f('about2', 'پاراگراف دوم', 'textarea'),
    ] },
    { id: 'contact', title: 'صفحه تماس', fields: [f('contactUs', 'عنوان صفحه'), f('contactIntro', 'متن معرفی', 'textarea')] },
    { id: 'footer', title: 'پایین سایت (فوتر)', fields: [f('tagline', 'جمله زیر نام سایت', 'textarea'), f('rights', 'متن حقوق')] },
  ];
}

export function defaultsFor(lang: 'fa' | 'en'): Record<string, string> {
  const out: Record<string, string> = {};
  for (const g of buildGroups()) {
    for (const fl of g.fields) {
      const v = getPath(ui[lang], fl.key);
      if (v !== undefined) out[fl.key] = v;
    }
  }
  return out;
}
