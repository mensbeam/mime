<?php
/** @license MIT
 * Copyright 2020 J. King et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace MensBeam\Mime;

/** A structured representation of a MIME type, consitent with the WHATWG MIME Sniffing specification
 *
 * The class is not instantiated directly, but rather via many of its static methods.
 * If parsing e.g. "TeXt/HTML; X=a; Y=B", the result will expose the following read-only
 * properties:
 *
 * - `type`: `"text"`
 * - `subtype`: `"html"`
 * - `essence`: `"text/html"`
 * - `params`: `['x' => "a", 'y' => "B"]`
 *
 * Instances may be cast to strings to yield a normalized representation
 *
 * @see https://mimesniff.spec.whatwg.org/
 *
 * @property-read string $type The major type of the MIME type i.e. the part before the slash
 * @property-read string $subtype The subtype of the MIME type i.e. the part after the slash
 * @property-read string $essence The full MIME type without paramters e.g. `"text/html"`
 * @property-read array $params The associative array of parameters included with the type. Keys are lowercase; values are presented in their original case, unescaped
 * @property-read bool $isArchive Whether the MIME type is an archive type
 * @property-read bool $isAudioVideo Whether the MIME type is an audio or video type
 * @property-read bool $isFont Whether the MIME type is a font type
 * @property-read bool $isHtml Whether the MIME type is HTML
 * @property-read bool $isImage Whether the MIME type is an image type
 * @property-read bool $isJavascript Whether the MIME type is a JavaScript type
 * @property-read bool $isJson Whether the MIME type is a JSON type
 * @property-read bool $isScriptable Whether the MIME type is a type which can be scripted (namely via JavaScript)
 * @property-read bool $isXml Whether the MIME type is an XML type
 * @property-read bool $isZipBased Whether the MIME type is a ZIP-based type
 */
class MimeType {
    protected const TYPE_PATTERN = <<<'PATTERN'
        /^
            [\t\r\n ]*                              # optional leading whitespace
            ([^\/]+)                                # type  
            \/                                      # type-subtype delimiter
            ([^;]+)                                 # subtype (possibly with trailing whitespace)
            (;.*)?                                  # optional parameters, to be parsed separately
            [\t\r\n ]*                              # optional trailing whitespace
        $/sx
PATTERN;
    protected const PARAM_PATTERN = <<<'PATTERN'
        /
            [;\t\r\n ]*                             # parameter delimiter and leading whitespace, all optional
            ([^=;]*)                                # parameter name; may be empty
            (?:=                                    # parameter name-value delimiter
                (
                    "(?:\\"|[^"])*(?:"|$)[^;]*      # quoted parameter value and optional garbage
                    |[^;]*                          # unquoted parameter value (possibly with trailing whitespace)
                )
            )?
            ;?                                      # optional trailing parameter delimiter
            [\t\r\n ]*                              # optional trailing whitespace
        /sx
PATTERN;
    protected const TOKEN_PATTERN = '/^[A-Za-z0-9!#$%&\'*+\-\.\^_`|~]+$/s';
    protected const BARE_VALUE_PATTERN = '/^[\t\x{20}-\x{7E}\x{80}-\x{FF}]+$/su';
    protected const QUOTED_VALUE_PATTERN = '/^"((?:\\\"|[\t !\x{23}-\x{7E}\x{80}-\x{FF}])*)(?:"|$)/su';
    protected const ESCAPE_PATTERN = '/\\\(.)/s';
    protected const CHAR_MAP = [0x80 => "\u{80}","\u{81}","\u{82}","\u{83}","\u{84}","\u{85}","\u{86}","\u{87}","\u{88}","\u{89}","\u{8a}","\u{8b}","\u{8c}","\u{8d}","\u{8e}","\u{8f}","\u{90}","\u{91}","\u{92}","\u{93}","\u{94}","\u{95}","\u{96}","\u{97}","\u{98}","\u{99}","\u{9a}","\u{9b}","\u{9c}","\u{9d}","\u{9e}","\u{9f}","\u{a0}","\u{a1}","\u{a2}","\u{a3}","\u{a4}","\u{a5}","\u{a6}","\u{a7}","\u{a8}","\u{a9}","\u{aa}","\u{ab}","\u{ac}","\u{ad}","\u{ae}","\u{af}","\u{b0}","\u{b1}","\u{b2}","\u{b3}","\u{b4}","\u{b5}","\u{b6}","\u{b7}","\u{b8}","\u{b9}","\u{ba}","\u{bb}","\u{bc}","\u{bd}","\u{be}","\u{bf}","\u{c0}","\u{c1}","\u{c2}","\u{c3}","\u{c4}","\u{c5}","\u{c6}","\u{c7}","\u{c8}","\u{c9}","\u{ca}","\u{cb}","\u{cc}","\u{cd}","\u{ce}","\u{cf}","\u{d0}","\u{d1}","\u{d2}","\u{d3}","\u{d4}","\u{d5}","\u{d6}","\u{d7}","\u{d8}","\u{d9}","\u{da}","\u{db}","\u{dc}","\u{dd}","\u{de}","\u{df}","\u{e0}","\u{e1}","\u{e2}","\u{e3}","\u{e4}","\u{e5}","\u{e6}","\u{e7}","\u{e8}","\u{e9}","\u{ea}","\u{eb}","\u{ec}","\u{ed}","\u{ee}","\u{ef}","\u{f0}","\u{f1}","\u{f2}","\u{f3}","\u{f4}","\u{f5}","\u{f6}","\u{f7}","\u{f8}","\u{f9}","\u{fa}","\u{fb}","\u{fc}","\u{fd}","\u{fe}","\u{ff}"];

