(function () {
  var scrollDirections = ['up', 'left', 'right', 'up', 'down'];

  var animatableSelector =
    'h1, h2, h3, h4, h5, h6, p, li, .logo, .bttns, .resource-list, section, article, header, footer, nav, aside, figure, blockquote, table, form, svg:not(#background), video, iframe, details, dl';

  function initScrollAnimations() {
    var container = document.querySelector('#content') || document.querySelector('main');
    if (!container) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var candidates = container.querySelectorAll(animatableSelector);
    var animated = Array.prototype.filter.call(candidates, function (element) {
      if (element.closest('#background')) return false;
      if (element.id === 'background') return false;

      var parent = element.parentElement;
      while (parent && parent !== container) {
        if (parent.matches(animatableSelector)) return false;
        parent = parent.parentElement;
      }

      return true;
    });

    animated.forEach(function (element, index) {
      element.setAttribute('data-scroll', scrollDirections[index % scrollDirections.length]);
    });

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      {
        threshold: 0.15,
        rootMargin: '0px 0px -8% 0px',
      }
    );

    animated.forEach(function (element) {
      observer.observe(element);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initScrollAnimations);
  } else {
    initScrollAnimations();
  }
})();
