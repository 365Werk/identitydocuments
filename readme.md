# Laravel Identity Documents

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![StyleCI][ico-styleci]][link-styleci]

For general questions and suggestions join gitter:

[(https://badges.gitter.im/werk365/identitydocuments.svg)](https://gitter.im/werk365/identitydocuments?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Package to parse Machine Readable Passports and other travel documents from image in Laravel.

MRZ is parsed and validated by check numbers and returned.

## Installation

Via Composer

``` bash
$ composer require werk365/identitydocuments
```

## Usage
This package uses Google's Vision API to do OCR, this requires you to make a service account and download the JSON keyfile. In order to use it in this project, store the key found in the file as an array in config/google_key.php like this:
```php
return [
    "type" => "service_account",
    "project_id" => "",
    "private_key_id" => "",
    "private_key" => "",
    "client_email" => "",
    "client_id" => "",
    "auth_uri" => "",
    "token_uri" => "",
    "auth_provider_x509_cert_url" => "",
    "client_x509_cert_url" => "",
];
```

Call the parse method from anywhere passing a POST request. The method expects 'front_img' and 'back_img' to be the images of the travel documents, although you can use only one if a single image contains all required data.
```php
use werk365\IdentityDocuments\IdentityDocuments;

public function annotate(Request $request){
    return IdentityDocuments::parse($request);
}
```

This returns the document type, MRZ, the parsed MRZ and all raw text found on the images.

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Contributing

Please see [contributing.md](contributing.md) for details and a todolist.

## Security

If you discover any security related issues, please email <hergen.dillema@gmail.com> instead of using the issue tracker.

## Credits

- [Hergen Dillema][link-author]
- [All Contributors][link-contributors]

## License

. Please see the [license file](license.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/werk365/identitydocuments.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/werk365/identitydocuments.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/werk365/identitydocuments/master.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/281089912/shield

[link-packagist]: https://packagist.org/packages/werk365/identitydocuments
[link-downloads]: https://packagist.org/packages/werk365/identitydocuments
[link-travis]: https://travis-ci.org/werk365/identitydocuments
[link-styleci]: https://styleci.io/repos/281089912
[link-author]: https://github.com/HergenD
[link-contributors]: ../../contributors
