# The unofficial Fathom Analytics API

This is an unofficial PHP SDK to get statistics out of Fathom Analytics, as long as it does not offer an official API.

## Installation

You can install the package via composer:

```bash
composer require beyondcode/fathom-api
```

## Usage

```php
$analytics = new BeyondCode\FathomAnalytics($email, $password);

$sites = $analytics->getSites();

$analytics->getCurrentVisitors($siteId);

// Returns the visitor data for today
$analytics->getData($siteId);

// Returns the visitor data for the whole week until today
$analytics->getData($siteId, Carbon::now()->startOfWeek());

// Returns the visitor data for two days ago until yesterday.
$analytics->getData($siteId, Carbon::now()->subDays(2), Carbon::now()->subDays(1));
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email marcel@beyondco.de instead of using the issue tracker.

## Credits

- [Marcel Pociot](https://github.com/mpociot)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
