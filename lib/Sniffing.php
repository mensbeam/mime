<?php
/** @license MIT
 * Copyright 2020 J. King et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace MensBeam\Mime;

abstract class Sniffing {
    protected const SNIFF_PATTERNS_IMAGE = [
        '/^\x{00}\x{00}[\x{01}\x{02}]\x{00}/s'  => "image/x-icon",
        '/^BM/s'                                => "image/bmp",
        '/^GIF8[79]a/s'                         => "image/gif",
        '/^RIFF.{4}WEBPVP/s'                    => "image/webp",
        '/^\x{89}PNG\r\n\x{1A}\n/s'             => "image/png",
        '/^\x{FF}\x{D8}\x{FF}/s'                => "imaged/jpeg",
    ];
    protected const SNIFF_PATTERNS_AUDIOVIDEO = [
        '/^\.snd/s'                 => "audio/basic",
        '/^FORM.{4}AIFF/s'          => "audio/aiff",
        '/^ID3/s'                   => "audi/mpeg",
        '/^OggS\x{00}/s'            => "application/ogg",
        '/^MThd\x{00}{3}\x{06}/s'   => "audio/midi",
        '/^RIFF.{4}AVI /s'          => "video/avi",
        '/^RIFF.{4}WAVE/s'          => "audio/wave",
    ];
    protected const SNIFF_PATTERNS_FONT = [
        '/^.{34}LP/s'                   => "application/vnd.ms-fontobject",
        '/^\x{00}\x{01}\x{00}{2}/s'     => "font/ttf",
        '/^OTTO/s'                      => "font/otf",
        '/^ttcf/s'                      => "font/collection",
        '/^wOFF/s'                      => "font/woff",
        '/^wOF2/s'                      => "font/woff2",
    ];
    protected const SNIFF_PATTERNS_ARCHIVE = [
        '/^\x{1F}\x{8B}\x{08}/s'        => "application/x-gzip",
        '/^PK\x{03}\x{04}/s'            => "application/zip",
        '/^Rar \x{1A}\x{07}\x{00}/s'    => "application/x-rar-compressed",
    ];
    protected const SNIFF_PATTERNS_UNKNWON_SCRIPTABLE = [
        '/^\s*<(?:!DOCTYPE HTML|HTML|HEAD|SCRIPT|IFRAME|H1|DIV|FONT|TABLE|A|B|STYLE|TITLE|BODY|BR|P|!--)[ >]/si'    => "text/html",
        '/^\s*<\?xml/s'                                                                                             => "text/xml",
        '/^%PDF-/s'                                                                                                 => "application/pdf",
    ];
    protected const SNIFF_PATTERN_UNKNWON_SAFE = [
        '/^%!PS-Adobe-/s'                                               => "application/postscript",
        '/^(?:(?:\x{FE}\x{FF}|\x{FF}\x{FE})..|\x{EF}\x{BB}\x{BF}.)/s'   => "text/plain",
    ];

    public static function interpretHttpMessage(\Psr\Http\Message\MessageInterface $msg, bool $sniff = true): ?MimeType {
        $checkForApacheBug = false;
        // Use the last Content-Type header-field
        $type = array_pop($msg->getHeader("Content-Type"));
        if (!is_null($type)) {
            if ($msg instanceof \Psr\Http\Message\ResponseInterface) {
                $checkForApacheBug = (bool) preg_match("<^text/plain(?:; charset=(?:UTF-8|(?:ISO|iso)-8859-1))?$>", $type);
            }
            $type = MimeType::decode($type);
        }
        # stub
        return null;
    }

    public static function sniffImage(string $resource): ?MimeType {
        foreach (self::SNIFF_PATTERNS_IMAGE as $pattern => $type) {
            if (preg_match($pattern, $resource)) {
                return MimeType::parse($type);
            }
        }
        return null;
    }

    public static function sniffAudioVideo(string $resource): ?MimeType {
        foreach (self::SNIFF_PATTERNS_AUDIOVIDEO as $pattern => $type) {
            if (preg_match($pattern, $resource)) {
                return MimeType::parse($type);
            }
        }
        return static::sniffMp4($resource) ?? static::sniffWebm($resource) ?? static::sniffMp3($resource);
    }

    protected static function sniffMp4(string $d): ?MimeType {
        if (strlen($d) < 12) {
            return null;
        }
        $boxSize = hexdec(bin2hex(substr($d, 0, 4)));
        if (strlen($d) < $boxSize || $boxSize % 4 > 0 || substr($d, 4, 4) !== "ftyp") {
            return null;
        }
        if (substr($d, 8, 3) === "mp4") {
            return MimeType::parse("video/mp4");
        }
        $bytesRead = 16;
        while ($bytesRead < $boxSize) {
            if (substr($d, $bytesRead, 3) === "mp4") {
                return MimeType::parse("video/mp4");
            }
            $bytesRead += 4;
        }
        return null;
    }

    protected static function sniffWebm(string $d): ?MimeType {
        $length = strlen($d);
        if ($length < 4 || substr($d, 0, 4) !== "\x1A\x45\xDF\xA3") {
            return null;
        }
        $iter = 4;
        while ($iter < 38 && $iter < $length) {
            if (substr($d, $iter, 2) === "\x42\x82") {
                $iter += 2;
                if ($iter >= $length) {
                    return null;
                }
                $iter += static::parseVint($d, $iter);
            }
        }
        return null;
    }

    protected static function parseVint(string $d, int $iter): int {
        return 1;
    }

    protected static function sniffMp3(string $d): ?MimeType {
        return null;
    }
}
