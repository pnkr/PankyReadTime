/**
 * @copyright   (C) 2024 Panayiotis Kiriakopoulos
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
(() => {
  'use strict';

  const fillFinishTimes = () => {
    document.querySelectorAll('.pankyreadingtime-finish[data-seconds]').forEach((el) => {
      const time = el.querySelector('time');

      if (!time) {
        return;
      }

      const finish = new Date(Date.now() + (parseInt(el.dataset.seconds, 10) || 0) * 1000);
      const options = { hour: '2-digit', minute: '2-digit' };
      let text;

      try {
        text = finish.toLocaleTimeString(document.documentElement.lang || undefined, options);
      } catch (e) {
        text = finish.toLocaleTimeString(undefined, options);
      }

      time.dateTime = finish.toISOString();
      time.textContent = text;
      el.hidden = false;
    });
  };

  const initProgress = () => {
    const bar = document.getElementById('reading-progress');

    if (!bar) {
      return;
    }

    const percent = document.getElementById('reading-progress-percent');
    let ticking = false;

    const update = () => {
      ticking = false;

      const max = document.documentElement.scrollHeight - window.innerHeight;
      const value = max > 0 ? Math.min(100, Math.max(0, (window.scrollY / max) * 100)) : 0;
      const rounded = String(Math.round(value));

      bar.style.width = `${value}%`;
      bar.setAttribute('aria-valuenow', rounded);

      if (percent) {
        percent.textContent = `${rounded}%`;
      }
    };

    const requestUpdate = () => {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(update);
      }
    };

    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate, { passive: true });
    window.addEventListener('load', requestUpdate);
    update();
  };

  const init = () => {
    fillFinishTimes();
    initProgress();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
