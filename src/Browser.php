<?php

namespace Gentry;

use JonnyW\PhantomJs\Client;

class Browser
{
    public function get($url)
    {
        $client = Client::getInstance();
        $client->getEngine()->setPath(getenv("GENTRY_VENDOR").'/bin/phantomjs');
        $request = $client->getMessageFactory()->createRequest();
        $response = $client->getMessageFactory()->createResponse();
        $client->getProcedureCompiler()->disableCache();
        $request->setMethod('GET');
        $request->setUrl($url);
        $request->addHeader('Cookie', session_name().'='.session_id());
        $request->addHeader('Gentry', getenv("GENTRY"));
        $request->addHeader('Gentry-Client', getenv("GENTRY_CLIENT"));
        $client->send($request, $response);
        return $response;
    }
}

