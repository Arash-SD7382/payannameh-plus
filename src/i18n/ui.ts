export type Lang = 'fa' | 'en';
export const href = (lang: Lang, p: string) => (lang === 'en' ? (p === '/' ? '/en/' : '/en' + p) : p);
export const bothLangs = () => [
  { params: { lang: undefined }, props: { lang: 'fa' } },
  { params: { lang: 'en' }, props: { lang: 'en' } },
];
export const fmtDate = (d: Date, lang: Lang, long = false) =>
  d.toLocaleDateString(lang === 'fa' ? 'fa-IR-u-ca-persian' : 'en-US', { year: 'numeric', month: long ? 'long' : 'short', day: 'numeric', timeZone: 'UTC' });
export const readMins = (body: string) => Math.max(1, Math.round(body.split(/\s+/).length / 200));
export const ui = {
  fa: {
    dir: 'rtl', stepsTitle: "مراحل ثبت سفارش", stepsSub: "از درخواست تا تحویل، در شش مرحله ساده", steps: [["ثبت درخواست", "درخواست خود را از طریق تلگرام، واتساپ، بله یا تلفن برای ما بفرستید."], ["بررسی پروژه", "تیم متخصص موضوع، رشته و نیازهای شما را بررسی می‌کند."], ["اعلام هزینه و زمان", "هزینه و زمان تحویل پیش از شروع کار به‌طور شفاف اعلام می‌شود."], ["شروع پروژه", "پس از توافق، کار را آغاز می‌کنیم و در طول مسیر با شما در ارتباط هستیم."], ["تحویل پروژه", "کار نهایی در زمان توافق‌شده تحویل داده می‌شود."], ["اصلاحات", "در صورت نیاز، اصلاحات اعلام‌شده را انجام می‌دهیم."]], foreignBadge: 'English', playVideo: 'پخش ویدیو', videoFallback: 'اگر ویدیو پخش نشد، لینک اصلی آن را باز کنید', brand: 'پایان‌نامه پلاس', tagline: 'پیشرفت در مسیر برتری علمی', switch: 'English',
    metaDesc: 'پایان‌نامه پلاس: پشتیبانی در نگارش پایان‌نامه، مقاله، پژوهش، داده و برنامه‌نویسی، همراه با مستندسازی مرحله‌به‌مرحله پروژه‌ها.',
    nav: { projects: 'پروژه‌ها', services: 'خدمات', about: 'درباره ما', contact: 'تماس' },
    heroTitle: 'کارهای علمی و فنی، مرحله‌به‌مرحله مستندسازی‌شده.',
    heroText: 'پایان‌نامه، مقاله، پژوهش، داده و پروژه‌های برنامه‌نویسی، با دقت علمی. ببینید هر پروژه چگونه برنامه‌ریزی، اجرا و تحویل شد.',
    viewProjects: 'مشاهده پروژه‌ها', ourServices: 'خدمات ما', whatWeHelp: 'در چه زمینه‌هایی کمک می‌کنیم', seeAll: 'مشاهده همه خدمات',
    featured: 'پروژه‌های منتخب', latest: 'آخرین پروژه‌ها', browseAll: 'مشاهده همه پروژه‌ها', noProjects: 'هنوز پروژه‌ای منتشر نشده است.',
    searchPh: 'جستجو در عنوان، دسته‌بندی یا موضوع', searchLabel: 'جستجوی پروژه‌ها', allCats: 'همه دسته‌ها', catLabel: 'فیلتر دسته‌بندی', sortLabel: 'مرتب‌سازی', newest: 'جدیدترین', oldest: 'قدیمی‌ترین',
    noMatch: 'پروژه‌ای با این عبارت پیدا نشد. عبارت یا دسته دیگری را امتحان کنید.',
    minRead: 'دقیقه مطالعه', onThisPage: 'فهرست مطالب', related: 'پروژه‌های مرتبط', by: 'نویسنده', home: 'خانه',
    prev: '→ قبلی', next: 'بعدی ←', projectsDesc: 'پروژه‌های انجام‌شده را همراه با ویدیو، مراحل و نتیجه ببینید.',
    servicesIntro: 'از پیش‌نویس اول تا فرمت‌بندی نهایی، در چهار حوزه از کارهای علمی و فنی پشتیبانی می‌کنیم.', servicesDesc: 'خدمات نگارش، پژوهش، ویرایش، داده و برنامه‌نویسی.',
    contactUs: 'تماس با ما', contactIntro: 'از طریق تلفن یا تلگرام با ما در تماس باشید. در اولین فرصت پاسخ می‌دهیم.', contactDesc: 'تماس با تیم پایان‌نامه پلاس از طریق تلفن یا تلگرام.',
    phone: 'تلفن', telegram: 'تلگرام', msgTelegram: 'پیام در تلگرام', explore: 'دسترسی سریع', contact: 'تماس', rights: 'تمامی حقوق محفوظ است.',
    aboutTitle: 'درباره پایان‌نامه پلاس', aboutDesc: 'پایان‌نامه پلاس یک تیم نویسندگی علمی و پشتیبانی پژوهشی است.',
    about1: 'پایان‌نامه پلاس یک تیم نویسندگی علمی و پشتیبانی پژوهشی است. ما در نگارش پایان‌نامه، مقاله، پروپوزال پژوهشی، تحلیل داده و پروژه‌های برنامه‌نویسی به دانشجویان و پژوهشگران کمک می‌کنیم؛ با متنی روشن، قالب‌بندی درست و تحویل به‌موقع.',
    about2: 'هر پروژه‌ای که به پایان می‌رسد، همراه با مراحل و نتیجه‌اش در همین سایت منتشر می‌شود تا روند کار را ببینید.',
    notFound: 'صفحه مورد نظر پیدا نشد.', notFoundText: 'ممکن است صفحه جابه‌جا شده باشد یا آدرس اشتباه باشد.', backHome: 'بازگشت به صفحه اصلی',
    cat: { Writing: 'نگارش', Research: 'پژوهش', 'Editing & Quality': 'ویرایش و کنترل کیفیت', 'Data & Technical': 'داده و فنی' },
  },
  en: {
    dir: 'ltr', stepsTitle: "How to order", stepsSub: "From request to delivery in six simple steps", steps: [["Submit your request", "Send us your request by Telegram, WhatsApp, Bale or phone."], ["Project review", "Our specialist team reviews your topic, field and requirements."], ["Cost and timeline", "The cost and delivery time are shared clearly before work begins."], ["Project starts", "Once we agree, we begin and stay in touch with you along the way."], ["Delivery", "The final work is delivered on the agreed date."], ["Revisions", "If needed, we make the revisions you request."]], foreignBadge: 'فارسی', playVideo: 'Play video', videoFallback: 'If the video does not play, open the original link', brand: 'Payannameh Plus', tagline: 'Advancing Academic Excellence', switch: 'فارسی',
    metaDesc: 'Payannameh Plus: thesis, article, research, data and programming support, with step-by-step documented projects.',
    nav: { projects: 'Projects', services: 'Services', about: 'About', contact: 'Contact' },
    heroTitle: 'Academic and technical work, documented step by step.',
    heroText: 'Thesis, articles, research, data and programming projects, written with academic care. Read how each project was planned, built and delivered.',
    viewProjects: 'View projects', ourServices: 'Our services', whatWeHelp: 'What we help with', seeAll: 'See all services',
    featured: 'Featured projects', latest: 'Latest projects', browseAll: 'Browse all projects', noProjects: 'No projects yet. New ones will appear here.',
    searchPh: 'Search by title, category or topic', searchLabel: 'Search projects', allCats: 'All categories', catLabel: 'Filter by category', sortLabel: 'Sort', newest: 'Newest first', oldest: 'Oldest first',
    noMatch: 'No projects match your search. Try a different word or category.',
    minRead: 'min read', onThisPage: 'On this page', related: 'Related projects', by: 'By', home: 'Home',
    prev: '← Previous', next: 'Next →', projectsDesc: 'Browse completed projects with video, steps and results.',
    servicesIntro: 'From the first draft to the final formatting, we support academic and technical work in four areas.', servicesDesc: 'Writing, research, editing, data and programming services.',
    contactUs: 'Contact us', contactIntro: 'Reach out by phone or Telegram. We reply as soon as we can.', contactDesc: 'Contact the Payannameh Plus team by phone or Telegram.',
    phone: 'Phone', telegram: 'Telegram', msgTelegram: 'Message on Telegram', explore: 'Explore', contact: 'Contact', rights: 'All rights reserved.',
    aboutTitle: 'About Payannameh Plus', aboutDesc: 'Payannameh Plus is an academic writing and research support team.',
    about1: 'Payannameh Plus is an academic writing and research support team. We help students and researchers with theses, articles, research proposals, data analysis and programming projects, written clearly, formatted properly and delivered on time.',
    about2: 'Every finished project is documented here with its steps and results, so you can see how the work is done.',
    notFound: 'Page not found', notFoundText: 'This page could not be found. It may have moved or the link may be wrong.', backHome: 'Back to home',
    cat: { Writing: 'Writing', Research: 'Research', 'Editing & Quality': 'Editing & Quality', 'Data & Technical': 'Data & Technical' },
  },
};

export const num = (n: number, lang: Lang) => n.toLocaleString(lang === 'fa' ? 'fa-IR' : 'en-US');
