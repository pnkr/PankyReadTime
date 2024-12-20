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
            'onContentBeforeDisplay' => 'beforeDisplayContent',
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

        if (($context !== "com_content.article" && $context !== "com_content.featured") || ($context === 'com_finder.indexer')) {
            return;
        }

        $text = $article->text;
        $word = count(explode(" ", strip_tags(html_entity_decode($text, ENT_QUOTES))));
        $m = floor($word / 230);
        $s = floor($word % 230 / (230 / 60));
        $estimateTime = $m . Text::_('PLG_CONTENT_PANKYREADINGTIME_MINUTE_LABEL') . ($m == 1 ? Text::_('PLG_CONTENT_PANKYREADINGTIME_MINUTEEXT_LABEL') : Text::_('PLG_CONTENT_PANKYREADINGTIME_MINUTESEXT_LABEL')) . ', ' . $s . Text::_('PLG_CONTENT_PANKYREADINGTIME_SEC_LABEL') . ($s == 1 ? Text::_('PLG_CONTENT_PANKYREADINGTIME_SECEXT_LABEL') : Text::_('PLG_CONTENT_PANKYREADINGTIME_SECSEXT_LABEL'));
        $article->readingTime = "<div class='badge bg-dark mb-2'><span class='fa fa-clock'></span> " . Text::_('PLG_CONTENT_PANKYREADINGTIME_AVERAGETIME_LABEL') . ": <span id='time'>" . $estimateTime . "</span></div>";
    }

    public function beforeDisplayContent(Event $event)
    {
        Log::add('onContentBeforeDisplay event triggered', Log::DEBUG, 'plg_content_pankyreadingtime');

        [$context, $article, $params, $page] = array_values($event->getArguments());

        if (isset($article->readingTime)) {
            if ($event instanceof ResultAwareInterface) {
                $event->addResult($article->readingTime);
            } else {
                $result = $event->getArgument('result') ?? [];
                $result[] = $article->readingTime;
                $event->setArgument('result', $result);
            }
        }
    }
}