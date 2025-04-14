[![](https://img.shields.io/packagist/v/inspiredminds/contao-search-and-replace.svg)](https://packagist.org/packages/inspiredminds/contao-search-and-replace)
[![](https://img.shields.io/packagist/dt/inspiredminds/contao-search-and-replace.svg)](https://packagist.org/packages/inspiredminds/contao-search-and-replace)

Contao Search & Replace
=======================

Allows you to search

## Asynchronous Operation

This extension support asynchronous operation via Symfony Messenger. This is important for lage databases.

In Contao **5** you will only need to route the messages manually:

```yaml
# config/config.yaml
framework:
    messenger:
        routing:
            'InspiredMinds\ContaoSearchAndReplace\Message\SearchMessage': contao_prio_normal
            'InspiredMinds\ContaoSearchAndReplace\Message\ReplaceMessage': contao_prio_normal
```

In Contao **4.13** you will also have to create a messenger transport, e.g.:

```yaml
framework:
    messenger:
        transports:
            search_and_replace: 'doctrine://default?queue_name=search_and_replace'
        routing:
            'InspiredMinds\ContaoSearchAndReplace\Message\SearchMessage': search_and_replace
            'InspiredMinds\ContaoSearchAndReplace\Message\ReplaceMessage': search_and_replace
```

If you use a `doctrine://` transport you will also have to install `symfony/doctrine-messenger`:

```
composer require symfony/doctrine-messenger
```

Then you have to consume the messages somehow via

```
vendor/bin/contao-console messenger:consume search_and_replace
```

e.g. via a `crontab` entry like this:

```
* * * * * /usr/bin/php /var/www/example.com/vendor/bin/contao-console messenger:consume search_and_replace --time-limit=59 --quiet
```
