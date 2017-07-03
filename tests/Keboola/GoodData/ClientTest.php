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
        $client = new Client(KBGDC_API_URL);
        $client->setUserAgent($this->getPackageName(), $this->getGitHash());
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
        $client = new Client(KBGDC_API_URL);
        $client->setUserAgent($this->getPackageName(), $this->getGitHash());
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
            $this->client->setApiUrl('https://secure.gooddata.com');
            $this->client->get('/gdc/ping');
            $ping = true;
        } catch (\Exception $e) {
            $ping = false;
        }

        $this->assertEquals($ping, $this->client->ping());
    }

    public function testGoodDataClientGetUserUploadUrl()
    {
        $data = json_decode('{
  "about": {
    "summary": "Use links to navigate the services.",
    "category": "GoodData API root",
    "links": [
      {
        "link": "/gdc/releaseInfo",
        "summary": "Release information.",
        "category": "releaseInfo",
        "title": "releaseInfo"
      },
      {
        "link": "/gdc/uploads",
        "summary": "User data staging area.",
        "category": "uploads",
        "title": "user-uploads"
      }
    ]
  }
}', true);
        $this->assertEquals(KBGDC_API_URL . '/gdc/uploads', $this->client->getUserUploadUrlFromGdcResponse($data));

        $data = json_decode('{
  "about": {
    "summary": "Use links to navigate the services.",
    "category": "GoodData API root",
    "links": [
      {
        "link": "/gdc/releaseInfo",
        "summary": "Release information.",
        "category": "releaseInfo",
        "title": "releaseInfo"
      },
      {
        "link": "https://secure-di.gooddata.com/uploads",
        "summary": "User data staging area.",
        "category": "uploads",
        "title": "user-uploads"
      }
    ]
  }
}', true);
        $this->assertEquals('https://secure-di.gooddata.com/uploads', $this->client->getUserUploadUrlFromGdcResponse($data));
    }

    public function testGoodDataClientGetSubClasses()
    {
        $this->assertInstanceOf('\Keboola\GoodData\Datasets', $this->client->getDatasets());
        $this->assertInstanceOf('\Keboola\GoodData\DateDimensions', $this->client->getDateDimensions());
        $this->assertInstanceOf('\Keboola\GoodData\Filters', $this->client->getFilters());
        $this->assertInstanceOf('\Keboola\GoodData\ProjectModel', $this->client->getProjectModel());
        $this->assertInstanceOf('\Keboola\GoodData\Projects', $this->client->getProjects());
        $this->assertInstanceOf('\Keboola\GoodData\Reports', $this->client->getReports());
        $this->assertInstanceOf('\Keboola\GoodData\Users', $this->client->getUsers());
    }
}
