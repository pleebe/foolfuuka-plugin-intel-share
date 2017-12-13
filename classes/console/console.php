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
            ->setDescription('Runs the banned media hash sharing daemon')
            ->addOption(
                'rebuild',
                null,
                InputOption::VALUE_OPTIONAL,
                _i('Rebuild banned_md5 table')
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if($input->getOption('rebuild')) {
            $this->md5_rebuild($output);
        } else {
            $this->intel_sharing($output);
        }
    }

    public function md5_rebuild($output)
    {
        $abc = "abcdefghijklmnopqrstuvwxyz";
        $c = 0;
        $i = $this->dc->qb()
            ->select($abc[0].'.media_hash as hash');
        foreach ($this->radix_coll->getAll() as $radix) {
            if($c==0) {
                $i->from($radix->getTable('_images'), $abc[$c]);
                $i->where($abc[$c] . '.banned=1');
            } else if($c==1) {
                $i->innerJoin($abc[$c-1], $radix->getTable('_images'), $abc[$c], $abc[$c-1].'.media_hash = '.$abc[$c].'.media_hash');
                $i->andWhere($abc[$c] . '.banned=1');
            }
            $c++;
        }
        $res = $i->execute()
            ->fetchAll();
        /* debug
         * $output->writeln(print_r($i->getSQL()));*/
        foreach($res as $e) {
            try {
                $re = $this->dc->qb()
                    ->select('count(md5) as count')
                    ->from($this->dc->p('banned_md5'))
                    ->where('md5 = :md5')
                    ->setParameter(':md5', $e['hash'])
                    ->execute()
                    ->fetch();
                if (!$re['count']) {
                    $this->dc->getConnection()
                        ->insert($this->dc->p('banned_md5'), ['md5' => $e['hash']]);
                    $output->write('+');
                } else {
                    $output->write('.');
                }
            } catch (\Exception $e) {$output->write('-');}
        }
        $output->writeln("\nDone rebuilding.");
    }

    public function getdir($radix, $media, $thumbnail)
    {
        return $this->preferences->get('foolfuuka.boards.directory') . '/' . $radix->shortname . '/'
        . ($thumbnail ? 'thumb' : 'image') . '/' . substr($media, 0, 4) . '/' . substr($media, 4, 2) . '/' . $media;
    }

    public function delete($radix, $md5)
    {
        $data = $this->dc->qb()
            ->select('media, preview_op, preview_reply')
            ->from($radix->getTable('_images'), 'ri')
            ->where('media_hash = :md5')
            ->setParameter(':md5', $md5)
            ->execute()
            ->fetch();
        if ($data['media'] !== null && $data['media'] !== '' && file_exists($this->getdir($radix, $data['media'], false)))
            @unlink($this->getdir($radix, $data['media'], false));
        if ($data['preview_op'] !== null && $data['preview_op'] !== '' && file_exists($this->getdir($radix, $data['preview_op'], true)))
            @unlink($this->getdir($radix, $data['preview_op'], true));
        if ($data['preview_reply'] !== null && $data['preview_reply'] !== '' && file_exists($this->getdir($radix, $data['preview_reply'], true)))
            @unlink($this->getdir($radix, $data['preview_reply'], true));
    }

    public function intel_sharing($output)
    {
        if (!$this->preferences->get('foolfuuka.plugins.intel.get.enabled')) {
            $output->writeln("You need to configure this plugin first in the admin panel.");
            return;
        }

        $uris = preg_split('/\r\n|\r|\n/', $this->preferences->get('foolfuuka.plugins.intel.get.urls'));

        foreach ($uris as $base) {
            $page = 0;
            $run = 1;
            while ($run == 1) {
                $page++;
                $url = $base . '/_/api/chan/intel/?page=' . $page;
                $output->writeln("\n* Synchronizing with $url");

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_USERAGENT, 'ffuuka intel share plugin/1.0');
                $result = curl_exec($ch);
                $result_r = json_decode($result, true);

                if (!array_key_exists('banned_hashes', $result_r)) {
                    $run = 0;
                    continue;
                }

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
                            try {
                                $this->delete($radix, $hash);

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
                            } catch (\Exception $e) {
                                $output->write('-');
                            }
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
        $output->writeln("\nFinished");
    }
}