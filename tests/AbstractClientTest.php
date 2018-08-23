<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Client;

abstract class AbstractClientTest extends \PHPUnit\Framework\TestCase
{
    protected $client;

    public function getPackageName()
    {
        $composer = \GuzzleHttp\json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
        return str_replace('/', '-', $composer['name']);
    }

    public function getGitHash()
    {
        return trim(exec('git log --pretty="%h" -n1 HEAD'));
    }

    public function __construct()
    {
        parent::__construct();

        $this->client = new Client(KBGDC_API_URL, Helper::getLogger(), null, ['verify' => false]);
        $this->client->setUserAgent($this->getPackageName(), $this->getGitHash());
        $this->client->login(KBGDC_USERNAME, KBGDC_PASSWORD);
    }
}
