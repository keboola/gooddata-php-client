# GoodData API PHP client by Keboola
[![Latest Stable Version](https://poser.pugx.org/keboola/gooddata-php-client/v/stable.svg)](https://packagist.org/packages/keboola/gooddata-php-client)
[![License](https://poser.pugx.org/keboola/gooddata-php-client/license.svg)](https://packagist.org/packages/keboola/gooddata-php-client)
[![Total Downloads](https://poser.pugx.org/keboola/gooddata-php-client/downloads.svg)](https://packagist.org/packages/keboola/gooddata-php-client)

## Installation

Library is available as composer package.
To start using composer in your project follow these steps:

**Install composer**
  
```bash
curl -s http://getcomposer.org/installer | php
mv ./composer.phar ~/bin/composer # or /usr/local/bin/composer
```

**Create composer.json file in your project root folder:**
```json
{
    "require": {
        "php" : ">=5.4.0",
        "keboola/gooddata-php-client": "~0.1"
    }
}
```

**Install package:**

```bash
composer install
```

**Add autoloader in your bootstrap script:**

```php
require 'vendor/autoload.php';
```

Read more in [Composer documentation](http://getcomposer.org/doc/01-basic-usage.md)

## Usage examples

```php
require 'vendor/autoload.php';

$client = new \Keboola\GoodData\Client();
$client->login(GOODDATA_USERNAME, GOODDATA_PASSWORD);

$pid = $client->createProject('Project name', GOODDATA_AUTH_TOKEN);
```