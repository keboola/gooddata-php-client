<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Client;

abstract class AbstractClientTest extends \PHPUnit_Framework_TestCase
{
    protected $client;

    public function __construct()
    {
        parent::__construct();

        $this->client = new Client(KBGDC_API_URL);
        $this->client->setLogger(Helper::getLogger());
        $this->client->login(KBGDC_USERNAME, KBGDC_PASSWORD);
    }
}