    protected $type = "";
    protected $subtype = "";
    protected $params = [];
    private $essence;
    private $isArchive;
    private $isAudioVideo;
    private $isFont;
    private $isHtml;
    private $isImage;
    private $isJavascript;
    private $isJson;
    private $isScriptable;
    private $isXml;
    private $isZipBased;

    protected function __construct(string $type = "", string $subtype = "", array $params = []) {
        $this->type = $type;
        $this->subtype = $subtype;
        $this->params = $params;
    }

    public function __get(string $name) {
        switch ($name) {
            case "essence":
                return $this->essence();
            case "isArchive":
                return in_array($this->essence(), ["application/zip", "application/x-gzip", "application/x-rar-compressed"]);
            case "isAudioVideo":
                return $this->type === "audio" || $this->type === "video" || $this->essence() === "application/ogg";
            case "isFont":
                return $this->type === "font" || preg_match("<^application/(?:font-(?:cff|off|sfnt|ttf|woff)|vnd\.ms-(?:fontobject|opentype))$>", $this->essence());
            case "isHtml":
                return $this->essence() === "text/html";
            case "isImage":
                return $this->type === "image";
            case "isJavascript":
                return (bool) preg_match("<^(?:(?:text|application)/(?:(?:x-)?(?:ecma|java)script)|text/(?:livescript|jscript|javascript1\.[0-5]))$>", $this->essence());
            case "isJson":
                return substr($this->subtype, -5) === "+json" || preg_match("<^(?:text|application)/json$>", $this->essence());
            case "isScriptable":
                return $this->essence() === "application/pdf" || $this->__get("isHtml") || $this->__get("isXml");
            case "isXml":
                return substr($this->subtype, -4) === "+xml" || preg_match("<^(?:text|application)/xml$>", $this->essence());
            case "isZipBased":
                return substr($this->subtype, -4) === "+zip" || $this->essence() === "application/zip";
            default:
                return $this->$name ?? null;
        }
    }

