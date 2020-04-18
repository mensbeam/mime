<?php
/** @license MIT
 * Copyright 2020 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace MensBeam\Mime\TestCase;

use MensBeam\Mime\MimeType as Mime;

/** @covers \MensBeam\Mime\MimeType */
class MimeTypeTest extends \PHPUnit\Framework\TestCase {
    /** @dataProvider provideStandardTests */
    public function testStandardTestSuite(string $input, ?string $exp): void {
        if (is_null($exp)) {
            $this->assertNull(Mime::parse($input));
        } else {
            $this->assertSame($exp, (string) Mime::parse($input));
        }
    }

    public function provideStandardTests(): iterable {
        foreach (new \GlobIterator(__DIR__."/*.json", \FilesystemIterator::CURRENT_AS_PATHNAME | \FilesystemIterator::KEY_AS_FILENAME) as $file => $path) {
            $indexOffset = 0;
            $description = "";
            foreach (json_decode(file_get_contents($path)) as $index => $test) {
                if (is_string($test)) {
                    // the array member is a description of the next member
                    // the index offset should be decremented, the description stored, and this entry skipped
                    $indexOffset--;
                    $description = $test;
                    continue;
                } else {
                    $index += $indexOffset;
                    $description = $description ? ": $description" : "";
                    yield "$file #$index$description" => [$test->input, $test->output];
                    $description = null;
                }
            }
        }
    }

    public function testDecodeAByteString(): void {
        // set up the test with the Intl extension
        $input = "";
        $exp = "";
        for ($a = 0; $a <= 0xFF; $a++) {
            $input .= chr($a);
            $exp .= \IntlChar::chr($a);
        }
        // perform the test
        $this->assertSame($exp, Mime::decode($input));
    }

    public function testEncodeAValidString(): void {
        // set up the test with the Intl extension
        $input = "";
        $exp = "";
        for ($a = 0; $a <= 0xFF; $a++) {
            $exp .= chr($a);
            $input .= \IntlChar::chr($a);
        }
        // perform the test
        $this->assertSame($exp, Mime::encode($input));
    }

    public function testEncodeAnInvalidString(): void {
        $input = "!\u{1F4A9}!";
        $this->assertNull(Mime::encode($input));
    }

    public function testParseAByteString(): void {
        $input = "application/unknown;param=\"\xE9tude\"";
        $exp = "application/unknown;param=\"\u{E9}tude\"";
        $this->assertSame($exp, (string) Mime::parseBytes($input));
    }

    public function testAccessInstanceProperties(): void {
        $input = "TEXT/HTML; VERSION=3.2; charset=utf-8; charset=iso-8859-1;";
        $obj = Mime::parse($input);
        $this->assertInstanceOf(Mime::class, $obj);
        $this->assertSame("text", $obj->type);
        $this->assertSame("html", $obj->subtype);
        $this->assertSame("text/html", $obj->essence);
        $this->assertSame(['version' => "3.2", 'charset' => "utf-8"], $obj->params);
    }
}
