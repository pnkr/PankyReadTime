<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.pankyreadingtime
 *
 * @copyright   (C) 2024 Panayiotis Kiriakopoulos
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Panky\Plugin\Content\Pankyreadingtime\Extension\Pankyreadingtime;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                // Passing the dispatcher is required on Joomla 5.1-5.3 and deprecated (until 7.0) from 5.4 on
                $plugin = new Pankyreadingtime(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('content', 'pankyreadingtime')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
