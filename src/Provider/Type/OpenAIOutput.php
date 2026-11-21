<?php
namespace Framework\Provider\Type;

use Framework\Provider\Type\CurlMethod;
use Framework\Utils\Dictionary;

/**
 * The OpenAI Output
 */
class OpenAIOutput {

    public Dictionary $response;

    public string $error           = "";

    public string $url             = "";
    public string $route           = "";

    public CurlMethod $method      = CurlMethod::POST;

    public string $externalID      = "";
    public string $vectorStoreID   = "";
    public string $fileIDs         = "";

    public string $text            = "";
    public string $language        = "";
    public int    $duration        = 0;

    public int    $runTime         = 0;
    public int    $inputTokens     = 0;
    public int    $outputTokens    = 0;
    public int    $cachedTokens    = 0;
    public int    $webSearchCalls  = 0;
    public int    $fileSearchCalls = 0;

    /** @var list<array{callID:string,name:string,arguments:array<int|string,mixed>}> */
    public array  $functionCalls   = [];



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

    /**
     * Sets the tokens of the given Response or Chat Completion
     * @param Dictionary $response
     * @return void
     */
    public function setUsage(Dictionary $response): void {
        $usage = $response->getDict("usage");

        // A Response names the tokens as input and output, and a Chat Completion as prompt and
        // completion. The cached ones are part of the input, and are billed at a lower price
        if ($usage->has("input_tokens")) {
            $this->inputTokens  = $usage->getInt("input_tokens");
            $this->outputTokens = $usage->getInt("output_tokens");
            $this->cachedTokens = $usage->getDict("input_tokens_details")->getInt("cached_tokens");
        } else {
            $this->inputTokens  = $usage->getInt("prompt_tokens");
            $this->outputTokens = $usage->getInt("completion_tokens");
            $this->cachedTokens = $usage->getDict("prompt_tokens_details")->getInt("cached_tokens");
        }
    }

    /**
     * Sets the amount of web and file searches that the given Response ran
     * @param Dictionary $response
     * @return void
     */
    public function setSearchCalls(Dictionary $response): void {
        $this->webSearchCalls  = 0;
        $this->fileSearchCalls = 0;

        // The searches are billed by each call the model made, and not by the tools it got
        foreach ($response->getList("output") as $output) {
            $type = $output->getString("type");
            if ($type === "web_search_call") {
                $this->webSearchCalls += 1;
            } elseif ($type === "file_search_call") {
                $this->fileSearchCalls += 1;
            }
        }
    }
}
