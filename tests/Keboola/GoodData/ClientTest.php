<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Client;
use Keboola\GoodData\Exception;

class ClientTest extends AbstractClientTest
{
    public function testGoodDataClientJsonLoginBadUsername()
    {
        $client = new Client();
        try {
            $client->login(uniqid(), KBGDC_PASSWORD);
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(403, $e->getCode());
        }
        try {
            $client->login(uniqid().'@'.uniqid().'.com', KBGDC_PASSWORD);
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testGoodDataClientJsonLoginBadPassword()
    {
        $client = new Client();
        try {
            $client->login(KBGDC_USERNAME, uniqid());
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testGoodDataClientJsonRequest()
    {
        $result = $this->client->jsonRequest('GET', '/gdc/account/profile/current');
        $this->assertArrayHasKey('accountSetting', $result);
        $this->assertArrayHasKey('login', $result['accountSetting']);
        $this->assertEquals(KBGDC_USERNAME, $result['accountSetting']['login']);
    }

    public function testGoodDataClientBadRequest()
    {
        try {
            $this->client->jsonRequest('GET', '/gdc/account/profile/'.uniqid());
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $this->client->jsonRequest('GET', '/gdc/'.uniqid());
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $this->client->jsonRequest('GET', '/gdc/projects/'.uniqid());
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testGoodDataClientForbiddenRequest()
    {
        try {
            $this->client->jsonRequest('GET', '/gdc/account/domains/'.KBGDC_OTHER_USERS_DOMAIN.'/users');
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testGoodDataClientPing()
    {
        try {
            $this->client->get('/gdc/ping');
            $ping = true;
        } catch (\Exception $e) {
            $ping = false;
        }

        $this->assertEquals($ping, $this->client->ping());
    }

    public function testGoodDataClientGetUserUploadUrl()
    {
        $data = $this->client->get('/gdc');
        $link = false;
        foreach ($data['about']['links'] as $r) {
            if ($r['category'] == 'uploads') {
                $link = $r['link'];
            }
        }

        $this->assertNotEmpty($link);
        $this->assertEquals($link, $this->client->getUserUploadUrl());
    }

    public function testGoodDataClientGetToFile()
    {
        $temp = Helper::getTemp();
        $file = $temp->createTmpFile();
        $this->client->request('GET', '/gdc/account/profile/current', [], true, [
            'accept' => 'text/plain',
            'accept-charset' => 'utf-8'
        ], $file->getPathname());
        $this->assertTrue(file_exists($file->getPathname()));
        $f = fopen($file, 'r');
        $this->assertEquals('accountSetting:', trim(fgets($f)));
        fclose($f);
    }
}
