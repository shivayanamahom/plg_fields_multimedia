/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
((window, document) => {
  'use strict';

  const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Слайдер на CSS scroll-snap ---------- */
  const initSlider = (gallery) => {
    const viewport = gallery.querySelector('[data-mm-viewport]');

    if (!viewport) {
      return;
    }

    const prev = gallery.querySelector('[data-mm-prev]');
    const next = gallery.querySelector('[data-mm-next]');
    const step = () => {
      const cell = viewport.querySelector('.mm-cell');
      const gap = parseFloat(getComputedStyle(viewport.firstElementChild).columnGap) || 0;

      return cell ? cell.getBoundingClientRect().width + gap : viewport.clientWidth;
    };
    const behavior = () => (reduceMotion() ? 'auto' : 'smooth');
    const update = () => {
      const max = viewport.scrollWidth - viewport.clientWidth - 2;

      if (prev) {
        prev.disabled = viewport.scrollLeft <= 2;
      }

      if (next) {
        next.disabled = viewport.scrollLeft >= max;
      }
    };
    const go = (direction, wrap = false) => {
      const max = viewport.scrollWidth - viewport.clientWidth;

      // Автопрокрутка после последнего кадра возвращается к первому; кнопки на краях отключены.
      if (wrap && direction > 0 && viewport.scrollLeft >= max - 2) {
        viewport.scrollTo({ left: 0, behavior: behavior() });
      } else {
        viewport.scrollTo({ left: viewport.scrollLeft + direction * step(), behavior: behavior() });
      }
    };

    prev?.addEventListener('click', () => go(-1));
    next?.addEventListener('click', () => go(1));
    viewport.addEventListener('scroll', () => window.requestAnimationFrame(update), { passive: true });
    viewport.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        go(event.key === 'ArrowRight' ? 1 : -1);
      }
    });
    window.addEventListener('resize', update);
    update();

    // Автопрокрутка останавливается при наведении, фокусе и при системной настройке «меньше движения».
    const seconds = Number(gallery.dataset.mmAutoplay || 0);

    if (seconds > 0 && !reduceMotion()) {
      let paused = false;

      ['mouseenter', 'focusin'].forEach((name) => gallery.addEventListener(name, () => { paused = true; }));
      ['mouseleave', 'focusout'].forEach((name) => gallery.addEventListener(name, () => { paused = false; }));
      window.setInterval(() => {
        if (!paused && !document.hidden) {
          go(1, true);
        }
      }, seconds * 1000);
    }
  };

  /* ---------- Встроенный просмотр (если WT Fancybox не подключён) ---------- */
  const initLightbox = () => {
    const dialog = document.querySelector('[data-mm-lightbox]');

    if (!dialog || dialog.mmReady) {
      return;
    }

    dialog.mmReady = true;

    const img = dialog.querySelector('[data-mm-lb-img]');
    const caption = dialog.querySelector('[data-mm-lb-caption]');
    let links = [];
    let index = 0;

    const show = (position) => {
      index = (position + links.length) % links.length;
      const link = links[index];

      img.src = link.href;
      img.alt = link.querySelector('img')?.alt || '';
      caption.textContent = link.dataset.mmCaption || '';
      dialog.querySelectorAll('[data-mm-lb-prev], [data-mm-lb-next]').forEach((button) => {
        button.hidden = links.length < 2;
      });
    };

    document.addEventListener('click', (event) => {
      const link = event.target.closest('[data-mm-open]');

      if (!link || event.metaKey || event.ctrlKey || event.shiftKey) {
        return;
      }

      event.preventDefault();
      links = [...document.querySelectorAll('[data-mm-open]')].filter((item) => item.dataset.mmGroup === link.dataset.mmGroup);
      show(links.indexOf(link));
      dialog.showModal();
    });

    dialog.querySelector('[data-mm-lb-close]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-mm-lb-prev]').addEventListener('click', () => show(index - 1));
    dialog.querySelector('[data-mm-lb-next]').addEventListener('click', () => show(index + 1));
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        dialog.close();
      }
    });
    dialog.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowLeft') {
        show(index - 1);
      } else if (event.key === 'ArrowRight') {
        show(index + 1);
      }
    });
    dialog.addEventListener('close', () => { img.removeAttribute('src'); });
  };

  /* ---------- WT Fancybox: библиотека загружает плагин, мы только привязываем галереи ---------- */
  const initFancybox = () => {
    if (!window.Fancybox || document.documentElement.mmFancybox) {
      return;
    }

    document.documentElement.mmFancybox = true;
    window.Fancybox.bind('.mm-gallery [data-fancybox]');
  };

  const init = () => {
    document.querySelectorAll('[data-mm-gallery]').forEach(initSlider);

    if (document.querySelector('[data-mm-engine="builtin"]')) {
      initLightbox();
    }

    if (document.querySelector('[data-mm-engine="fancybox"]')) {
      initFancybox();
    }
  };

  // Скрипты с defer выполняются до DOMContentLoaded: ждём его, чтобы Fancybox уже был загружен.
  let started = false;
  const start = () => {
    if (!started) {
      started = true;
      init();
    }
  };

  if (document.readyState === 'complete') {
    start();
  } else {
    document.addEventListener('DOMContentLoaded', start);
    window.addEventListener('load', start);
  }
})(window, document);
