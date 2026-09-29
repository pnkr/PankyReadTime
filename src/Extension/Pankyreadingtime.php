<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.pankyreadingtime
 *
 * @copyright   (C) 2024 Panayiotis Kiriakopoulos
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Panky\Plugin\Content\Pankyreadingtime\Extension;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Content\AfterDisplayEvent;
use Joomla\CMS\Event\Content\AfterTitleEvent;
use Joomla\CMS\Event\Content\BeforeDisplayEvent;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class Pankyreadingtime extends CMSPlugin implements SubscriberInterface
{
    private const DEFAULT_SPEED = 230;

    private const DEFAULT_COLOR = '#007bff';

    private const DEFAULT_HEIGHT = 5;

    private const ASSET_NAME = 'plg_content_pankyreadingtime.progress';

    protected $autoloadLanguage = true;

    private bool $assetsLoaded = false;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare'       => 'onContentPrepare',
            'onContentAfterTitle'    => 'onContentAfterTitle',
            'onContentBeforeDisplay' => 'onContentBeforeDisplay',
            'onContentAfterDisplay'  => 'onContentAfterDisplay',
        ];
    }

    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        if (!$this->shouldRender($event->getContext())) {
            return;
        }

        $article = $event->getItem();

        if (!\is_object($article)) {
            return;
        }

        $speed = (int) $this->params->get('reading_speed', self::DEFAULT_SPEED);

        if ($speed <= 0) {
            $speed = self::DEFAULT_SPEED;
        }

        $totalSeconds = (int) floor($this->countWords((string) ($article->text ?? '')) * 60 / $speed);

        // Kept on the item (not the plugin) so template overrides can keep using $item->readingTime
        $article->readingTime = $this->renderBadge(intdiv($totalSeconds, 60), $totalSeconds % 60);
    }

    public function onContentAfterTitle(AfterTitleEvent $event): void
    {
        $this->addBadge($event, 'afterTitle');
    }

    public function onContentBeforeDisplay(BeforeDisplayEvent $event): void
    {
        $this->addBadge($event, 'before');

        if (!$this->shouldRender($event->getContext()) || !$this->params->get('show_progress_bar', 1)) {
            return;
        }

        $this->loadAssets();

        $bottom  = $this->params->get('progress_bar_position', 'top') === 'bottom';
        $barHtml = '<div id="reading-progress" class="pankyreadingtime-progress' . ($bottom ? ' pankyreadingtime-progress--bottom' : '') . '"'
            . ' role="progressbar" aria-label="' . $this->escape(Text::_('PLG_CONTENT_PANKYREADINGTIME_PROGRESS_BAR_ARIA_LABEL')) . '"'
            . ' aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>';

        if ($this->params->get('progress_bar_show_percent', 0)) {
            $barHtml .= '<div id="reading-progress-percent" class="pankyreadingtime-percent' . ($bottom ? ' pankyreadingtime-percent--bottom' : '') . '"'
                . ' aria-hidden="true">0%</div>';
        }

        $event->addResult($barHtml);
    }

    public function onContentAfterDisplay(AfterDisplayEvent $event): void
    {
        $this->addBadge($event, 'after');
    }

    /**
     * Adds the prepared badge to the event result when the configured position matches.
     *
     * @param   AfterTitleEvent|BeforeDisplayEvent|AfterDisplayEvent  $event     The content event
     * @param   string                                                $position  The position this event represents
     */
    private function addBadge(AfterTitleEvent|BeforeDisplayEvent|AfterDisplayEvent $event, string $position): void
    {
        if (!$this->shouldRender($event->getContext())) {
            return;
        }

        $article = $event->getItem();

        if ($this->params->get('badge_position', 'before') === $position && isset($article->readingTime)) {
            $event->addResult($article->readingTime);
        }
    }

    private function shouldRender(string $context): bool
    {
        return $context === 'com_content.article' && $this->getApplication()->isClient('site');
    }

    private function countWords(string $html): int
    {
        // Drop code blocks, then pad tags with a space so "<p>a</p><p>b</p>" doesn't count as one word
        $html  = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $plain = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $words = preg_split('/[\s\p{Z}]+/u', $plain, -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? 0 : \count($words);
    }

    private function renderBadge(int $minutes, int $seconds): string
    {
        $showSeconds = (bool) $this->params->get('show_seconds', 1);

        if (!$showSeconds) {
            if ($seconds > 0) {
                $minutes++;
            }

            $seconds = 0;
        }

        if ($this->params->get('badge_format', 'verbose') === 'compact') {
            $estimate = $minutes . Text::_('PLG_CONTENT_PANKYREADINGTIME_MINUTE_SHORT');

            if ($showSeconds) {
                $estimate .= ' ' . $seconds . Text::_('PLG_CONTENT_PANKYREADINGTIME_SECOND_SHORT');
            }
        } else {
            $estimate = Text::plural('PLG_CONTENT_PANKYREADINGTIME_N_MINUTES', $minutes);

            if ($showSeconds) {
                $estimate .= ', ' . Text::plural('PLG_CONTENT_PANKYREADINGTIME_N_SECONDS', $seconds);
            }
        }

        $html = '<div class="pankyreadingtime badge bg-dark mb-2">'
            . '<span class="icon-clock" aria-hidden="true"></span> '
            . $this->escape(Text::_('PLG_CONTENT_PANKYREADINGTIME_AVERAGETIME_LABEL')) . ': '
            . '<span class="pankyreadingtime-estimate">' . $this->escape($estimate) . '</span>';

        if ($this->params->get('show_finish_by', 0)) {
            // The clock time is filled in by the browser, so it uses the visitor's timezone and survives page caching
            $this->loadAssets();

            $html .= '<span class="pankyreadingtime-finish" data-seconds="' . ($minutes * 60 + $seconds) . '" hidden> &bull; '
                . $this->escape(Text::_('PLG_CONTENT_PANKYREADINGTIME_FINISH_BY')) . ' <time></time></span>';
        }

        return $html . '</div>';
    }

    private function loadAssets(): void
    {
        if ($this->assetsLoaded) {
            return;
        }

        $document = $this->getApplication()->getDocument();

        if (!$document instanceof HtmlDocument) {
            return;
        }

        $this->assetsLoaded = true;

        $color = (string) $this->params->get('progress_bar_color', self::DEFAULT_COLOR);

        if (!preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color)) {
            $color = self::DEFAULT_COLOR;
        }

        $height = max(1, min(50, (int) $this->params->get('progress_bar_height', self::DEFAULT_HEIGHT)));

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('plg_content_pankyreadingtime');
        $wa->useStyle(self::ASSET_NAME)
            ->useScript(self::ASSET_NAME)
            ->addInlineStyle(':root{--pankyreadingtime-color:' . $color . ';--pankyreadingtime-height:' . $height . 'px}');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
