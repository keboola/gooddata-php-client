<?php
/**
 * @package gooddata-php-client
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

class Exception extends \Exception
{
    /**
     * Response from API
     * @var array
     */
    private $response;

    /**
     * @return array
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param array $response
     */
    public function setResponse(array $response)
    {
        $this->response = $response;
    }

    public function __construct($message, $code = 0, \Exception $previous = null, array $response = [])
    {
        parent::__construct($message, $code, $previous);
    }
}
