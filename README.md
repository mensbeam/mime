# MIME Sniffing

This library aims to be a complete implementation of [the WHATWG Mime Sniffing](https://mimesniff.spec.whatwg.org/) specification, along with other features related to MIME types. Presently it does not implement MIME sniffing itself, but it will be expanded in due course once the specification stabilizes.

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

### Extracting from HTTP headers

A structured `MimeType` instance may also be produced [from one or more HTTP header lines](https://fetch.spec.whatwg.org/#concept-header-extract-mime-type) using the `extract()` method:

```php
/* Assume $response is a PSR-7 HTTP message containing the following
   header fields:

   Content-Type: text/html; charset=UTF-8, invalid
   Content-Type:
   Content-Type: text/html; foo=bar

*/
echo (string) \MensBeam\Mime\MimeType::extract($response->getHeader("Content-Type")); // prints "text/html;foo=bar;charset=UTF-8"
echo (string) \MensBeam\Mime\MimeType::extract($response->getHeaderLine("Content-Type")); // also prints "text/html;foo=bar;charset=UTF-8"
```

### Negotiating a content type

[HTTP content type negotiation](https://www.rfc-editor.org/rfc/rfc9110.html#name-accept) can be performed using the `negotiate` static method:

```php
/* Assume $request1 is a PSR-7 HTTP message containing the following
   header fields:

   Accept: application/json;q=0.8, application/xml
   Accept: text/html;q=0.1, text/*;q=0.7

   Assume $request2 is a PSR-7 HTTP message containing the following
   header fields:

   Accept: application/xml
   Accept: application/json

*/
$ourTypes1 = ["application/json", "application/xml"];
$ourTypes2 = ["text/html", "text/xml", "text/plain"];
echo \MensBeam\Mime\MimeType::negotiate($ourTypes1, $request1->getHeader("Accept")); // "application/xml" has higher qvalue, so is returned
echo \MensBeam\Mime\MimeType::negotiate($ourTypes2, $request1->getHeaderLine("Accept")); // "text/html" has lower qvalue and is disqualified; "text/xml" appears first in our array, so is returned
echo \MensBeam\Mime\MimeType::negotiate($ourTypes1, $request2->getHeader("Accept")); // "application/json" appears first in our array, so is returned
echo \MensBeam\Mime\MimeType::negotiate($ourTypes2, $request2->getHeaderLine("Accept")); // no types are acceptable; null is returned
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
