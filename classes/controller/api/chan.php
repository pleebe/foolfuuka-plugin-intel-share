<?php

namespace Foolz\FoolFuuka\Controller\Api;

use Foolz\FoolFrame\Model\Context;
use Foolz\FoolFrame\Model\DoctrineConnection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class IntelShare extends \Foolz\FoolFuuka\Controller\Api\Chan
{
    /**
     * @var DoctrineConnection
     */
    protected $dc;

    /**
     * @var context
     */
    protected $context;

    public function before()
    {
        $this->context = $this->getContext();
        $this->dc = $this->getContext()->getService('doctrine');

        parent::before();
    }

    public static function isValidPostNumber($str)
    {
        return ctype_digit((string) $str);
    }

    public function get_intel($page = 1)
    {
        $this->response = new JsonResponse();
        if ($this->request->get('page')) {
            $page = $this->request->get('page');
            if (!$this->isValidPostNumber($page)) {
                return $this->response->setData(['error' => _i('The value for "page" is invalid.')])->setStatusCode(422);
            }
        }

        $count = $this->dc->qb()
            ->select('count(md5) as c')
            ->from($this->dc->p('banned_md5'))
            ->execute()
            ->fetchAll();

        $per_page = 100;
        $result = $this->dc->qb()
            ->select('md5')
            ->from($this->dc->p('banned_md5'))
            ->setFirstResult(($page * $per_page) - $per_page)
            ->execute()
            ->fetchAll();

        $results = [];

        foreach($result as $r) {
            foreach($r as $key => $value) {
                array_push($results, $value);
            }
        }

        $this->response->setData(['banned_hashes' => $results,'total_count' => $count[0]['c']]);

        return $this->response;
    }
}
