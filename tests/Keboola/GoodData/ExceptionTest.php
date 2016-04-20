<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Exception;

class ExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testExceptionConstruct()
    {
        $message = ['error' => uniqid()];
        $e = new Exception($message);
        $json = json_decode($e->getMessage(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals($message['error'], $json['error']);
    }

    public function testExceptionLoginError()
    {
        $e = Exception::loginError();
        $this->assertEquals(403, $e->getCode());
    }

    public function testExceptionUnexpectedResponseError()
    {
        $message = uniqid();
        $method = uniqid();
        $uri = uniqid();
        $response = uniqid();
        $e = Exception::unexpectedResponseError($message, $method, $uri, $response);
        $json = json_decode($e->getMessage(), true);
        $this->assertArrayHasKey('description', $json);
        $this->assertEquals($message, $json['description']);
        $this->assertArrayHasKey('method', $json);
        $this->assertEquals($method, $json['method']);
        $this->assertArrayHasKey('uri', $json);
        $this->assertEquals($uri, $json['uri']);
        $this->assertArrayHasKey('response', $json);
        $this->assertEquals($response, $json['response']);
        $this->assertEquals(400, $e->getCode());
    }

    public function testExceptionError()
    {
        $message = uniqid();
        $uri = uniqid();
        $code = 429;
        $e = Exception::error($uri, $message, $code);
        $this->assertEquals($code, $e->getCode());
        $this->assertNotEquals($message, $e->getMessage());
    }

    public function testExceptionConfigurationError()
    {
        $message = uniqid();
        $e = Exception::configurationError($message);
        $this->assertEquals($message, $e->getMessage());
    }

    public function testExceptionParseMessage()
    {
        $par1 = uniqid();
        $par2 = uniqid();
        $message = [
            'details' => [
                'error' => [
                    'validationErrors' => [
                        [
                            'validationError' => [
                                'message' => '%s %s',
                                'parameters' => [$par1, $par2]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $e = Exception::configurationError($message);
        $this->assertNotEquals($message, $e->getMessage());
        $json = json_decode($e->getMessage(), true);
        $this->assertCount(1, $json);
        $this->assertEquals("$par1 $par2", $json[0]);
    }
}
