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
    protected $pid;
    protected $user;

    protected function setUp()
    {
        $this->pid = Helper::getSomeProject();
        $this->user = Helper::getSomeUser();
        Helper::getClient()->getProjects()->addUser($this->pid, $this->user['uid']);
        parent::setUp();
    }

    public function testSSOLink()
    {
        $targetUrl = "/#s=/gdc/projects/{$this->pid}|projectDashboardPage";

        $sso = new SSO(null, null, KBGDC_API_URL);
        $ssoLink = $sso->getUrl(KBGDC_USERNAME, KBGDC_SSO_KEY, KBGDC_SSO_PROVIDER, $targetUrl, $this->user['email'], 3600, KBGDC_SSO_KEY_PASS);

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

    public function testSSOClaims()
    {
        $targetUrl = "/#s=/gdc/projects/{$this->pid}|projectDashboardPage";

        $sso = new SSO(null, null, KBGDC_API_URL);
        $claims = $sso->getClaims(KBGDC_USERNAME, KBGDC_SSO_KEY, KBGDC_SSO_PROVIDER, $targetUrl, $this->user['email'], 3600, KBGDC_SSO_KEY_PASS);

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
            $client->request(
                'POST',
                'https://secure.gooddata.com/gdc/account/customerlogin',
                [
                    'headers' => [
                        'Accept' => 'application/json'
                    ],
                    'json' => [
                        'pgpLoginRequest' => [
                            'encryptedClaims' => $claims['encryptedClaims'],
                            'ssoProvider' => $claims['ssoProvider'],
                            'targetUrl' => $targetUrl,
                        ],
                    ],
                ]
            );
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody();
                $this->fail("$response on claims: " . print_r($claims));
            } else {
                $this->fail($e->getMessage() . " on claims: " . print_r($claims));
            }
        }
        /** @var Request $lastRequest */
        $result = $lastRequest->getUri()->__toString();
        $this->assertStringEndsWith($targetUrl, urldecode($result));
    }
}
