<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.pankyreadingtime
 *
 * @copyright   (C) 2023 Panagiotis Kiriakopoulos. <https://github.com/pnkr>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;


use Joomla\CMS\Plugin\CMSPlugin;

/**
 * Email cloack plugin class.
 *
 * @since  1.5
 */
class PlgContentPankyreadingtime extends CMSPlugin
{
		/**
		 * Load the language file on instantiation
		 *
		 * @var    boolean
		 * @since  3.1
		 */
		protected $autoloadLanguage = true;	
	/**
	 * The Application object
	 *
	 * @var    JApplicationSite
	 * @since  3.9.0
	 */
	protected $app;

	/**
	 * This plugins adds an information tag at the top of each article, informing the visitor about the time he must spend to fully read it.
	 *
	 * @param   string   $context  The context of the content being passed to the plugin.
	 * @param   mixed    &$row     An object with a "text" property or the string to be cloaked.
	 * @param   mixed    &$params  Additional parameters.
	 * @param   integer  $page     Optional page number. Unused. Defaults to zero.
	 *
	 * @return  void
	 */
	public function onContentPrepare($context, &$row, &$params, $page = 0)
	{


		if ($this->app->isClient('api'))
		{
			return true;
		}

		// Don't run this plugin when the content is being indexed
		if ($context === 'com_finder.indexer')
		{
			return;
		}

		if (is_object($row) && $context === 'com_content.article')
		{


			$word = count(explode(" ",strip_tags(html_entity_decode($row->text,ENT_QUOTES))));
			$m = floor($word / 230);
			$s = floor($word % 230 / (230 / 60));
			$estimateTime = $m . JText::_('PLG_CONTENT_PANKYREADINGTIME_MINUTE_LABEL') . ($m == 1 ? JText::_('PLG_CONTENT_PANKYREADINGTIME_MINUTEEXT_LABEL') : JText::_('PLG_CONTENT_PANKYREADINGTIME_MINUTESEXT_LABEL')) . ', ' . $s  .JText::_('PLG_CONTENT_PANKYREADINGTIME_SEC_LABEL') . ($s == 1 ? JText::_('PLG_CONTENT_PANKYREADINGTIME_SECEXT_LABEL') :  JText::_('PLG_CONTENT_PANKYREADINGTIME_SECSEXT_LABEL'));			

			$row->text = "<div class='badge bg-dark mb-2'><span class='fa fa-clock'></span> " . JText::_('PLG_CONTENT_PANKYREADINGTIME_AVERAGETIME_LABEL') . ": <span id='time'>" . $estimateTime . "</span></div>" . $row->text;	


			return;
		}



	}



}
