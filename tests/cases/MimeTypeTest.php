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
}
