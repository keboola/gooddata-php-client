<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Exception;
use Keboola\GoodData\Users;

class UsersTest extends AbstractClientTest
{
    protected function setUp()
    {
        $this->markTestSkipped();
    }

    public function testUsersGetUidFromUri()
    {
        $uid = uniqid();
        $this->assertEquals($uid, Users::getUidFromUri("/gdc/account/profile/$uid"));
    }

    public function testUsersGetUriFromUid()
    {
        $uid = uniqid();
        $this->assertEquals("/gdc/account/profile/$uid", Users::getUriFromUid($uid));
    }

    public function testUsersGetUidFromEmail()
    {
        $usersApi = new Users($this->client);
        $email = uniqid().'@'.KBGDC_USERS_DOMAIN;
        $uid = Helper::createUser($email);

        $this->assertEquals($uid, $usersApi->getUidFromEmail($email, KBGDC_DOMAIN));
    }

    public function testUsersCreateUser()
    {
        $usersApi = new Users($this->client);

        $login = uniqid().'@'.uniqid().'.com';
        $uid = $usersApi->createUser($login, md5($login), KBGDC_DOMAIN, [
            'firstName' => uniqid(),
            'lastName' => uniqid()
        ]);

        $result = $this->client->get("/gdc/account/profile/$uid");
        $this->assertArrayHasKey('accountSetting', $result);
        $this->assertArrayHasKey('login', $result['accountSetting']);
        $this->assertEquals($login, $result['accountSetting']['login']);

        $this->client->delete("/gdc/account/profile/$uid");
    }

    public function testUsersCreateUserAlreadyExists()
    {
        $usersApi = new Users($this->client);

        try {
            $usersApi->createUser(KBGDC_USERNAME, md5(KBGDC_USERNAME), KBGDC_DOMAIN, [
                'firstName' => uniqid(),
                'lastName' => uniqid()
            ]);
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(400, $e->getCode());
        }
    }

    public function testUsersDeleteUser()
    {
        $uid = Helper::createUser();

        $result = $this->client->get("/gdc/account/profile/$uid");
        $this->assertArrayHasKey('accountSetting', $result);
        $this->assertArrayHasKey('login', $result['accountSetting']);

        $usersApi = new Users($this->client);
        $usersApi->deleteUser($uid);

        $result = $this->client->get("/gdc/account/profile/$uid");
        $this->assertArrayHasKey('accountSetting', $result);
        $this->assertArrayHasKey('login', $result['accountSetting']);
        $this->assertArrayHasKey('email', $result['accountSetting']);
        $this->assertNotEquals($result['accountSetting']['login'], $result['accountSetting']['email']);
    }

    public function testUsersGetUidFromEmailInProject()
    {
        $pid = Helper::getSomeProject();
        $email = uniqid().'@'.KBGDC_USERS_DOMAIN;
        $uid = Helper::createUser($email);
        Helper::getClient()->getProjects()->addUser($pid, $uid);

        $usersApi = new Users($this->client);
        $this->assertEquals($uid, $usersApi->getUidFromEmailInProject($email, $pid));
    }

    public function testUsersGetAndUpdate()
    {
        $uid = Helper::createUser();

        $usersApi = new Users($this->client);
        $result = $usersApi->getUser($uid);
        $this->assertArrayHasKey('accountSetting', $result);
        $this->assertArrayHasKey('login', $result['accountSetting']);
        $this->assertArrayHasKey('email', $result['accountSetting']);

        $newName = uniqid();
        $result = $usersApi->getUser($uid);
        $this->assertArrayHasKey('accountSetting', $result);
        $this->assertArrayHasKey('firstName', $result['accountSetting']);
        $this->assertNotEquals($newName, $result['accountSetting']['firstName']);

        $usersApi->updateUser($uid, ['firstName' => $newName]);
        $result = $usersApi->getUser($uid);
        $this->assertArrayHasKey('accountSetting', $result);
        $this->assertArrayHasKey('firstName', $result['accountSetting']);
        $this->assertEquals($newName, $result['accountSetting']['firstName']);
    }

    public function testUsersGetCurrentUid()
    {
        $usersApi = new Users($this->client);
        $result = $this->client->get('/gdc/account/profile/current');
        $this->assertEquals(
            Users::getUidFromUri($result['accountSetting']['links']['self']),
            $usersApi->getCurrentUid()
        );
    }

    public function testUsersGetProjectsYield()
    {
        $userClient = clone $this->client;

        $login = uniqid().'@'.KBGDC_USERS_DOMAIN;
        $pass = md5($login);
        $uid = Helper::createUser($login, $pass);
        $userClient->login($login, $pass);
        $users = new Users($userClient);

        $count = 0;
        foreach ($users->getProjectsYield($uid, 1) as $usersBatch) {
            $count += count($usersBatch);
        }
        $this->assertEquals(0, $count);

        $pid = Helper::getSomeProject();
        Helper::getClient()->getProjects()->addUser($pid, $uid);

        $count = 0;
        foreach ($users->getProjectsYield($uid, 1) as $usersBatch) {
            $count += count($usersBatch);
        }
        $this->assertEquals(1, $count);
    }

    public function testUsersGetDomainUsersYield()
    {
        $users = new Users($this->client);
        $uid1 = Helper::createUser();
        $uid1Found = false;
        $uid2 = Helper::createUser();
        $uid2Found = false;
        
        foreach ($users->getDomainUsersYield(KBGDC_DOMAIN) as $users) {
            foreach ($users as $user) {
                if ($user['accountSetting']['links']['self'] == Users::getUriFromUid($uid1)) {
                    $uid1Found = true;
                } elseif ($user['accountSetting']['links']['self'] == Users::getUriFromUid($uid2)) {
                    $uid2Found = true;
                }
            }
        }

        $this->assertTrue($uid1Found);
        $this->assertTrue($uid2Found);
    }
}
