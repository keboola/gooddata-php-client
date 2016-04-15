<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Keboola\GoodData\SSO;

class SSOTest extends \PHPUnit_Framework_TestCase
{
    public function testSSO()
    {
        $pid = Helper::getSomeProject();
        $user = Helper::getSomeUser();
        Helper::getClient()->getProjects()->addUser($pid, $user['uid']);

        $targetUrl = "/#s=/gdc/projects/$pid|projectDashboardPage";

        $sso = new SSO();
        $ssoLink = $sso->getUrl(KBGDC_USERNAME, KBGDC_SSO_KEY, KBGDC_SSO_PROVIDER, $targetUrl, $user['email']);

        $stack = \GuzzleHttp\HandlerStack::create();
        $lastRequest = null;
        $stack->push(\GuzzleHttp\Middleware::mapRequest(function (Request $request) use (&$lastRequest) {
            $lastRequest = $request;
            return $request;
        }));
        $client = new Client([
            'handler' => $stack,
            \GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => true
        ]);
        try {
            $client->request('GET', $ssoLink, ['headers' => [
                'Accept' => 'application/json'
            ]]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody();
                $this->fail($response);
            } else {
                $this->fail($e->getMessage());
            }
        }
        /** @var Request $lastRequest */
        $result = $lastRequest->getUri()->__toString();
        $this->assertStringEndsWith($targetUrl, urldecode($result));
    }
}
