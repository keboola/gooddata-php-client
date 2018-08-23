<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

class Users
{
    /** @var  Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public static function getUidFromUri($uri)
    {
        return substr($uri, strrpos($uri, '/')+1);
    }

    public static function getUriFromUid($uid)
    {
        return "/gdc/account/profile/$uid";
    }

    public function getUidFromEmail($email, $domain)
    {
        $result = $this->client->get("/gdc/account/domains/$domain/users?login=" . urlencode($email));
        return !empty($result['accountSettings']['items'][0]['accountSetting']['links']['self'])
            ? self::getUidFromUri($result['accountSettings']['items'][0]['accountSetting']['links']['self'])
            : false;
    }

    public function getUidFromEmailInProject($email, $pid)
    {
        foreach ($this->client->getProjects()->getUsersForProjectYield($pid) as $users) {
            foreach ($users as $user) {
                if (!empty($user['user']['content']['login'])
                    && strtolower($user['user']['content']['login']) == strtolower($email)) {
                        return self::getUidFromUri($user['user']['links']['self']);
                }
            }
        }
        return false;
    }

    public function createUser($login, $password, $domain, array $options = [])
    {
        $result = $this->client->post("/gdc/account/domains/$domain/users", [
            'accountSetting' => array_merge([
                'login' => strtolower($login),
                'email' => strtolower($login),
                'password' => $password,
                'verifyPassword' => $password
            ], $options),
        ]);

        if (isset($result['uri'])) {
            return self::getUidFromUri($result['uri']);
        } else {
            throw Exception::unexpectedResponseError(
                'Create user failed',
                'POST',
                "/gdc/account/domains/$domain/users",
                $result
            );
        }
    }

    public function getUser($uid)
    {
        return $this->client->get("/gdc/account/profile/$uid");
    }

    public function getCurrentUid()
    {
        $result = $this->client->get('/gdc/account/profile/current');
        return Users::getUidFromUri($result['accountSetting']['links']['self']);
    }

    public function updateUser($uid, $data)
    {
        $userData = $this->getUser($uid);
        unset($userData['accountSetting']['links']);
        unset($userData['accountSetting']['login']);
        unset($userData['accountSetting']['email']);
        unset($userData['accountSetting']['effectiveIpWhitelist']);
        $userData['accountSetting'] = array_merge($userData['accountSetting'], $data);
        $this->client->put("/gdc/account/profile/$uid", $userData);
    }

    public function deleteUser($uid)
    {
        $this->client->delete("/gdc/account/profile/$uid");
    }

    public function getProjectsYield($uid = null, $limit = 1000, $offset = 0)
    {
        if (!$uid) {
            $uid = $this->getCurrentUid();
        }

        $continue = true;
        do {
            $result = $this->client->get("/gdc/account/profile/$uid/projects?offset=$offset&limit=$limit");
            if (count($result['projects'])) {
                yield $result['projects'];
            } else {
                $continue = false;
            }
            $offset += $limit;
        } while ($continue);
    }


    public function getDomainUsersYield($domain)
    {
        // first page
        $result = $this->client->get("/gdc/account/domains/$domain/users");
        if (isset($result['accountSettings']['items'])) {
            yield $result['accountSettings']['items'];
        }

        // next pages
        while (isset($result['accountSettings']['paging']['next'])) {
            $result = $this->client->get($result['accountSettings']['paging']['next']);
            if (isset($result['accountSettings']['items'])) {
                yield $result['accountSettings']['items'];
            }
        }
    }
}
