<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

use GuzzleHttp\HandlerStack as HandlerStackBase;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ClientHandlerStack
{
    public static function create($options = [])
    {
        $handlerStack = HandlerStackBase::create();
        $handlerStack->push(Middleware::retry(
            self::createDefaultDecider(isset($options['backoffMaxTries'])  ? $options['backoffMaxTries'] : 0),
            self::createExponentialDelay()
        ));
        return $handlerStack;
    }

    private static function createDefaultDecider($maxRetries = 5)
    {
        return function (
            $retries,
            RequestInterface $request,
            ResponseInterface $response = null,
            $error = null
        ) use ($maxRetries) {
            if ($retries >= $maxRetries) {
                return false;
            } elseif ($response && $response->getStatusCode() > 499) {
                return true;
            } elseif ($error) {
                return true;
            } else {
                return false;
            }
        };
    }

    private static function createExponentialDelay()
    {
        return function ($retries) {
            return (int) pow(2, $retries - 1) * 1000;
        };
    }
}
