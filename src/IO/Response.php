<?php
namespace Framework\IO;

use Framework\IO\Errors;
use Framework\IO\Search;
use Framework\Utils\JSON;

use JsonSerializable;

/**
 * The Response wrapper
 */
class Response {

    /** @var array<string,mixed> */
    private array $data;

    private bool $withTokens;

    private int $statusCode = 200;

    private ?string $text = null;



    /**
     * Creates a new Response instance
     * @param array<string,mixed> $data       Optional.
     * @param bool                $withTokens Optional.
     */
    public function __construct(array $data = [], bool $withTokens = true) {
        $this->data       = $data;
        $this->withTokens = $withTokens;
    }

    /**
     * Adds the Tokens
     * @param string $accessToken
     * @param string $refreshToken
     * @return Response
     */
    public function addTokens(string $accessToken, string $refreshToken): Response {
        if (!$this->withTokens) {
            return $this;
        }

        $this->data["xAccessToken"] = $accessToken;
        if ($refreshToken !== "") {
            $this->data["xRefreshToken"] = $refreshToken;
        }
        return $this;
    }

    /**
     * Sets the HTTP Status Code, for the Responses that are not a success
     * @param int $statusCode
     * @return Response
     */
    public function setStatusCode(int $statusCode): Response {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Returns the HTTP Status Code
     * @return int
     */
    public function getStatusCode(): int {
        return $this->statusCode;
    }

    /**
     * Returns the Text to print instead of the Data, or null when it is printed as JSON
     * @return string|null
     */
    public function getText(): ?string {
        return $this->text;
    }

    /**
     * Returns the Data as an Object
     * @return array<string,mixed>
     */
    public function toArray(): array {
        return $this->data;
    }

    /**
     * Prints the Data Data
     * @return Response
     */
    public function printData(): Response {
        if (isset($this->data["data"])) {
            print(JSON::encode($this->data["data"], asPretty: true));
        }
        return $this;
    }



    /**
     * Creates an Empty Response
     * @param bool $withTokens Optional.
     * @return Response
     */
    public static function empty(bool $withTokens = true): Response {
        return new Response(withTokens: $withTokens);
    }

    /**
     * Creates an Exit Response
     * @param int $exitCode
     * @return Response
     */
    public static function exit(int $exitCode): Response {
        return new Response([ "result" => $exitCode ], false);
    }

    /**
     * Creates a Text Response, printed as it is instead of as JSON
     * @param string $text
     * @return Response
     */
    public static function text(string $text): Response {
        $result = new Response(withTokens: false);
        $result->text = $text;
        return $result;
    }

    /**
     * Creates a Result Response
     * @param array<string,mixed> $result Optional.
     * @return Response
     */
    public static function result(array $result = []): Response {
        return new Response($result);
    }

    /**
     * Creates a Data Response
     * @param JsonSerializable|array<string,mixed> $data
     * @return Response
     */
    public static function data(JsonSerializable|array $data): Response {
        return new Response([
            "data" => $data,
        ]);
    }

    /**
     * Creates a Search Response
     * @param list<Search> $data Optional.
     * @return Response
     */
    public static function search(array $data = []): Response {
        return new Response([
            "data" => $data,
        ]);
    }

    /**
     * Creates an Invalid Response
     * @return Response
     */
    public static function invalid(): Response {
        return new Response([
            "data" => [ "error" => true ],
        ]);
    }

    /**
     * Creates a Logout Response
     * @return Response
     */
    public static function logout(): Response {
        return new Response([
            "userLoggedOut" => true,
        ]);
    }



    /**
     * Creates a Success Response
     * @param string                                    $success
     * @param JsonSerializable|array<string,mixed>|null $data    Optional.
     * @param string                                    $param   Optional.
     * @return Response
     */
    public static function success(
        string $success,
        JsonSerializable|array|null $data = null,
        string $param = "",
    ): Response {
        return new Response([
            "success" => $success,
            "param"   => $param,
            "data"    => $data,
        ]);
    }

    /**
     * Creates a Warning Response
     * @param string                                    $warning
     * @param JsonSerializable|array<string,mixed>|null $data    Optional.
     * @param string                                    $param   Optional.
     * @return Response
     */
    public static function warning(
        string $warning,
        JsonSerializable|array|null $data = null,
        string $param = "",
    ): Response {
        return new Response([
            "warning" => $warning,
            "param"   => $param,
            "data"    => $data,
        ]);
    }

    /**
     * Creates an Error Response
     * @param Errors|string                             $error
     * @param JsonSerializable|array<string,mixed>|null $data  Optional.
     * @param list<string>|string                       $param Optional.
     * @return Response
     */
    public static function error(
        Errors|string $error,
        JsonSerializable|array|null $data = null,
        array|string $param = "",
    ): Response {
        $params = is_array($param) ? $param : [ $param ];
        $param  = $params[0] ?? "";

        if ($error instanceof Errors) {
            if ($error->has("global")) {
                return new Response([
                    "error"  => $error->global,
                    "param"  => $param,
                    "params" => $params,
                    "data"   => $data,
                ]);
            }
            return new Response([
                "errors" => $error->get(),
                "data"   => $data,
            ]);
        }

        return new Response([
            "error"  => $error,
            "param"  => $param,
            "params" => $params,
            "data"   => $data,
        ]);
    }
}
