const svg = (inner: string) => `<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">${inner}</svg>`;
const PHONE = 'M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z';
export const ICONS: Record<string, string> = {
  phone: svg(`<path fill="#fff" d="${PHONE}"/>`),
  telegram: svg('<path fill="#fff" d="M20.8 4L3 11l5.4 1.8 2 6.3 3-3.9 5 3.6z"/><path fill="none" stroke="#2AABEE" stroke-width="1.4" stroke-linejoin="round" d="M8.4 12.8L17.6 7l-7.2 7.3z"/>'),
  whatsapp: svg(`<path fill="none" stroke="#fff" stroke-width="1.9" stroke-linejoin="round" d="M12 3.2a8.8 8.8 0 00-7.6 13.2L3.2 20.8l4.5-1.2A8.8 8.8 0 1012 3.2z"/><path fill="#fff" transform="translate(7 7) scale(.42)" d="${PHONE}"/>`),
  bale: svg('<path fill="#fff" d="M12 2.5l7.5 2.8v6.1c0 4.6-3.1 8.8-7.5 10.1-4.4-1.3-7.5-5.5-7.5-10.1V5.3L12 2.5z"/><path fill="none" stroke="#2DC08E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" d="M8.5 12.2l2.4 2.4 4.6-4.8"/>'),
};
export const COLORS: Record<string, string> = { phone: '#1b73b8', telegram: '#2AABEE', whatsapp: '#25D366', bale: '#2DC08E' };
