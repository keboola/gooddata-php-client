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

        $sso = new SSO(null, null, KBGDC_API_URL);
        $ssoLink = $sso->getUrl(KBGDC_USERNAME, KBGDC_SSO_KEY, KBGDC_SSO_PROVIDER, $targetUrl, $user['email'], 3600, KBGDC_SSO_KEY_PASS);

        $stack = \GuzzleHttp\HandlerStack::create();
        $lastRequest = null;
        $stack->push(\GuzzleHttp\Middleware::mapRequest(function (Request $request) use (&$lastRequest) {
            $lastRequest = $request;
            return $request;
        }));
        $client = new Client([
            'handler' => $stack,
            \GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => true,
            'verify' => false
        ]);
        try {
            $client->request('GET', $ssoLink, ['headers' => [
                'Accept' => 'application/json'
            ]]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody();
                $this->fail("$response on link: $ssoLink");
            } else {
                $this->fail($e->getMessage() . " on link: $ssoLink");
            }
        }
        /** @var Request $lastRequest */
        $result = $lastRequest->getUri()->__toString();
        $this->assertStringEndsWith($targetUrl, urldecode($result));
    }
}
