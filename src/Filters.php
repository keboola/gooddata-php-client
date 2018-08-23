<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

class Filters
{
    /** @var  Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }
    
    public function create($pid, $name, $attrUri, $operator, $value, $overUri = null, $toUri = null)
    {
        if (is_array($value)) {
            foreach ($value as &$v) {
                $v = "[$v]";
            }
            $value = "(" . implode(',', $value) . ")";
        } elseif ($operator == 'IN') {
            $value = "([$value])";
        } else {
            $value = "[$value]";
        }

        $expression = "[$attrUri] $operator $value";
        if ($overUri && $toUri) {
            $expression = "($expression) OVER [$overUri] TO [$toUri]";
        }
        $result = $this->client->post("/gdc/md/{$pid}/obj", [
            'userFilter' => [
                'content' => [
                    'expression' => $expression
                ],
                'meta' => [
                    'category'  => 'userFilter',
                    'title' => $name
                ]
            ]
        ]);
        return $result['uri'];
    }

    public function assignToUser($pid, $uid, $filters = [])
    {
        $result = $this->client->post("/gdc/md/$pid/userfilters", [
            'userFilters' => [
                'items' => [
                    [
                        "user" => Users::getUriFromUid($uid),
                        "userFilters" => $filters
                    ]
                ]
            ]
        ]);

        if (!isset($result['userFiltersUpdateResult']['successful'])
            || !count($result['userFiltersUpdateResult']['successful'])) {
            throw Exception::unexpectedResponseError(
                'Assign filters to user failed',
                'POST',
                "/gdc/md/$pid/userfilters",
                $result
            );
        }
    }

    public function getForUser($pid, $uid)
    {
        $response = $this->client->get("/gdc/md/$pid/userfilters?users=" . Users::getUriFromUid($uid));
        return isset($response['userFilters']['items'][0]['userFilters'])
            ? $response['userFilters']['items'][0]['userFilters'] : [];
    }

    public function getForProject($pid)
    {
         $response = $this->client->get("/gdc/md/$pid/query/userfilters");
         return isset($response['query']['entries'])
             ? $response['query']['entries'] : [];
    }

    public function delete($uri)
    {
        $this->client->delete($uri);
    }
}
