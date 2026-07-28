const ANIMATE_SELECTOR =
  'main h1, main h2, main h3, main h4, main p, main .logo, main .bttns, main .resource-list, main li, main a.bttn, main img, main section, main article, main footer, main [data-animate]';

const DIRECTIONS = ['slide-up', 'slide-left', 'slide-right'] as const;

function getDirection(element: Element, index: number): (typeof DIRECTIONS)[number] {
  const custom = element.getAttribute('data-animate');
  if (custom === 'slide-up' || custom === 'slide-left' || custom === 'slide-right') {
    return custom;
  }
  return DIRECTIONS[index % DIRECTIONS.length];
}

function initScrollAnimations() {
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const elements = Array.from(document.querySelectorAll<HTMLElement>(ANIMATE_SELECTOR)).filter(
    (element) => !element.closest('#background')
  );

  if (elements.length === 0) return;

  if (prefersReducedMotion) {
    elements.forEach((element) => element.classList.add('is-visible'));
    return;
  }

  elements.forEach((element, index) => {
    element.classList.add('animate-on-scroll', getDirection(element, index));
    element.style.setProperty('--animate-delay', `${Math.min(index * 0.1, 0.6)}s`);
  });

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    },
    {
      threshold: 0.15,
      rootMargin: '0px 0px -5% 0px',
    }
  );

  elements.forEach((element) => observer.observe(element));
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initScrollAnimations);
} else {
  initScrollAnimations();
}
