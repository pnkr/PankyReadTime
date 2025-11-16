# Copilot Instructions for PankyReadTime

Project type: Joomla 5.x Content Plugin that displays an article's average reading time and a scroll progress bar on the site frontend.

## Architecture & Flow
- Entry manifest: `pankyreadingtime.xml` (metadata, Joomla version, namespaces, files, language packs, params).
- DI wiring: `services/provider.php` registers the plugin via Joomla's DI, returning an instance of `Panky\Plugin\Content\Pankyreadingtime\Extension\Pankyreadingtime` and injecting the application.
- Core plugin: `src/Extension/pankyreadingtime.php` implements `CMSPlugin` + `SubscriberInterface` and subscribes to:
  - `onContentPrepare` → computes reading time and sets `$article->readingTime`.
  - `onContentBeforeDisplay` → appends `$article->readingTime` and injects a fixed-top scroll progress bar with inline JS.
- Context guardrails: runs only on site app and content contexts `com_content.article` / `com_content.featured` (skips indexer).

## Key Files & Conventions
- Main class: `Pankyreadingtime` (note casing) in `src/Extension/pankyreadingtime.php`.
- Logging: uses `Joomla\CMS\Log\Log` with category `plg_content_pankyreadingtime` (INFO/DEBUG in handlers). To troubleshoot, enable logging in Joomla and watch this category.
- Localization: language keys live under `language/*/plg_content_pankyreadingtime*.ini`; plugin sets `$this->autoloadLanguage = true`.
- HTML output: badge markup uses Bootstrap classes (e.g., `badge bg-dark mb-2`, `fa fa-clock`); progress bar is an inline `<div id="reading-progress">` plus a small `scroll` handler.
- Word count logic: strips HTML, decodes entities, splits on spaces; default speed currently 230 wpm; minutes/seconds labels assembled from i18n fragments to handle singular/plural nuances.

## Parameters & Current Behavior
- Manifest defines params like `reading_speed`, `show_seconds`, and several `show_in*` toggles.
- Current implementation does NOT read `$this->params` and always uses 230 wpm, always shows seconds and progress bar. If you add param-driven behavior, prefer:
  - `$speed = (int) $this->params->get('reading_speed', 230);`
  - `$showSeconds = (bool) $this->params->get('show_seconds', 1);`
  - Respect context toggles before adding output in both handlers.

## Event Handling Pattern
- Access event args via `array_values($event->getArguments())` → `[$context, $article, $params, $page]`.
- Prefer result-safe append: if `$event instanceof ResultAwareInterface`, use `$event->addResult($html)`; otherwise get/set the `result` argument.
- Write computed HTML into the `$article` in `onContentPrepare`, then read/append it in `onContentBeforeDisplay`.

## Dev & Test Workflow
- No build step or Composer deps. Structure follows Joomla 5 plugin layout (`services` + `src` + `language`).
- Local testing: zip the repo contents (keeping folder structure) and install via Joomla Extensions → Install; or copy into `plugins/content/pankyreadingtime` and use Discover/Install.
- Versioning: keep `@version` in PHP headers and `<version>` in `pankyreadingtime.xml` in sync when releasing.

## Gotchas & Tips
- Ensure the plugin only runs on `site` (`$this->getApplication()->isClient('site')`).
- Keep HTML/CSS minimal; rely on Bootstrap utilities shipped with the active Joomla template where possible.
- When changing language keys, update both `en-GB` and `el-GR` files and the manifest description keys.
- Respect contexts to avoid affecting indexer or non-article views; keep guards aligned in both handlers.
