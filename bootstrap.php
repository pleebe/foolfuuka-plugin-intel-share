<?php

use Doctrine\DBAL\Schema\Schema;
use Foolz\FoolFrame\Model\Autoloader;
use Foolz\FoolFrame\Model\Context;
use Foolz\FoolFrame\Model\Preferences;
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
                $autoloader->addClassMap([
                    'Foolz\FoolFuuka\Controller\Api\IntelShare' => __DIR__ . '/classes/controller/api/chan.php',
                    'Foolz\FoolFrame\Controller\Admin\Plugins\IntelShare' => __DIR__ . '/classes/controller/admin.php',
                    'Foolz\FoolFuuka\Plugins\IntelShare\Console\Console' => __DIR__ . '/classes/console/console.php'
                ]);

                Event::forge('Foolz\FoolFrame\Model\Context::handleWeb#obj.routing')
                    ->setCall(function ($result) use ($context) {
                        if ($context->getService('auth')->hasAccess('maccess.admin')) {
                            Event::forge('Foolz\FoolFrame\Controller\Admin::before#var.sidebar')
                                ->setCall(function ($result) {
                                    $sidebar = $result->getParam('sidebar');
                                    $sidebar[]['plugins'] = [
                                        "content" => ["intel/manage" => ["level" => "admin", "name" => _i("Intelligence Sharing"), "icon" => 'icon-file']]
                                    ];
                                    $result->setParam('sidebar', $sidebar);
                                });

                            $context->getRouteCollection()->add(
                                'foolfuuka.plugin.intel.admin', new Route(
                                    '/admin/plugins/intel/{_suffix}',
                                    [
                                        '_suffix' => 'manage',
                                        '_controller' => '\Foolz\FoolFrame\Controller\Admin\Plugins\IntelShare::manage'
                                    ],
                                    [
                                        '_suffix' => '.*'
                                    ]
                                )
                            );
                        }

                        /** @var Preferences $preferences */
                        $preferences = $context->getService('preferences');
                        if($preferences->get('foolfuuka.plugins.intel.share.enabled')) {
                            $context->getRouteCollection()->add(
                                'foolfuuka.plugin.intel.chan', new Route(
                                    '/_/api/chan/intel/',
                                    [
                                        '_controller' => 'Foolz\FoolFuuka\Controller\Api\IntelShare::intel'
                                    ]
                               )
                            );
                        }
                    });
                Event::forge('Foolz\FoolFrame\Model\Context::handleConsole#obj.app')
                    ->setCall(function ($result) use ($context) {
                        $result->getParam('application')
                            ->add(new \Foolz\FoolFuuka\Plugins\IntelShare\Console\Console($context));
                    });
            });
    }
}


(new HHVM_intel_share())->run();
