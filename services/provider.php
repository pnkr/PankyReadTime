<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  plg_content_pankyreadingtime Version 2.0
 *
 * @copyright   (C) 2024 Panagiotis Kiriakopoulos. <https://github.com/pnkr>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

 
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Panky\Plugin\Content\Pankyreadingtime\Extension\Pankyreadingtime;

    return new class() implements ServiceProviderInterface
    {
        public function register(Container $container)
        {
            $container->set(
                PluginInterface::class,
                function (Container $container) {
    
                    $config = (array) PluginHelper::getPlugin('content', 'pankyreadingtime');
                    $subject = $container->get(DispatcherInterface::class);
                    $app = Factory::getApplication();
                    
                    $plugin = new Pankyreadingtime($subject, $config);
                    $plugin->setApplication($app);
    
                    return $plugin;
                }
            );
        }
    };
