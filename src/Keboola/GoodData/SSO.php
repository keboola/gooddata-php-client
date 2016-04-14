<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

use PhpGpg\Driver\GnuPG\Cli;
use PhpGpg\PhpGpg;

class SSO
{
    const GOODDATA_EMAIL = 'security@gooddata.com';

    protected $encryptingEmail;
    protected $encryptingKey;

    public function __construct($email = null, $key = null)
    {
        $this->encryptingEmail = $email ?: self::GOODDATA_EMAIL;
        $this->encryptingKey = $key ?: file_get_contents(__DIR__ . '/gooddata-pub.key');
    }

    public function getUrl($user, $key, $provider, $targetUrl, $email, $validity = 86400)
    {
        $signData = json_encode(['email' => $email, 'validity' => time() + $validity]);

        PhpGpg::setDefaultDriver('\PhpGpg\Driver\GnuPG\Cli');
        /** @var Cli $gpg */
        $gpg = new PhpGpg(sys_get_temp_dir());
        $gpg->enableArmor();

        $gpg->importKey($key);
        $gpg->addSignKey($user);
        $result = $gpg->sign($signData, PhpGpg::SIG_MODE_NORMAL);

        $gpg->importKey($this->encryptingKey);
        $gpg->addEncryptKey($this->encryptingEmail);
        $result = $gpg->encrypt($result);

        return sprintf(
            "https://secure.gooddata.com/gdc/account/customerlogin?sessionId=%s&serverURL=%s&targetURL=%s",
            urlencode($result),
            urlencode($provider),
            urlencode($targetUrl)
        );
    }
}
