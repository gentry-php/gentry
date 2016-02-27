<?php

namespace Gentry;

use JonnyW\PhantomJs\Client;

class Browser
{
    private static $sessionname = 'PHPSESSID';
    private static $sessionid = null;

    public function __construct()
    {
        if (!isset(self::$sessionid)) {
            self::$sessionid = getenv('GENTRY_CLIENT');
        }
    }

    public function get($url)
    {
        list($client, $request, $response) = $this->initializeRequest();
        $request->setMethod('GET');
        $request->setUrl($url);
        $client->send($request, $response);
        Cache\Pool::getInstance()->__wakeup();
        return $response;
    }

    public function post($url, array $data)
    {
        list($client, $request, $response) = $this->initializeRequest();
        $request->setMethod('POST');
        $request->setUrl($url);
        $request->setRequestData($data);
        $client->send($request, $response);
        Cache\Pool::getInstance()->__wakeup();
        return $response;
    }

    /**
     * Override session defaults for this run.
     *
     * @param string $name Optional session name. Defaults to `PHPSESSID`.
     * @param string $id Optional session id. Defaults to `GENTRY_CLIENT`.
     */
    public static function setSession($name = null, $id = null)
    {
        if (isset($name)) {
            self::$sessionname = $name;
        }
        if (isset($id)) {
            self::$sessionid = $id;
        }
    }

    private function initializeRequest()
    {
        $client = Client::getInstance();
        $client->getEngine()->setPath(getenv("GENTRY_VENDOR").'/bin/phantomjs');
        $cookies = sys_get_temp_dir().'/'.getenv("GENTRY_CLIENT");
        $client->getEngine()->addOption("--cookies-file=$cookies");
        $request = $client->getMessageFactory()->createRequest();
        $response = $client->getMessageFactory()->createResponse();
        $client->getProcedureCompiler()->disableCache();
        $request->addHeader('Cookie', self::$sessionname.'='.self::$sessionid);
        $request->addHeader('Gentry', getenv("GENTRY"));
        $request->addHeader('Gentry-Client', getenv("GENTRY_CLIENT"));
        $request->addHeader('User-Agent', 'Gentry/PhantomJs headless');
        $request->setTimeout(5000);
        return [$client, $request, $response];
    }
}

