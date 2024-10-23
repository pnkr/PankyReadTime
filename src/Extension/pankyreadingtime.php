<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.pankyreadingtime
 * @version     2.0.0
 * @copyright   (C) 2024 Panayiotis Kiriakopoulos
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

 namespace Panky\Plugin\Content\Pankyreadingtime\Extension;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects



use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Log\Log;
use Joomla\Event\Event;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Event\SubscriberInterface;


/**
 * Class Pankyreadingtime
 *
 * @since  5.1.0
 */
class Pankyreadingtime extends CMSPlugin implements SubscriberInterface
{

	/**
     * Returns an array of CMS events this plugin will listen to and the respective handlers.
     *
     * @return array
     * @since  5.1.0
     */
    public static function getSubscribedEvents(): array
    {
        Log::add('getSubscribedEvents called', Log::INFO, 'plg_content_pankyreadingtime');
        return [
            'onContentPrepare' => 'addReadingTime',
        ];
    }

    /**
     * Load the language file on instantiation
     *
     * @var boolean
     * @since  5.1
     */
    protected $autoloadLanguage = true;

    /**
     * The Application object
     *
     * @var JApplicationSite
     * @since  5.1
     */
    protected $app;

    /**
     * This plugins adds an information tag at the top of each article,
     * informing the visitor about the time he must spend to fully read it.
     *
     * @param string $context The context of the content being passed to the plugin.
     * @param mixed  &$row    An object with a "text" property or the string to be cloaked.
     * @param mixed  &$params Additional parameters.
     * @param integer $page   Optional page number. Unused. Defaults to zero.
     * @return void
     */
    public function addReadingTime(Event $event)
    {
        Log::add('onContentPrepare event triggered', Log::DEBUG, 'plg_content_pankyreadingtime');

        // The line below restricts the functionality to the site (ie not on api)
        // You may not want this, so you need to consider this in your own plugins
        if (!$this->getApplication()->isClient('site')) {
            return;
        }
        // In Joomla 5 a concrete ContentPrepareEvent is passed
        [$context, $article, $params, $page] = array_values($event->getArguments());

        if (($context !== "com_content.article" && $context !== "com_content.featured") || ($context === 'com_finder.indexer')) {
			return;
		}
        
        $text = $article->text; // text of the article
        //$config = Factory::getApplication()->getConfig()->toArray();  // config params as an array
            // (we can't do a foreach over the config params as a Registry because they're protected)
        

            $word = count(explode(" ", strip_tags(html_entity_decode($text, ENT_QUOTES))));
            $m = floor($word / 230);
            $s = floor($word % 230 / (230 / 60));
            $estimateTime = $m . Text::_('PLG_CONTENT_PANKYREADINGTIME_MINUTE_LABEL') . ($m == 1 ? Text::_('PLG_CONTENT_PANKYREADINGTIME_MINUTEEXT_LABEL') : Text::_('PLG_CONTENT_PANKYREADINGTIME_MINUTESEXT_LABEL')) . ', ' . $s . Text::_('PLG_CONTENT_PANKYREADINGTIME_SEC_LABEL') . ($s == 1 ? Text::_('PLG_CONTENT_PANKYREADINGTIME_SECEXT_LABEL') : Text::_('PLG_CONTENT_PANKYREADINGTIME_SECSEXT_LABEL'));
            $text = "<div class='badge bg-dark mb-2'><span class='fa fa-clock'></span> " . Text::_('PLG_CONTENT_PANKYREADINGTIME_AVERAGETIME_LABEL') . ": <span id='time'>" . $estimateTime . "</span></div>" . $text;
            
			// now update the article text with the processed text
			$article->text = $text;

			return;
        
    }
}
