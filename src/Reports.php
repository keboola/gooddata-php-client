<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

class Reports
{
    /** @var  Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function execute($uri)
    {
        $this->client->post('/gdc/xtab2/executor3', [
            'report_req' => [
                'report' => $uri
            ]
        ]);
    }

    public function export($pid, $uri)
    {
        $result = $this->client->post("/gdc/app/projects/$pid/execute/raw/", [
            "report_req" => [
                "reportDefinition" => $uri
            ]
        ]);
        return $result['uri'];
    }
}
