(() => {
  const root = document.documentElement;
  const themeToggle = document.getElementById('themeToggle');
  const toggle = document.getElementById('menuToggle');
  const nav = document.getElementById('mainNav');

  const applyTheme = (selectedTheme, persist = false) => {
    root.setAttribute('data-theme', selectedTheme);
    if (themeToggle) {
      const lightIcon = themeToggle.querySelector('[data-theme-icon="light"]');
      const darkIcon = themeToggle.querySelector('[data-theme-icon="dark"]');
      if (lightIcon) lightIcon.toggleAttribute('hidden', selectedTheme !== 'light');
      if (darkIcon) darkIcon.toggleAttribute('hidden', selectedTheme !== 'dark');
      themeToggle.setAttribute('aria-label', selectedTheme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
      themeToggle.setAttribute('aria-pressed', String(selectedTheme === 'dark'));
      themeToggle.setAttribute('title', selectedTheme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    }

    if (persist) {
      try {
        localStorage.setItem('aitam-theme', selectedTheme);
      } catch (error) {
        console.warn('Theme preference could not be saved.', error);
      }
    }
  };

  const initialTheme = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  applyTheme(initialTheme);

  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const nextTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(nextTheme, true);
    });
  }

  const closeMenu = () => {
    if (!toggle || !nav) return;
    nav.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Open navigation');
  };

  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const isOpen = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(isOpen));
      toggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
    });

    nav.addEventListener('click', event => {
      if (event.target instanceof Element && event.target.closest('a')) closeMenu();
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && nav.classList.contains('is-open')) {
        closeMenu();
        toggle.focus();
      }
    });

    document.addEventListener('click', event => {
      if (nav.classList.contains('is-open') &&
          event.target instanceof Node &&
          !nav.contains(event.target) &&
          !toggle.contains(event.target)) closeMenu();
    });
  }

  document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', event => {
      const selector = link.getAttribute('href');
      if (!selector || selector.length < 2) return;
      const target = document.querySelector(selector);
      if (target) {
        event.preventDefault();
        target.scrollIntoView({
          behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
          block: 'start'
        });
      }
    });
  });

  document.querySelectorAll('[data-counter]').forEach(input => {
    const counterId = input.getAttribute('data-counter');
    const counter = counterId ? document.getElementById(counterId) : null;
    if (!counter || !(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement)) return;
    const updateCounter = () => {
      counter.textContent = `${input.value.length} / ${input.maxLength}`;
    };
    input.addEventListener('input', updateCounter);
    updateCounter();
  });
})();