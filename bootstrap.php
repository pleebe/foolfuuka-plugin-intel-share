<?php

use Doctrine\DBAL\Schema\Schema;
use Foolz\FoolFrame\Model\Autoloader;
use Foolz\FoolFrame\Model\Context;
use Foolz\FoolFrame\Model\DoctrineConnection;
use Foolz\FoolFrame\Model\Plugins;
use Foolz\FoolFrame\Model\Uri;
use Foolz\FoolFuuka\Model\RadixCollection;
use Foolz\Plugin\Event;
use Symfony\Component\Routing\Route;

class HHVM_intel_share
{
    public function run()
    {
        Event::forge('Foolz\Plugin\Plugin::execute#foolz/foolfuuka-plugin-intel-share')
            ->setCall(function ($plugin) {
                /** @var Context $context */
                $context = $plugin->getParam('context');

                /** @var Autoloader $autoloader */
                $autoloader = $context->getService('autoloader');
                $autoloader->addClass('Foolz\FoolFuuka\Controller\Api\IntelShare', __DIR__ . '/classes/controller/api/chan.php');

                Event::forge('Foolz\FoolFrame\Model\Context::handleWeb#obj.routing')
                    ->setCall(function ($result) use ($context) {
                        $routes = $result->getObject();
                        $routes->getRouteCollection()->add(
                            'foolfuuka.plugin.intel.chan', new Route(
                                '/_/api/chan/intel/',
                                [
                                    '_controller' => 'Foolz\FoolFuuka\Controller\Api\IntelShare::intel'
                                ]
                            )
                        );
                    });
            });
    }
}


(new HHVM_intel_share())->run();
