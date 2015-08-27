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

        $uid = Client::getIdFromUri($userInfo['accountSetting']['links']['self']);
        $this->assertStringEndsWith($uid, $userInfo['accountSetting']['links']['self']);
        $this->assertEquals($uid, $client->getCurrentUserId());
    }

    public function testCreateUpdateAndDeleteUser()
    {
        $client = new Client();
        $client->login(GOODDATA_USERNAME, GOODDATA_PASSWORD);

        $email = uniqid() . '@test.com';

        $userId = $client->createUser(GOODDATA_DOMAIN, $email, uniqid(), 'Test', 'Test');
        $this->assertNotEmpty($userId);

        $newFirstName = uniqid();
        $client->updateUser($userId, ['firstName' => $newFirstName]);
        $result = $client->getUser($userId);
        $this->assertArrayHasKey('accountSetting', $result);
        $this->assertArrayHasKey('firstName', $result['accountSetting']);
        $this->assertEquals($newFirstName, $result['accountSetting']['firstName']);

        $this->assertEquals($userId, $client->retrieveUserId($email, GOODDATA_DOMAIN));

        $this->assertEmpty($client->deleteUser($userId));
    }
}