    public function __toString(): string {
        $out = $this->essence();
        if (is_array($this->params) && sizeof($this->params)) {
            foreach ($this->params as $name => $value) {
                $out .= ";$name=".(preg_match(self::TOKEN_PATTERN, $value) ? $value : '"'.str_replace(["\\", '"'], ["\\\\", "\\\""], $value).'"');
            }
        }
        return $out;
    }

    protected function essence(): string {
        return $this->type."/".$this->subtype;
    }

    /** Parses a UTF-8 string and returns a MimeType instance, or null on failure
     *
     * If parsing an HTTP header, the MimeType::parseBytes method should be used instead
     *
     * @see \MensBeam\Mime\MimeType::parseBytes
     */
    public static function parse(string $mimeType): ?self {
        if (preg_match(self::TYPE_PATTERN, $mimeType, $match)) {
            [$mimeType, $type, $subtype, $params] = array_pad($match, 4, "");
            if (strlen($type = static::parseHttpToken($type)) && strlen($subtype = static::parseHttpToken(rtrim($subtype, "\t\r\n ")))) {
                return new static(strtolower($type), strtolower($subtype), static::parseParams($params));
            }
        }
        return null;
    }

    /** Parses a binary string and returns a MimeType instance, or null on failure
     *
     * This should be used on MIME type strings from HTTP headers, which use a special character set
     */
    public static function parseBytes(string $mimeType): ?self {
        return static::parse(static::decode($mimeType));
    }

    /** Returns the UTF-8 isomorphically decoded form of the binary string $bytes
     *
     * @see https://infra.spec.whatwg.org/#isomorphic-decode
     * @param string $bytes The binary string to decode to UTF-8
     */
    public static function decode(string $bytes): string {
        $out = "";
        for ($a = 0; $a < strlen($bytes); $a++) {
            $c = $bytes[$a];
            $p = ord($c);
            $out .= $p < 0x80 ? $c : self::CHAR_MAP[$p];
        }
        return $out;
    }

    /** Returns the isomorphically encoded form of the UTF-8 input string $chars
     *
     * If the input contains characters beyond the Latin-1 Supplement block, null is returned
     *
     * This method should be used when a MIME type of unknown provenance is to be inserted into an HTTP header
     *
     * @see https://infra.spec.whatwg.org/#isomorphic-encode
     * @param string $chars The UTF-8 encoded string to convert to binary
     */
    public static function encode(string $chars): ?string {
        $map = array_combine(array_values(self::CHAR_MAP), range(chr(0x80), chr(0xFF)));
        $out = "";
        $set = array_reverse(preg_split("<>u", $chars));
        array_pop($set);
        while (sizeof($set) > 1) {
            $c = array_pop($set);
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

    /** Parses a parameter string into an associative array of keys and values
     *
     * If a parameter appears more than once, the first valid instance is used
     */
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

    /** Validates a st ring as an HTTP token production
     *
     * Returns an empty string if the string is not a valid token
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.6
     */
    protected static function parseHttpToken(string $token): string {
        if (preg_match(self::TOKEN_PATTERN, $token, $match)) {
            return $token;
        }
        return "";
    }

    /** Trims and validates a bare HTTP value string; per HTTP this should be a token, but WHATWG allows the full qdtext production
     *
     * Returns an empty string if the string is not a valid token
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.6
     */
    protected static function parseHttpBareValue(string $value): string {
        $value = rtrim($value, "\t\r\n ");
        if (preg_match(self::BARE_VALUE_PATTERN, $value, $match)) {
            return $value;
        }
        return "";
    }

    /** Trims and validates a quoted HTTP value string per the qdtext production
     *
     * Returns null if the string is not a valid token; an emptty string is a valid value
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.6
     */
    protected static function parseHttpQuotedValue(string $value): ?string {
        if (preg_match(self::QUOTED_VALUE_PATTERN, $value, $match)) {
            return preg_replace(self::ESCAPE_PATTERN, '$1', $match[1]);
        }
        return null;
    }
}
