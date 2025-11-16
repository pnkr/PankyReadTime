<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.pankyreadingtime
 * @version     2.1.0
 * @copyright   (C) 2024 Panayiotis Kiriakopoulos
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

 namespace Panky\Plugin\Content\Pankyreadingtime\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Event\Result\ResultAwareInterface;

class Pankyreadingtime extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        Log::add('getSubscribedEvents called', Log::INFO, 'plg_content_pankyreadingtime');
        return [
            'onContentPrepare' => 'addReadingTime',
            'onContentAfterTitle' => 'afterTitleContent',
            'onContentBeforeDisplay' => 'beforeDisplayContent',
            'onContentAfterDisplay' => 'afterDisplayContent',
        ];
    }

    protected $autoloadLanguage = true;

    protected $app;

    public function addReadingTime(Event $event)
    {
        Log::add('onContentPrepare event triggered', Log::DEBUG, 'plg_content_pankyreadingtime');

        if (!$this->getApplication()->isClient('site')) {
            return;
        }

        [$context, $article, $params, $page] = array_values($event->getArguments());

        if ($context !== "com_content.article" || $context === 'com_finder.indexer') {
            return;
        }

        $speed = (int) $this->params->get('reading_speed', 230);
        if ($speed <= 0) {
            $speed = 230;
        }
        $showSeconds = (bool) $this->params->get('show_seconds', 1);
        $badgeFormat = (string) $this->params->get('badge_format', 'verbose');
        $showFinishBy = (bool) $this->params->get('show_finish_by', 0);

        $text = $article->text ?? '';
        $plain = strip_tags(html_entity_decode($text, ENT_QUOTES));
        $words = preg_split('/\s+/', trim($plain));
        $wordCount = $plain === '' ? 0 : count($words);

        $totalMinutes = $wordCount / max(1, $speed);
        $m = (int) floor($totalMinutes);
        $s = (int) floor(($totalMinutes - $m) * 60);

        if (!$showSeconds) {
            if ($s > 0) {
                $m += 1;
            }
            $s = 0;
        }

        if ($badgeFormat === 'compact') {
            $estimateTime = $m . Text::_('PLG_CONTENT_PANKYREADINGTIME_MINUTE_SHORT');
            if ($showSeconds) {
                $estimateTime .= ' ' . $s . Text::_('PLG_CONTENT_PANKYREADINGTIME_SECOND_SHORT');
            }
        } else {
            $estimateTime = $m . Text::_('PLG_CONTENT_PANKYREADINGTIME_MINUTE_LABEL')
                . ($m == 1 ? Text::_('PLG_CONTENT_PANKYREADINGTIME_MINUTEEXT_LABEL') : Text::_('PLG_CONTENT_PANKYREADINGTIME_MINUTESEXT_LABEL'));
            if ($showSeconds) {
                $estimateTime .= ', ' . $s . Text::_('PLG_CONTENT_PANKYREADINGTIME_SEC_LABEL')
                    . ($s == 1 ? Text::_('PLG_CONTENT_PANKYREADINGTIME_SECEXT_LABEL') : Text::_('PLG_CONTENT_PANKYREADINGTIME_SECSEXT_LABEL'));
            }
        }

        $finishStr = '';
        if ($showFinishBy) {
            try {
                $totalSeconds = ($m * 60) + $s;
                $dt = new \DateTime();
                if ($totalSeconds > 0) {
                    $dt->add(new \DateInterval('PT' . $totalSeconds . 'S'));
                }
                $finishStr = ' • ' . Text::_('PLG_CONTENT_PANKYREADINGTIME_FINISH_BY') . ' ' . $dt->format('H:i');
            } catch (\Throwable $e) {
                // ignore finish time on error
            }
        }

        $article->readingTime = "<div class='badge bg-dark mb-2'><span class='fa fa-clock'></span> "
            . Text::_('PLG_CONTENT_PANKYREADINGTIME_AVERAGETIME_LABEL') . ": <span id='time'>" . $estimateTime . "</span>" . $finishStr . "</div>";
    }

    private function shouldRenderForContext(string $context): bool
    {
        if (!$this->getApplication()->isClient('site')) {
            return false;
        }
        if ($context === 'com_finder.indexer') {
            return false;
        }
        return $context === 'com_content.article';
    }

    private function appendResult(Event $event, string $html): void
    {
        if ($event instanceof ResultAwareInterface) {
            $event->addResult($html);
        } else {
            $result = $event->getArgument('result') ?? [];
            $result[] = $html;
            $event->setArgument('result', $result);
        }
    }

    public function afterTitleContent(Event $event)
    {
        [$context, $article, $params, $page] = array_values($event->getArguments());
        if (!$this->shouldRenderForContext($context)) {
            return;
        }

        $position = (string) $this->params->get('badge_position', 'before');
        if ($position === 'afterTitle' && isset($article->readingTime)) {
            $this->appendResult($event, $article->readingTime);
        }
    }

    public function beforeDisplayContent(Event $event)
    {
        Log::add('onContentBeforeDisplay event triggered', Log::DEBUG, 'plg_content_pankyreadingtime');

        [$context, $article, $params, $page] = array_values($event->getArguments());
        if (!$this->shouldRenderForContext($context)) {
            return;
        }

        $position = (string) $this->params->get('badge_position', 'before');
        if ($position === 'before' && isset($article->readingTime)) {
            $this->appendResult($event, $article->readingTime);
        }

        $showProgress = (bool) $this->params->get('show_progress_bar', 1);
        if ($showProgress) {
            $color = (string) $this->params->get('progress_bar_color', '#007bff');
            $height = (int) $this->params->get('progress_bar_height', 5);
            $barPos = (string) $this->params->get('progress_bar_position', 'top');
            $showPct = (bool) $this->params->get('progress_bar_show_percent', 0);

            $stylePos = $barPos === 'bottom' ? 'bottom: 0;' : 'top: 0;';
            $percentBoxPos = $barPos === 'bottom' ? 'bottom: ' . max(0, $height + 2) . 'px;' : 'top: ' . max(0, $height + 2) . 'px;';

            $progressBar = "<div id='reading-progress' role='progressbar' aria-valuemin='0' aria-valuemax='100' aria-valuenow='0' style='position: fixed; $stylePos left: 0; height: {$height}px; background: $color; width: 0; z-index: 9999;'></div>";
            if ($showPct) {
                $progressBar .= "<div id='reading-progress-percent' style='position: fixed; right: 8px; $percentBoxPos font-size: 12px; color: #6c757d; background: rgba(255,255,255,0.8); padding: 2px 6px; border-radius: 10px; z-index: 9999;'>0%</div>";
            }

            $progressBar .= "<script>(function(){var rp=document.getElementById('reading-progress');if(!rp)return;var pctEl=" . ($showPct ? "document.getElementById('reading-progress-percent')" : "null") . ";function upd(){var st=window.scrollY||window.pageYOffset;var dh=document.body.scrollHeight-window.innerHeight;var p=dh>0?Math.min(100,Math.max(0,(st/dh)*100)):0;rp.style.width=p+'%';rp.setAttribute('aria-valuenow',p.toFixed(0));" . ($showPct ? "if(pctEl){pctEl.textContent=p.toFixed(0)+'%';}" : "") . "}document.addEventListener('scroll',upd,{passive:true});window.addEventListener('load',upd);upd();})();</script>";

            $this->appendResult($event, $progressBar);
        }
    }

    public function afterDisplayContent(Event $event)
    {
        [$context, $article, $params, $page] = array_values($event->getArguments());
        if (!$this->shouldRenderForContext($context)) {
            return;
        }
        $position = (string) $this->params->get('badge_position', 'before');
        if ($position === 'after' && isset($article->readingTime)) {
            $this->appendResult($event, $article->readingTime);
        }
    }
}