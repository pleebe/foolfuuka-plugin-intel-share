<?php

namespace Foolz\FoolFuuka\Plugins\IntelShare\Console;

use Foolz\FoolFrame\Model\Context;
use Foolz\FoolFrame\Model\DoctrineConnection;
use Foolz\FoolFuuka\Model\RadixCollection;
use Foolz\FoolFrame\Model\Preferences;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Console extends Command
{
    /**
     * @var \Foolz\FoolFrame\Model\Context
     */
    protected $context;

    /**
     * @var DoctrineConnection
     */
    protected $dc;

    /**
     * @var RadixCollection
     */
    protected $radix_coll;

    /**
     * @var Preferences
     */
    protected $preferences;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->dc = $context->getService('doctrine');
        $this->radix_coll = $context->getService('foolfuuka.radix_collection');
        $this->preferences = $context->getService('preferences');
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('intel_share:run')
            ->setDescription('Runs the banned media hash sharing daemon');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->intel_sharing($output);
    }

    public function intel_sharing($output)
    {
        if(!$this->preferences->get('foolfuuka.plugins.intel.get.enabled')) {
            $output->writeln("You need to configure this plugin first in the admin panel.");
            return;
        }

        while(true) {
            $uris = preg_split('/\r\n|\r|\n/', $this->preferences->get('foolfuuka.plugins.intel.get.urls'));

            foreach ($uris as $base) {
                $page = 0;
                $run = 1;
                while ($run == 1) {
                    $page++;
                    $url = $base . '/_/api/chan/intel/?page=' . $page;
                    $output->writeln("\n* Synchronizing with $base");

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                    $result = curl_exec($ch);
                    $result_r = json_decode($result, true);

                    foreach ($result_r['banned_hashes'] as $hash) {
                        $re = $this->dc->qb()
                            ->select('count(md5) as count')
                            ->from($this->dc->p('banned_md5'))
                            ->where('md5 = :md5')
                            ->setParameter(':md5', $hash)
                            ->execute()
                            ->fetch();

                        if (!$re['count']) {
                            $this->dc->getConnection()
                                ->insert($this->dc->p('banned_md5'), ['md5' => $hash]);

                            foreach ($this->radix_coll->getAll() as $radix) {
                                $output->write('radix'.$radix->shortname);
                                try {
                                    $i = $this->dc->qb()
                                        ->select('COUNT(*) as count')
                                        ->from($radix->getTable('_images'), 'ri')
                                        ->where('media_hash = :md5')
                                        ->setParameter(':md5', $hash)
                                        ->execute()
                                        ->fetch();

                                    if (!$i['count']) {
                                        $this->dc->getConnection()
                                            ->insert($radix->getTable('_images'), ['media_hash' => $hash, 'banned' => 1]);
                                    } else {
                                        $this->dc->qb()
                                            ->update($radix->getTable('_images'))
                                            ->set('banned', 1)
                                            ->where('media_hash = :media_hash')
                                            ->setParameter(':media_hash', $hash)
                                            ->execute();
                                    }

                                    $output->write('+');
                                } catch (\Exception $e) {$output->write('-');}
                            }
                        } else {
                            $output->write('.');
                        }
                    }
                    if ($result_r['total_count'] / 100 <= $page) {
                        $run = 0;
                    }
                }
            }
            $sleep = $this->preferences->get('foolfuuka.plugins.intel.get.sleep');
            $output->writeln("\n* Sleeping for $sleep minutes");
            sleep($sleep*60);
        }
    }
}