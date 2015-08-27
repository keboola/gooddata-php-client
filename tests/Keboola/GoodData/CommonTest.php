<?php
/**
 * @package gooddata-php-client
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Tests;

use Keboola\GoodData\Client;
use ReflectionClass;

class CommonTest extends \PHPUnit_Framework_TestCase
{
    public function testPing()
    {
        $client = new Client();
        $this->assertTrue(is_bool($client->ping()));
    }

    public function testFindingCookie()
    {
        $cookies = [
            'GDCAuthTT=ttAuthfkdsfjldks; path=/gdc; expires=Wed, 03-Jun-2015 14:45:37 GMT; secure; HttpOnly',
            'GDCAuthSST=He2EUhwfEyRtusOO; path=/gdc/account; secure; HttpOnly',
            'no-store, no-cache, must-revalidate, max-age=0',
            'CP=IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT'
        ];

        $client = new Client();
        $reflector = new ReflectionClass('\Keboola\GoodData\Client');
        $method = $reflector->getMethod('findCookie');
        $method->setAccessible(true);
        $result = $method->invokeArgs($client, [$cookies, 'GDCAuthSST']);
        $this->assertEquals('He2EUhwfEyRtusOO', $result);
        $result = $method->invokeArgs($client, [$cookies, 'GDCAuthTT']);
        $this->assertEquals('ttAuthfkdsfjldks', $result);
    }
}
