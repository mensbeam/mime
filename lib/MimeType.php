<?php
/** @license MIT
 * Copyright 2020 J. King et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace MensBeam\Mime;

/** @property-read string $type
 * @property-read string $subtype
 * @property-read string $essence
 * @property-read array $params
 */
class MimeType {
    protected const TYPE_PATTERN = <<<'PATTERN'
        <^
            [\t\r\n ]*                              # optional leading whitespace
            ([^/]+)                                 # type  
            /                                       # type/subtype delimiter
            ([^;]+)                                 # subtype (possibly with trailing whitespace)
            (;.*)?                                  # optional parameters, to be parsed separately
            [\t\r\n ]*                              # optional trailing whitespace
        $>sx
PATTERN;
    protected const PARAM_PATTERN = <<<'PATTERN'
        <
            [;\t\r\n ]*                             # parameter delimiter and leading whitespace, all optional
            ([^=;]*)                                # parameter name; may be empty
            (?:=                                    # parameter name/value delimiter
                (
                    "(?:\\"|[^"])*(?:"|$)[^;]*      # quoted parameter value and optional garbage
                    |[^;]*                          # unquoted parameter value (possibly with trailing whitespace)
                )
            )?
            ;?                                      # optional trailing parameter delimiter
            [\t\r\n ]*                              # optional trailing whitespace
        >sx
PATTERN;
    protected const TOKEN_PATTERN = '<^[A-Za-z0-9!#$%&\'*+\-\.\^_`|~]+$>s';
    protected const BARE_VALUE_PATTERN = '<^[\t\x{20}-\x{7E}\x{80}-\x{FF}]+$>su';
    protected const QUOTED_VALUE_PATTERN = '<^"((?:\\\"|[\t !\x{23}-\x{7E}\x{80}-\x{FF}])*)(?:"|$)>su';
    protected const ESCAPE_PATTERN = '<\\\(.)>s';
    protected const CHAR_MAP = [0x80 => "\u{80}","\u{81}","\u{82}","\u{83}","\u{84}","\u{85}","\u{86}","\u{87}","\u{88}","\u{89}","\u{8a}","\u{8b}","\u{8c}","\u{8d}","\u{8e}","\u{8f}","\u{90}","\u{91}","\u{92}","\u{93}","\u{94}","\u{95}","\u{96}","\u{97}","\u{98}","\u{99}","\u{9a}","\u{9b}","\u{9c}","\u{9d}","\u{9e}","\u{9f}","\u{a0}","\u{a1}","\u{a2}","\u{a3}","\u{a4}","\u{a5}","\u{a6}","\u{a7}","\u{a8}","\u{a9}","\u{aa}","\u{ab}","\u{ac}","\u{ad}","\u{ae}","\u{af}","\u{b0}","\u{b1}","\u{b2}","\u{b3}","\u{b4}","\u{b5}","\u{b6}","\u{b7}","\u{b8}","\u{b9}","\u{ba}","\u{bb}","\u{bc}","\u{bd}","\u{be}","\u{bf}","\u{c0}","\u{c1}","\u{c2}","\u{c3}","\u{c4}","\u{c5}","\u{c6}","\u{c7}","\u{c8}","\u{c9}","\u{ca}","\u{cb}","\u{cc}","\u{cd}","\u{ce}","\u{cf}","\u{d0}","\u{d1}","\u{d2}","\u{d3}","\u{d4}","\u{d5}","\u{d6}","\u{d7}","\u{d8}","\u{d9}","\u{da}","\u{db}","\u{dc}","\u{dd}","\u{de}","\u{df}","\u{e0}","\u{e1}","\u{e2}","\u{e3}","\u{e4}","\u{e5}","\u{e6}","\u{e7}","\u{e8}","\u{e9}","\u{ea}","\u{eb}","\u{ec}","\u{ed}","\u{ee}","\u{ef}","\u{f0}","\u{f1}","\u{f2}","\u{f3}","\u{f4}","\u{f5}","\u{f6}","\u{f7}","\u{f8}","\u{f9}","\u{fa}","\u{fb}","\u{fc}","\u{fd}","\u{fe}","\u{ff}"];

    private $type = "";
    private $subtype = "";
    private $params = [];
    private $essence;

    private function __construct(string $type = "", string $subtype = "", array $params = []) {
        $this->type = $type;
        $this->subtype = $subtype;
        $this->params = $params;
    }

    public function __get(string $name) {
        if ($name === "essence") {
            return $this->type."/".$this->subtype;
        }
        return $this->$name ?? null;
    }

    public function __toString(): string {
        $out = $this->__get("essence");
        if (is_array($this->params) && sizeof($this->params)) {
            foreach ($this->params as $name => $value) {
                $out .= ";$name=".(preg_match(self::TOKEN_PATTERN, $value) ? $value : '"'.str_replace(["\\", '"'], ["\\\\", "\\\""], $value).'"');
            }
        }
        return $out;
    }

    public static function parse(string $mimeType): ?self {
        if (preg_match(self::TYPE_PATTERN, $mimeType, $match)) {
            [$mimeType, $type, $subtype, $params] = array_pad($match, 4, "");
            if (strlen($type = static::parseHttpToken($type)) && strlen($subtype = static::parseHttpToken(rtrim($subtype, "\t\r\n ")))) {
                return new static(strtolower($type), strtolower($subtype), static::parseParams($params));
            }
        }
        return null;
    }

    public static function parseBytes(string $mimeType): ?self {
        return static::parse(static::decode($mimeType));
    }

    public static function decode(string $bytes): string {
        $out = "";
        for ($a = 0; $a < strlen($bytes); $a++) {
            $c = $bytes[$a];
            $p = ord($c);
            $out .= $p < 0x80 ? $c : self::CHAR_MAP[$a];
        }
        return $out;
    }

    public static function encode(string $chars): ?string {
        $map = array_combine(array_values(self::CHAR_MAP), range(chr(0x80), chr(0xFF)));
        $out = "";
        foreach (preg_split("<>u", $chars) as $c) {
            if (strlen($c) === 1) {
                $out .= $c;
            } elseif (isset($map[$c])) {
                $out .= $map[$c];
            } else {
                return null;
            }
        }
        return $out;
    }

    protected static function parseParams(string $params): array {
        $out = [];
        if (preg_match_all(self::PARAM_PATTERN, $params, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                [$param, $name, $value] = array_pad($match, 3, "");
                $name = strtolower(static::parseHttpToken($name));
                if (!strlen($name) || isset($out[$name])) {
                    continue;
                } elseif (strlen($value) && $value[0] === '"') {
                    $value = static::parseHttpQuotedValue($value);
                    if (is_null($value)) {
                        continue;
                    }
                } else {
                    $value = static::parseHttpBareValue($value);
                    if (!strlen($value)) {
                        continue;
                    }
                }
                $out[$name] = $value;
            }
        }
        return $out;
    }

    protected static function parseHttpToken(string $token): string {
        if (preg_match(self::TOKEN_PATTERN, $token, $match)) {
            return $token;
        }
        return "";
    }

    protected static function parseHttpBareValue(string $value): string {
        $value = rtrim($value, "\t\r\n ");
        if (preg_match(self::BARE_VALUE_PATTERN, $value, $match)) {
            return $value;
        }
        return "";
    }

    protected static function parseHttpQuotedValue(string $value): ?string {
        if (preg_match(self::QUOTED_VALUE_PATTERN, $value, $match)) {
            return preg_replace(self::ESCAPE_PATTERN, '$1', $match[1]);
        }
        return null;
    }
}
