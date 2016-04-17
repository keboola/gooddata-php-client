<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2013-03-19
 *
 */
namespace Keboola\GoodData;

class Exception extends \Exception
{
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        $message = self::parseMessage($message);
        if (is_array($message)) {
            $message = json_encode($message);
        }
        parent::__construct($message, $code, $previous);
    }

    public static function loginError(\Exception $previous = null)
    {
        return new static('GoodData login failed', 403, $previous);
    }

    public static function unexpectedResponseError($message, $method, $uri, $response = [])
    {
        return new static(json_encode([
            'description' => $message,
            'method' => $method,
            'uri' => $uri,
            'response' => $response
        ]), 400);
    }
    
    public static function error($uri, $message, $code = 0, \Exception $previous = null)
    {
        $message = self::parseMessage($message);
        switch ($code) {
            case 401:
                $message = (isset($message['message'])
                    && $message['message'] == 'Login needs security code verification due to failed login attempts.')
                    ? "GoodData login refused due to failed login attempts"
                    : "GoodData login failed. $message";
                break;
            case 403:
                $message = "GoodData user does not have access to resource '$uri'. $message";
                break;
            case 410:
                $message = "GoodData uri $uri is not reachable, project has been probably deleted. $message";
                break;
            case 429:
                $message = "Too many requests on GoodData API. Try again later please. $message";
                break;
        }
        if (is_array($message)) {
            $message = json_encode($message);
        }
        return new static($message, $code, $previous);
    }

    public static function parseMessage($message)
    {
        if (isset($message['message'])) {
            if (isset($message['parameters']) && count($message['parameters'])) {
                $message = vsprintf($message['message'], $message['parameters']);
            } else {
                $message = $message['message'];
            }
        }
        if (isset($message['error']['message'])) {
            if (isset($message['error']['parameters']) && count($message['error']['parameters'])) {
                $message = vsprintf($message['error']['message'], $message['error']['parameters']);
            } else {
                $message = $message['error']['message'];
            }
        }
        if (isset($message['details']['error']['validationErrors'])) {
            $errors = [];
            foreach ($message['details']['error']['validationErrors'] as $err) {
                $errors[] = vsprintf($err['validationError']['message'], $err['validationError']['parameters']);
            }
            $message = $errors;
        }
        return $message;
    }

    public function getData()
    {
        $json = json_decode($this->message, true);
        return $json ?: [];
    }
}
