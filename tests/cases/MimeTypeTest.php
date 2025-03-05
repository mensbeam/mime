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
}
