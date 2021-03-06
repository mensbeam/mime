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

### MIME type groups

The MIME Sniffing specification defines a series of [MIME type groups](https://mimesniff.spec.whatwg.org/#mime-type-groups); these are exposed via the boolean properties `isArchive`, `isAudioVideo`, `isFont`, `isHtml`, `isImage`, `isJavascript`, `isJson`, `isScriptable`, `isXml`, and `isZipBased`. For example:

```php
$mimeType = \MensBeam\Mime\MimeType::parse("image/svg+xml");
var_export($mimeType->isImage);      // prints "true"
var_export($mimeType->isXml);        // prints "true"
var_export($mimeType->isScriptable); // prints "true"
var_export($mimeType->isArchive);    // prints "false"
```
