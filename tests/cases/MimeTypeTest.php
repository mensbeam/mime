<?php
/** @license MIT
 * Copyright 2020 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace MensBeam\Mime\TestCase;

use MensBeam\Mime\MimeType as Mime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Mime::class)]
class MimeTypeTest extends \PHPUnit\Framework\TestCase {
    #[DataProvider("provideStandardParsingTests")]
    public function testStandardTestSuite(string $input, ?string $exp): void {
        if (is_null($exp)) {
            $this->assertNull(Mime::parse($input));
        } else {
            $this->assertSame($exp, (string) Mime::parse($input));
        }
    }

    public static function provideStandardParsingTests(): iterable {
        foreach (new \GlobIterator(__DIR__."/*mime-types.json", \FilesystemIterator::CURRENT_AS_PATHNAME | \FilesystemIterator::KEY_AS_FILENAME) as $file => $path) {
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

    #[DataProvider("provideMimeTypeGroups")]
    public function testDetermineMimeTypeGroups(string $type, array $booleans): void {
        $t = Mime::parse($type);
        foreach ($booleans as $prop => $exp) {
            $this->assertSame($exp, $t->$prop, "Property $prop does not match expectation");
        }
    }

    public static function provideMimeTypeGroups(): iterable {
        $propMap = [
            'image'          => "isImage",
            'audio or video' => "isAudioVideo",
            'font'           => "isFont",
            'ZIP-based'      => "isZipBased",
            'archive'        => "isArchive",
            'XML'            => "isXml",
            'HTML'           => "isHtml",
            'scriptable'     => "isScriptable",
            'JavaScript'     => "isJavascript",
            'JSON'           => "isJson",
        ];
        foreach (new \GlobIterator(__DIR__."/mime-groups.json", \FilesystemIterator::CURRENT_AS_PATHNAME | \FilesystemIterator::KEY_AS_FILENAME) as $file => $path) {
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
                    $output = array_combine(array_values($propMap), array_fill(0, sizeof($propMap), false));
                    foreach ($test->groups as $group) {
                        assert(isset($propMap[$group]), "Group '$group' is not mapped to a property");
                        $output[$propMap[$group]] = true;
                    }
                    yield "$file #$index$description" => [$test->input, $output];
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

    #[DataProvider("provideCombinedTypes")]
    public function testSplitMultiples($in, array $exp): void {
        $m = new \ReflectionMethod(Mime::class, "split");
        $this->assertSame($exp, $m->invoke(null, $in));
    }

    public static function provideCombinedTypes(): iterable {
        return [
            ['test,test',                      ['test', 'test']],
            [['test', 'test, test'],           ['test', 'test', 'test']],
            ['test;a="a,b",test',              ['test;a="a,b"', 'test']],
            ['',                               []],
            ['a,',                             ['a']],
            [',a',                             ['a']],
            ['test;b="ook\\\\,eek\\"\\!",eek', ['test;b="ook\\\\,eek\\"\\!"', 'eek']]
        ];
    }

    #[DataProvider("provideHeaderFields")]
    public function testExtractFromHeader($in, ?string $exp) {
        $this->assertSame($exp, (string) Mime::extract($in));
    }

    public static function provideHeaderFields(): iterable {
        // most of these tests are taken from examples in the specification
        return [
            ['text/html, text/*',                               'text/*'],
            ['text/plain;charset=gbk, text/html',               'text/html'],
            ['text/html;charset=gbk;a=b, text/html;x=y',        'text/html;x=y;charset=gbk'],
            [['text/html;charset=gbk;a=b', 'text/html;x=y'],    'text/html;x=y;charset=gbk'],
            [['text/html;charset=gbk', 'x/x', 'text/html;x=y'], 'text/html;x=y'],
            [['text/html', 'cannot-parse'],                     'text/html'],
            [['text/html', '*/*'],                              'text/html'],
            [['text/html', ''],                                 'text/html'],
        ];
    }

    #[DataProvider("provideNegotiations")]
    public function testNegotiateContentTypes(array $types, $accept, $exp): void {
        if ($exp instanceof \Throwable) {
            $this->expectException(get_class($exp));
            Mime::negotiate($types, $accept);
        } else {
            $this->assertSame($exp, Mime::negotiate($types, $accept));
        }
    }

    public static function provideNegotiations(): iterable {
        return [
            "No acceptable types"  => [["text/plain", "text/html"],  "",                                    null],
            "Invalid remote types" => [["text/plain", "text/html"],  "invalid",                             null],
            "Wildcard Types 1"     => [["text/plain", "text/html"],  "*/*",                                 "text/plain"],
            "Wildcard Types 2"     => [["text/plain", "text/html"],  "text/*",                              "text/plain"],
            "Bogus qvalue 1"       => [["text/plain", "text/html"],  "text/html, text/plain;q=!",           "text/plain"],
            "Bogus qvalue 2"       => [["text/plain", "text/html"],  "text/html;q=3, text/plain;q=2",       "text/plain"],
            "Bogus qvalue 3"       => [["text/plain", "text/html"],  "text/html, text/plain;q=0.0001",      "text/plain"],
            "Bogus qvalue 4"       => [["text/plain", "text/html"],  "text/html, text/plain;q=0.0010",      "text/plain"],
            "Bogus qvalue 5"       => [["text/plain", "text/html"],  "text/html, text/plain;q=\" 0.1\"",    "text/plain"],
            "Bogus qvalue 6"       => [["text/plain", "text/html"],  "text/html;q=1.1, text/plain",         "text/plain"],
            "Valid qvalue 1"       => [["text/plain", "text/html"],  "text/html, text/plain;q=0.1",         "text/html"],
            "Valid qvalue 2"       => [["text/plain", "text/html"],  "text/html;q=0.009, text/plain;q=0.1", "text/plain"],
            "Valid qvalue 3"       => [["text/plain", "text/html"],  "text/html, text/plain;q=\"0.1\"",     "text/html"],
            "Valid qvalue 4"       => [["text/plain", "text/html"],  "text/html, text/plain;q=\"0\\.1\"",   "text/html"],
            "Valid qvalue 5"       => [["text/plain", "text/html"],  "text/*;q=0.8, text/plain;q=0.1",      "text/html"],
            "Parameters 1"         => [["text/plain;charset=ascii"], "text/plain",                          "text/plain;charset=ascii"],
            "Parameters 2"         => [["text/plain;charset=ascii"], "text/plain;charset=\"ascii\"",        "text/plain;charset=ascii"],
            "Parameters 3"         => [["text/plain;charset=ascii"], "text/*;charset=ascii",                "text/plain;charset=ascii"],
            "Parameters 4"         => [["text/plain;charset=ascii"], "*/*;charset=ascii",                   "text/plain;charset=ascii"],
            "Parameters 5"         => [["x/y;a=a;b=b"],              "x/y;b=b;a=a",                         "x/y;a=a;b=b"],
            "Failure 1"            => [["invalid"],                  "x/y",                                 new \InvalidArgumentException()],
            "Failure 2"            => [["text/*"],                   "x/y",                                 new \InvalidArgumentException()],
        ];
    }
}
