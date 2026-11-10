<?php
namespace Framework\Provider\Type;

use Framework\Provider\Type\CurlMethod;
use Framework\Utils\Dictionary;

/**
 * The OpenAI Output
 */
class OpenAIOutput {

    public Dictionary $response;

    public string $error         = "";

    public string $url           = "";
    public string $route         = "";

    public CurlMethod $method    = CurlMethod::POST;

    public string $externalID    = "";
    public string $vectorStoreID = "";
    public string $fileIDs       = "";

    public string $text          = "";
    public string $language      = "";
    public int    $duration      = 0;

    public int    $runTime       = 0;
    public int    $inputTokens   = 0;
    public int    $outputTokens  = 0;
    public bool   $didSearchCall = false;

    /** @var list<array{callID:string,name:string,arguments:array<int|string,mixed>}> */
    public array  $functionCalls = [];



    /**
     * Creates a Basic Output.
     * The method is always the same for now, so it is not given
     * @param Dictionary $response
     * @param string     $error    Optional.
     * @param string     $url      Optional.
     * @param string     $route    Optional.
     */
    public function __construct(
        Dictionary $response,
        string $error = "",
        string $url = "",
        string $route = "",
    ) {
        $this->response   = $response;
        $this->externalID = $response->getString("id");
        $this->error      = $error;
        $this->url        = $url;
        $this->route      = $route;

        if ($error === "" && $response->hasValue("error")) {
            $error = $response->get("error");
            if (is_string($error)) {
                $this->error = $error;
            } else {
                $this->error = $response->getDict("error")->getString("message");
            }
        }
    }
}
