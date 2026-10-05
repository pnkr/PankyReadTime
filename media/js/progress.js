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

  // Short utterances avoid browsers (notably Chrome) silently stopping long speech after ~15 seconds
  const MAX_CHUNK = 200;
  const SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE', 'IFRAME', 'svg']);
  const SENTENCE = /[^.!?;;…\n]+[.!?;;…]*/g;
  const WORD = /[^\s.,!?;:;…"«»“”()[\]]+/y;
  const HIGHLIGHT_NAME = 'pankyreadingtime-word';

  /**
   * Collects the visible text of `root` together with the text node each part came from, so a character
   * offset in the spoken text can be turned back into a DOM range. Whitespace is replaced one-for-one so
   * offsets stay aligned; "\n" is added only between blocks and at <br>, and never belongs to a text node.
   */
  const readText = (root) => {
    const segments = [];
    const blocks = new Map();
    let text = '';
    let lastBlock = null;

    const blockOf = (el) => {
      if (blocks.has(el)) {
        return blocks.get(el);
      }

      let block = root;

      if (el && el !== root) {
        const display = window.getComputedStyle(el).display;
        block = display.startsWith('inline') || display === 'contents' ? blockOf(el.parentElement) : el;
      }

      blocks.set(el, block);

      return block;
    };

    const breakLine = () => {
      if (text && !text.endsWith('\n')) {
        text += '\n';
      }
    };

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
      acceptNode: (node) => {
        if (node.nodeType !== Node.ELEMENT_NODE || node.tagName === 'BR') {
          return NodeFilter.FILTER_ACCEPT;
        }

        // Elements without boxes (display: none) are not read, just like innerText
        return SKIP_TAGS.has(node.tagName) || !node.getClientRects().length ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
      },
    });

    while (walker.nextNode()) {
      const node = walker.currentNode;

      if (node.nodeType === Node.ELEMENT_NODE) {
        if (node.tagName === 'BR') {
          breakLine();
        }

        continue;
      }

      const block = blockOf(node.parentElement);

      if (block !== lastBlock) {
        breakLine();
        lastBlock = block;
      }

      segments.push({ node, start: text.length });
      text += node.data.replace(/\s/g, ' ');
    }

    return { text, segments };
  };

  /**
   * Splits the text into sentence-aligned {start, end} ranges of at most MAX_CHUNK characters,
   * never joining sentences across a paragraph break.
   */
  const chunkRanges = (text) => {
    const ranges = [];
    let buffer = null;
    let match;

    const flush = () => {
      if (buffer) {
        ranges.push(buffer);
        buffer = null;
      }
    };

    SENTENCE.lastIndex = 0;

    while ((match = SENTENCE.exec(text))) {
      let start = match.index;
      const end = start + match[0].trimEnd().length;

      while (start < end && text[start] === ' ') {
        start += 1;
      }

      if (buffer && text.slice(buffer.end, start).includes('\n')) {
        flush();
      }

      if (start === end) {
        continue;
      }

      if (buffer && end - buffer.start > MAX_CHUNK) {
        flush();
      }

      while (end - start > MAX_CHUNK) {
        let cut = text.lastIndexOf(' ', start + MAX_CHUNK);

        if (cut <= start) {
          cut = start + MAX_CHUNK;
        }

        ranges.push({ start, end: cut });
        start = cut;

        while (start < end && text[start] === ' ') {
          start += 1;
        }
      }

      if (buffer) {
        buffer.end = end;
      } else {
        buffer = { start, end };
      }
    }

    flush();

    return ranges;
  };

  const initListen = () => {
    const controls = document.querySelector('.pankyreadingtime-listen');
    const body = document.querySelector('.pankyreadingtime-body');

    if (!controls || !body || !('speechSynthesis' in window) || typeof window.SpeechSynthesisUtterance !== 'function') {
      return;
    }

    const synth = window.speechSynthesis;
    const toggle = controls.querySelector('.pankyreadingtime-listen-toggle');
    const stop = controls.querySelector('.pankyreadingtime-listen-stop');
    const label = toggle.querySelector('.pankyreadingtime-listen-label');
    const icon = toggle.querySelector('[aria-hidden="true"]');
    const lang = document.documentElement.lang || '';
    // Browsers without the CSS Custom Highlight API still read aloud, just without highlighting
    const canHighlight = controls.dataset.highlight === '1'
      && typeof window.Highlight === 'function' && window.CSS && 'highlights' in window.CSS;
    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let source = { text: '', segments: [] };
    let chunks = [];
    let index = 0;
    let state = 'idle';
    // Bumped on every pause/stop so callbacks from cancelled utterances are ignored
    let session = 0;

    const pickVoice = () => {
      if (!lang) {
        return null;
      }

      const wanted = lang.toLowerCase();
      const voices = synth.getVoices();
      const normalise = (voice) => voice.lang.toLowerCase().replace('_', '-');

      return voices.find((voice) => normalise(voice) === wanted)
        || voices.find((voice) => normalise(voice).split('-')[0] === wanted.split('-')[0])
        || null;
    };

    // Finds the text node and the offset inside it for a character offset in source.text
    const locate = (offset) => {
      const { segments } = source;
      let lo = 0;
      let hi = segments.length - 1;

      while (lo < hi) {
        const mid = (lo + hi + 1) >> 1;

        if (segments[mid].start <= offset) {
          lo = mid;
        } else {
          hi = mid - 1;
        }
      }

      const segment = segments[lo];

      return [segment.node, Math.max(0, Math.min(offset - segment.start, segment.node.length))];
    };

    const clearHighlight = () => {
      if (canHighlight) {
        window.CSS.highlights.delete(HIGHLIGHT_NAME);
      }
    };

    const highlightWord = (start, end) => {
      if (!source.segments.length || end <= start) {
        return;
      }

      const [startNode, startOffset] = locate(start);
      const [endNode, endOffset] = locate(end - 1);
      const range = document.createRange();

      try {
        range.setStart(startNode, startOffset);
        range.setEnd(endNode, Math.min(endOffset + 1, endNode.length));
      } catch (e) {
        // The article changed after reading started; skip this word rather than stop speaking
        return;
      }

      window.CSS.highlights.set(HIGHLIGHT_NAME, new window.Highlight(range));

      // Keep the word being read on screen
      const rect = range.getBoundingClientRect();

      if (rect.height && (rect.top < 0 || rect.bottom > window.innerHeight)) {
        window.scrollBy({ top: rect.top - window.innerHeight / 3, behavior: reduceMotion ? 'auto' : 'smooth' });
      }
    };

    const setState = (next) => {
      state = next;

      const key = { playing: 'labelPause', paused: 'labelResume' }[next] || 'labelPlay';

      label.textContent = toggle.dataset[key];
      icon.className = next === 'playing' ? 'icon-pause' : 'icon-play';
      stop.hidden = next === 'idle';

      if (next === 'idle') {
        clearHighlight();
      }
    };

    const speakNext = () => {
      if (index >= chunks.length) {
        index = 0;
        setState('idle');
        return;
      }

      const token = session;
      const chunk = chunks[index];
      const utterance = new SpeechSynthesisUtterance(source.text.slice(chunk.start, chunk.end));
      const voice = pickVoice();

      if (lang) {
        utterance.lang = lang;
      }

      if (voice) {
        utterance.voice = voice;
      }

      if (canHighlight) {
        // Fired per word by most local voices; some online voices (e.g. Chrome's Google voices) never fire it
        utterance.onboundary = (event) => {
          if (token !== session || (event.name && event.name !== 'word')) {
            return;
          }

          const start = chunk.start + event.charIndex;
          let length = event.charLength || 0;

          if (!length) {
            WORD.lastIndex = start;
            const match = WORD.exec(source.text);
            length = match ? match[0].length : 0;
          }

          highlightWord(start, Math.min(start + length, chunk.end));
        };
      }

      utterance.onend = () => {
        if (token === session) {
          index += 1;
          speakNext();
        }
      };

      utterance.onerror = (event) => {
        if (token === session && event.error !== 'interrupted' && event.error !== 'canceled') {
          index = 0;
          setState('idle');
        }
      };

      synth.speak(utterance);
    };

    // Pause is a cancel that remembers the position: synth.pause() is unreliable on mobile browsers
    const halt = () => {
      session += 1;
      synth.cancel();
    };

    toggle.addEventListener('click', () => {
      if (state === 'playing') {
        halt();
        setState('paused');
        return;
      }

      if (state === 'idle') {
        source = readText(body);
        chunks = chunkRanges(source.text);
        index = 0;
      }

      halt();
      setState('playing');
      speakNext();
    });

    stop.addEventListener('click', () => {
      halt();
      index = 0;
      setState('idle');
    });

    window.addEventListener('pagehide', halt);
    controls.hidden = false;
  };

  const init = () => {
    fillFinishTimes();
    initProgress();
    initListen();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
