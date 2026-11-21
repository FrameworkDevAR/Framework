<?php
// spell-checker: ignore  CURLOPT, RETURNTRANSFER, CUSTOMREQUEST, CONNECTTIMEOUT, POSTFIELDS
// spell-checker: ignore  HTTPHEADER, FOLLOWLOCATION, HTTPGET, USERPWD, HEADERFUNCTION
// spell-checker: ignore  VERIFYPEER, VERIFYHOST
namespace Framework\Provider;

use Framework\Analysis\Attr\NotTested;
use Framework\Provider\Type\CurlMethod;
use Framework\Utils\Arrays;
use Framework\Utils\JSON;
use Framework\Utils\Strings;
use Framework\Utils\URL;

/**
 * The Curl Utils
 */
class Curl {

    /**
     * Executes a Request
     * @param CurlMethod                $method
     * @param string                    $url
     * @param array<string,mixed>|null  $params       Optional.
     * @param array<string,string>|null $headers      Optional.
     * @param string                    $userPass     Optional.
     * @param bool                      $isCustom     Optional.
     * @param bool                      $jsonBody     Optional.
     * @param bool                      $urlBody      Optional.
     * @param string                    $rawBody      Optional.
     * @param bool                      $textBools    Optional.
     * @param bool                      $jsonResponse Optional.
     * @param bool                      $withHeaders  Optional.
     * @param bool                      $returnError  Optional.
     * @param bool                      $disableSSL   Optional.
     * @param bool                      $followUrl    Optional.
     * @param int                       $timeout      Optional.
     * @return mixed
     */
    public static function execute(
        CurlMethod $method,
        string $url,
        ?array $params = null,
        ?array $headers = null,
        string $userPass = "",
        bool $isCustom = false,
        bool $jsonBody = false,
        bool $urlBody = false,
        string $rawBody = "",
        bool $textBools = false,
        bool $jsonResponse = true,
        bool $withHeaders = false,
        bool $returnError = false,
        bool $disableSSL = false,
        bool $followUrl = false,
        int $timeout = 100,
    ): mixed {
        $options = self::getOptions(
            $method,
            $url,
            params:     $params,
            headers:    $headers,
            userPass:   $userPass,
            isCustom:   $isCustom,
            jsonBody:   $jsonBody,
            urlBody:    $urlBody,
            rawBody:    $rawBody,
            textBools:  $textBools,
            disableSSL: $disableSSL,
            followUrl:  $followUrl,
            timeout:    $timeout,
        );

        // Get the Headers
        $headers = [];
        if ($withHeaders) {
            $options[CURLOPT_HEADERFUNCTION] = function ($curl, string $header) use (&$headers) {
                $parts = Strings::split($header, ":");
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return Strings::length($header);
            };
        }

        // Execute the Curl
        $curl   = curl_init();
        $result = false;
        if ($curl !== false) {
            curl_setopt_array($curl, $options);
            $result = curl_exec($curl);
        }


        // Return the Error
        if ($returnError && $result === false) {
            $error = "Cannot initialize cURL";
            if ($curl !== false) {
                $error = curl_errno($curl) . ": " . curl_error($curl);
            }
            if ($withHeaders) {
                return [ "error" => $error, "headers" => $headers ];
            }
            if ($jsonResponse) {
                return [ "error" => $error ];
            }
            return $error;
        }

        // Get the Response
        $response = $result;
        if ($jsonResponse) {
            if ($result === false || $result === "") {
                $response = [];
            } elseif (!JSON::isValid($result)) {
                $response = [ "error" => $result ];
            } else {
                $response = JSON::decodeAsArray($result);
            }
        }

        // Return the Response
        if ($withHeaders) {
            return [ "response" => $response, "headers" => $headers ];
        }
        return $response;
    }

    /**
     * Returns the Curl Options for the given Request
     * @param CurlMethod                $method
     * @param string                    $url
     * @param array<string,mixed>|null  $params     Optional.
     * @param array<string,string>|null $headers    Optional.
     * @param string                    $userPass   Optional.
     * @param bool                      $isCustom   Optional.
     * @param bool                      $jsonBody   Optional.
     * @param bool                      $urlBody    Optional.
     * @param string                    $rawBody    Optional.
     * @param bool                      $textBools  Optional.
     * @param bool                      $disableSSL Optional.
     * @param bool                      $followUrl  Optional.
     * @param int                       $timeout    Optional.
     * @return array<int,mixed>
     */
    private static function getOptions(
        CurlMethod $method,
        string $url,
        ?array $params = null,
        ?array $headers = null,
        string $userPass = "",
        bool $isCustom = false,
        bool $jsonBody = false,
        bool $urlBody = false,
        string $rawBody = "",
        bool $textBools = false,
        bool $disableSSL = false,
        bool $followUrl = false,
        int $timeout = 100,
    ): array {
        // A bool is sent as a 1 or as an empty value, and an API that does not read the
        // empty one as a false takes the text instead
        if ($textBools && $params !== null) {
            foreach ($params as $key => $value) {
                if (is_bool($value)) {
                    $params[$key] = $value ? "true" : "false";
                }
            }
        }

        $options = [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => false,
            CURLOPT_FORBID_REUSE    => true,
            CURLOPT_TIMEOUT         => $timeout,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_LOW_SPEED_LIMIT => 512,
            CURLOPT_LOW_SPEED_TIME  => 120,
        ];
        if ($disableSSL) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = false;
        }
        if ($followUrl) {
            $options[CURLOPT_FOLLOWLOCATION] = true;
            $options[CURLOPT_MAXREDIRS]      = 5;
        }

