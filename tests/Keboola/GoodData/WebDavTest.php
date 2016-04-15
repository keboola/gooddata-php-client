<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\WebDav;

class WebDavTest extends \PHPUnit_Framework_TestCase
{
    protected $client;

    public function __construct()
    {
        parent::__construct();

        $this->client = new WebDav(KBGDC_USERNAME, KBGDC_PASSWORD);
    }

    public function testWebDavGetUrl()
    {
        $this->assertEquals('https://secure-di.gooddata.com/uploads', $this->client->getUrl());
    }

    public function testWebDavUpload()
    {
        $folder = uniqid();
        $this->client->createFolder($folder);
        $this->assertTrue($this->client->fileExists($folder));

        $file1 = tempnam(sys_get_temp_dir(), uniqid());
        $this->client->upload($file1, $folder);
        $this->assertTrue($this->client->fileExists("$folder/".basename($file1)));

        $file2 = tempnam(sys_get_temp_dir(), uniqid());
        rename($file2, "$file2.txt");
        $file2 = "$file2.txt";
        $this->client->upload($file2, $folder);
        $this->assertTrue($this->client->fileExists("$folder/".basename($file2)));

        $result = $this->client->listFiles($folder);
        $this->assertCount(2, $result);
        $this->assertTrue(in_array(basename($file1), $result));
        $this->assertTrue(in_array(basename($file2), $result));

        $result = $this->client->listFiles($folder, ['txt']);
        $this->assertCount(1, $result);
        $this->assertFalse(in_array(basename($file1), $result));
        $this->assertTrue(in_array(basename($file2), $result));
    }

    public function testWebDavSaveLogs()
    {
        $folder = uniqid();
        $this->client->createFolder($folder);

        $message1 = uniqid();
        $message2 = uniqid();
        mkdir(sys_get_temp_dir()."/$folder");
        file_put_contents(sys_get_temp_dir()."/$folder/upload_status.json", json_encode([
            'error' => [
                'component' => uniqid(),
                'message' => $message1.' %s',
                'parameters' => [$message2]
            ]
        ]));
        $this->client->upload(sys_get_temp_dir()."/$folder/upload_status.json", $folder);

        $message3 = uniqid();
        file_put_contents(sys_get_temp_dir()."/$folder/file.log", $message3);
        $this->client->upload(sys_get_temp_dir()."/$folder/file.log", $folder);


        $this->client->saveLogs($folder, sys_get_temp_dir()."/$folder/result.json");
        $this->assertTrue(file_exists(sys_get_temp_dir()."/$folder/result.json"));
        $result = file_get_contents(sys_get_temp_dir()."/$folder/result.json");
        $result = json_decode($result, true);
        $this->assertArrayHasKey('upload_status.json', $result);
        $this->assertArrayHasKey('file.log', $result);
        $this->assertEquals("$message1 $message2", $result['upload_status.json']);
        $this->assertEquals($message3, $result['file.log']);
    }
}
