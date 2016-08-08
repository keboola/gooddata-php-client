<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

use Crypt_GPG;

class SSO
{
    const GOODDATA_EMAIL = 'security@gooddata.com';
    const BASE_URI = 'https://secure.gooddata.com';

    protected $encryptingEmail;
    protected $encryptingKey;
    protected $baseUri;

    public function __construct($email = null, $key = null, $baseUri = null)
    {
        $this->encryptingEmail = $email ?: self::GOODDATA_EMAIL;
        $this->encryptingKey = $key ?: file_get_contents(__DIR__ . '/gooddata-pub.key');
        $this->baseUri = $baseUri ?: self::BASE_URI;
    }

    public function getUrl($user, $key, $provider, $targetUrl, $email, $validity = 86400, $keyPass = null)
    {
        $signData = json_encode(['email' => $email, 'validity' => time() + $validity]);

        $gpg = new Crypt_GPG(['homedir' => sys_get_temp_dir()]);

        $gpg->importKey($key);
        $gpg->addSignKey($user, $keyPass);

        $gpg->importKey($this->encryptingKey);
        $gpg->addEncryptKey($this->encryptingEmail);

        $result = $gpg->encryptAndSign($signData);
        if (!$result) {
            throw new \Exception("Sso generation for user $email using admin $user failed, session id is empty.");
        }

        return sprintf(
            "%s/gdc/account/customerlogin?sessionId=%s&serverURL=%s&targetURL=%s",
            $this->baseUri,
            urlencode($result),
            urlencode($provider),
            urlencode($targetUrl)
        );
    }
}
