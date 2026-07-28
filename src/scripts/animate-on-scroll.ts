const DIRECTIONS = ['from-bottom', 'from-left', 'from-right', 'from-top'] as const;

const AUTO_SELECTORS = [
  'main h1',
  'main h2',
  'main h3',
  'main p',
  'main img',
  'main li',
  'main a.bttn',
  'main .logo',
  'footer',
].join(', ');

function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function ensureAnimateClass(el: Element, index: number): void {
  if (!(el instanceof HTMLElement)) return;
  if (el.classList.contains('animate-on-scroll')) return;

  el.classList.add('animate-on-scroll');

  const hasDirection = DIRECTIONS.some((dir) => el.classList.contains(dir));
  if (!hasDirection) {
    el.classList.add(DIRECTIONS[index % DIRECTIONS.length]);
  }

  if (!el.style.getPropertyValue('--animate-delay')) {
    el.style.setProperty('--animate-delay', `${(index % 6) * 80}ms`);
  }
}

function isNestedAnimateTarget(el: Element, collection: Set<Element>): boolean {
  let parent = el.parentElement;
  while (parent) {
    if (collection.has(parent) || parent.classList.contains('animate-on-scroll')) {
      return true;
    }
    parent = parent.parentElement;
  }
  return false;
}

export function initAnimateOnScroll(): void {
  if (prefersReducedMotion()) return;

  const tagged = Array.from(document.querySelectorAll('.animate-on-scroll'));
  const auto = Array.from(document.querySelectorAll(AUTO_SELECTORS));
  const candidates = Array.from(new Set([...tagged, ...auto]));
  const candidateSet = new Set(candidates);
  const elements = candidates.filter((el) => !isNestedAnimateTarget(el, candidateSet));

  elements.forEach((el, index) => ensureAnimateClass(el, index));

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    },
    {
      threshold: 0.15,
      rootMargin: '0px 0px -48px 0px',
    }
  );

  elements.forEach((el) => observer.observe(el));
}
