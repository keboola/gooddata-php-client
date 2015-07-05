<?php
/**
 * @package gooddata-php-client
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Tests;

use Keboola\GoodData\Client;

class UserTest extends \PHPUnit_Framework_TestCase
{
    public function testGetUserInfo()
    {
        $client = new Client();
        $client->login(GOODDATA_USERNAME, GOODDATA_PASSWORD);

        $userInfo = $client->getCurrentUser();
        $this->assertArrayHasKey('accountSetting', $userInfo);
        $this->assertArrayHasKey('login', $userInfo['accountSetting']);
        $this->assertEquals(GOODDATA_USERNAME, $userInfo['accountSetting']['login']);
        $this->assertArrayHasKey('links', $userInfo['accountSetting']);
        $this->assertArrayHasKey('self', $userInfo['accountSetting']['links']);

        $uid = $client->getUserIdFromUri($userInfo['accountSetting']['links']['self']);
        $this->assertStringEndsWith($uid, $userInfo['accountSetting']['links']['self']);
        $this->assertEquals($uid, $client->getCurrentUserId());
    }
}