        // GET Requests
        if (!$isCustom && $method === CurlMethod::GET) {
            $options[CURLOPT_URL]      = URL::addParams($url, $params);
            $options[CURLOPT_ENCODING] = "identity";

        // POST Requests
        } elseif (!$isCustom && $method === CurlMethod::POST) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_URL]  = $url;

            $body = $params;
            if ($rawBody !== "") {
                $body = $rawBody;
            } elseif ($jsonBody) {
                $body = JSON::encode($params);
            } elseif ($urlBody) {
                $body = URL::parseParams($params);
            }

            if (!Arrays::isEmpty($body)) {
                $options[CURLOPT_POSTFIELDS] = $body;
            }
            if ($headers !== null && is_scalar($body)) {
                $headers["Content-Length"] = (string)strlen($body);
            }

        // Custom Requests
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $method->toString();
            $options[CURLOPT_ENCODING]      = "identity";

            if ($rawBody !== "") {
                $options[CURLOPT_URL]        = $url;
                $options[CURLOPT_POSTFIELDS] = $rawBody;
            } elseif ($jsonBody) {
                $options[CURLOPT_URL]        = $url;
                $options[CURLOPT_POSTFIELDS] = JSON::encode($params);
            } elseif ($urlBody) {
                $options[CURLOPT_URL]        = $url;
                $options[CURLOPT_POSTFIELDS] = URL::parseParams($params);
            } else {
                $options[CURLOPT_URL] = URL::addParams($url, $params);
            }
        }

        // Set the Headers
        if ($headers !== null && count($headers) > 0) {
            $options[CURLOPT_HTTPHEADER] = self::parseHeader($headers);
        }

        // Set the User and Password
        if ($userPass !== "") {
            $options[CURLOPT_USERPWD] = $userPass;
        }
        return $options;
    }

    /**
     * Parses the Header
     * @param array<string,string> $headers
     * @return array<int,string>
     */
    private static function parseHeader(array $headers): array {
        $result = [];
        foreach ($headers as $key => $value) {
            $result[] = "{$key}: {$value}";
        }
        return $result;
    }



    /**
     * Executes a Read Request
     * @param string                    $url
     * @param string                    $filePath
     * @param array<string,string>|null $headers  Optional.
     * @return bool
     */
    #[NotTested("It needs a server")]
    public static function read(
        string $url,
        string $filePath,
        ?array $headers = null,
    ): bool {
        $file = fopen($filePath, "wb");
        if ($url === "" || $file === false) {
            return false;
        }

        $options = [
            CURLOPT_URL             => $url,
            CURLOPT_FILE            => $file,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_HTTPGET         => true,
            CURLOPT_TIMEOUT         => 100,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_LOW_SPEED_LIMIT => 512,
            CURLOPT_LOW_SPEED_TIME  => 120,
        ];

        // Set the Headers
        if ($headers !== null && count($headers) > 0) {
            $options[CURLOPT_HTTPHEADER] = self::parseHeader($headers);
        }

        // Execute the Curl
        $curl = curl_init();
        if ($curl === false) {
            fclose($file);
            return false;
        }

        curl_setopt_array($curl, $options);
        curl_exec($curl);
        fclose($file);

        // An answer with an error status writes its error page in the file
        $error  = curl_error($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        return $error === "" && $status < 400;
    }

    /**
     * Executes a Write Request
     * @param string                    $url
     * @param string                    $fileContent
     * @param array<string,string>|null $headers     Optional.
     * @return mixed
     */
    #[NotTested("It needs a server")]
    public static function write(
        string $url,
        string $fileContent,
        ?array $headers = null,
    ): mixed {
        if ($url === "" || $fileContent === "") {
            return [];
        }

        $options = [
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => $fileContent,
            CURLOPT_TIMEOUT         => 100,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_LOW_SPEED_LIMIT => 512,
            CURLOPT_LOW_SPEED_TIME  => 120,
        ];

        // Set the Headers
        if ($headers !== null && count($headers) > 0) {
            $options[CURLOPT_HTTPHEADER] = self::parseHeader($headers);
        }

        // Execute the Curl
        $curl = curl_init();
        if ($curl === false) {
            return [];
        }

        curl_setopt_array($curl, $options);
        curl_exec($curl);
        $output = curl_exec($curl);

        // Return the Response
        if (!is_string($output)) {
            return [];
        }
        $response = JSON::decode($output);
        return $response;
    }



    /**
     * Parses the Curl Options in a Readable Format for Debugging
     * @param array<int,string> $options
     * @return array<string,mixed>
     */
    public static function parseOptions(array $options): array {
        $constants = get_defined_constants(categorize: true);
        if (!isset($constants["curl"])) {
            return [];
        }

        $constants = $constants["curl"];
        $constMap  = [];
        foreach ($constants as $name => $value) {
            if (strpos($name, "CURLOPT_") === 0 && is_int($value)) {
                $constMap[$value] = $name;
            }
        }

        $readableOptions = [];
        foreach ($options as $key => $value) {
            $name = $constMap[$key] ?? "UNKNOWN_OPTION_{$key}";
            $readableOptions[$name] = $value;
        }

        return $readableOptions;
    }
}
