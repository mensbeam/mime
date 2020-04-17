# MIME Sniffing

This library aims to be a complete implementation of [the WHATWG Mime Sniffing](https://mimesniff.spec.whatwg.org/) specification. Presently it only implements MIME type parsing (in other words, MIME sniffing itself is not implemented), but it will be expanded in due course.

## Features

### Parsing

A MIME type string may be parsed into a structured `MimeType` instance as follows:

```php
$mimeType = \MensBeam\Mime\MimeType::parse("text/HTML; charSet=UTF-8");
echo $mimeType->type;              // prints "text"
echo $mimeType->subtype;           // prints "html"
echo $mimeType->essence;           // prints "text/html"
echo $mimeType->params['charset']; // prints "UTF-8"
```

### Normalizing

Once parsed, a `MimeType` instance can be serialized to produce a normalized text representation:

```php
$typeString = 'TeXt/HTML;  CHARset="UTF\-8"; charset=iso-8859-1; unset='; 
$mimeType = \MensBeam\Mime\MimeType::parse($typeString);
echo (string) $mimeType; // prints "text/html;charset=UTF-8"
```
