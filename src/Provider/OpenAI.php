<?php
namespace Framework\Provider;

use Framework\IO\Select;
use Framework\Provider\Type\CurlMethod;
use Framework\Provider\Type\OpenAIOutput;
use Framework\System\Config;
use Framework\Date\Timer;
use Framework\File\File;
use Framework\File\Storage;
use Framework\Utils\Dictionary;
use Framework\Utils\Strings;

use CURLStringFile;

/**
 * The OpenAI Provider
 */
class OpenAI {

    private const BaseUrl = "https://api.openai.com/v1";

    /** The Api Key to use in the next request, instead of the one in the config */
    private static string $tempApiKey = "";



    /**
     * Sets the Api Key to use in the next request, instead of the one in the config.
     * It is removed once that request is done, so it can not affect the other ones
     * @param string $apiKey
     * @return void
     */
    public static function setTempApiKey(string $apiKey): void {
        self::$tempApiKey = $apiKey;
    }

    /**
     * Returns the Api Key to use in the request, consuming the temporal one
     * @return string
     */
    private static function getApiKey(): string {
        if (self::$tempApiKey !== "") {
            $apiKey = self::$tempApiKey;
            self::$tempApiKey = "";
            return $apiKey;
        }
        return Config::getOpenAiKey();
    }



    /**
     * Does a GET Request
     * @param string                   $route
     * @param array<string,mixed>|null $request Optional.
     * @return Dictionary
     */
    private static function get(string $route, ?array $request = null): Dictionary {
        $response = Curl::execute(CurlMethod::GET, self::BaseUrl . $route, $request, [
            "Authorization" => "Bearer " . self::getApiKey(),
        ], jsonResponse: true);
        return new Dictionary($response);
    }

    /**
     * Does a POST Request
     * @param string                   $route
     * @param array<string,mixed>|null $request Optional.
     * @return Dictionary
     */
    private static function post(string $route, ?array $request = null): Dictionary {
        $response = Curl::execute(
            method:  CurlMethod::POST,
            url:     self::BaseUrl . $route,
            params:  $request,
            headers: [
                "Authorization" => "Bearer " . self::getApiKey(),
                "Content-Type"  => "application/json",
            ],
            jsonBody:     true,
            jsonResponse: true,
            returnError:  true,
        );
        return new Dictionary($response);
    }

    /**
     * Does an UPLOAD Request
     * @param string                   $route
     * @param array<string,mixed>|null $request Optional.
     * @return Dictionary
     */
    private static function upload(string $route, ?array $request = null): Dictionary {
        $response = Curl::execute(CurlMethod::POST, self::BaseUrl . $route, $request, [
            "Authorization" => "Bearer " . self::getApiKey(),
            "Content-Type"  => "multipart/form-data",
        ], jsonResponse: true);
        return new Dictionary($response);
    }

    /**
     * Does a DELETE Request
     * @param string                   $route
     * @param array<string,mixed>|null $request Optional.
     * @return Dictionary
     */
    private static function delete(string $route, ?array $request = null): Dictionary {
        $response = Curl::execute(CurlMethod::DELETE, self::BaseUrl . $route, $request, [
            "Authorization" => "Bearer " . self::getApiKey(),
            "Content-Type"  => "application/json",
        ], jsonResponse: true);
        return new Dictionary($response);
    }



    /**
     * Returns a Select of Models
     * @return list<Select>
     */
    public static function getModelSelect(): array {
        $response = self::get("/models");
        $result   = [];

        foreach ($response->getDict("data") as $model) {
            $name     = $model->getString("id");
            $result[] = new Select($name, $name);
        }
        return $result;
    }

    /**
     * Returns true if the given Model exists
     * @param string $model
     * @return bool
     */
    public static function modelExists(string $model): bool {
        if ($model === "") {
            return false;
        }

        $response = self::get("/models/$model");
        return $response->hasValue("id");
    }



    /**
     * Returns the allowed File Extensions
     * @return list<string>
     */
    public static function getFilExtensions(): array {
        return [
            "c", "cs", "cpp", "doc", "docx", "html", "java", "json",
            "md", "pdf", "php", "pptx", "py", "rb", "tex", "txt",
            "css", "js", "sh", "ts",
        ];
    }

    /**
     * Uploads the given File
     * @param string $fileName
     * @param string $fileContent
     * @return OpenAIOutput
     */
    public static function uploadFile(string $fileName, string $fileContent): OpenAIOutput {
        $response = self::upload("/files", [
            "purpose" => "assistants",
            "file"    => new CURLStringFile($fileContent, $fileName, "text/plain"),
        ]);
        return new OpenAIOutput($response);
    }

    /**
     * Deletes the given File
     * @param string $fileID
     * @return OpenAIOutput
     */
    public static function deleteFile(string $fileID): OpenAIOutput {
        $response = self::delete("/files/$fileID");
        return new OpenAIOutput($response);
    }

    /**
     * Retrieves the given File
     * @param string $fileID
     * @return OpenAIOutput
     */
    public static function getFile(string $fileID): OpenAIOutput {
        $response = self::get("/files/$fileID");
        return new OpenAIOutput($response);
    }



    /**
     * Lists all the Vector Store
     * @return Dictionary
     */
    public static function getAllVectorStores(): Dictionary {
        $response = self::get("/vector_stores");
        return $response->getDict("data");
    }

    /**
     * Creates a Vector Store
     * @param string $name
     * @return OpenAIOutput
     */
    public static function createVectorStore(string $name): OpenAIOutput {
        $response = self::post("/vector_stores", [
            "name" => $name,
        ]);
        return new OpenAIOutput($response);
    }

    /**
     * Edits a Vector Store
     * @param string $vectorStoreID
     * @param string $name
     * @return OpenAIOutput
     */
    public static function editVectorStore(string $vectorStoreID, string $name): OpenAIOutput {
        $response = self::post("/vector_stores/$vectorStoreID", [
            "name" => $name,
        ]);
        return new OpenAIOutput($response);
    }

    /**
     * Deletes a Vector Store
     * @param string $vectorStoreID
     * @return OpenAIOutput
     */
    public static function deleteVectorStore(string $vectorStoreID): OpenAIOutput {
        $response = self::delete("/vector_stores/$vectorStoreID");
        return new OpenAIOutput($response);
    }

    /**
     * Lists the Files of a Vector Store
     * @param string $vectorStoreID
     * @param bool   $all           Optional.
     * @return list<Dictionary>
     */
    public static function getVectorStoreFiles(
        string $vectorStoreID,
        bool $all = false,
    ): array {
        $result = [];
        $after  = "";

        do {
            $request = [ "limit" => 100 ];
            if ($after !== "") {
                $request["after"] = $after;
            }

            $response = self::get("/vector_stores/$vectorStoreID/files", $request);
            foreach ($response->getDict("data") as $elem) {
                $result[] = $elem;
            }

            $after = "";
            if ($response->getBool("has_more")) {
                $after = $response->getString("last_id");
            }
        } while ($after !== "" && $all);

        return $result;
    }

    /**
     * Creates a Vector Store File
     * @param string $vectorStoreID
     * @param string $fileID
     * @return OpenAIOutput
     */
    public static function createVectorFile(string $vectorStoreID, string $fileID): OpenAIOutput {
        $response = self::post("/vector_stores/$vectorStoreID/files", [
            "file_id" => $fileID,
        ]);
        return new OpenAIOutput($response);
    }

    /**
     * Retrieves a single Vector Store File
     * @param string $vectorStoreID
     * @param string $fileID
     * @return OpenAIOutput
     */
    public static function getVectorFile(string $vectorStoreID, string $fileID): OpenAIOutput {
        $response = self::get("/vector_stores/$vectorStoreID/files/$fileID");
        return new OpenAIOutput($response);
    }

    /**
     * Waits until a Vector Store File is fully processed
     * @param string $vectorStoreID
     * @param string $fileID
     * @param int    $maxAttempts   Optional.
     * @param int    $waitMs        Optional.
     * @return OpenAIOutput
     */
    public static function waitForVectorFile(
        string $vectorStoreID,
        string $fileID,
        int $maxAttempts = 30,
        int $waitMs = 500,
    ): OpenAIOutput {
        $result = new OpenAIOutput(new Dictionary([]));

        for ($i = 0; $i < $maxAttempts; $i += 1) {
            $result = self::getVectorFile($vectorStoreID, $fileID);
            if ($result->error !== "") {
                return $result;
            }

            $status = $result->response->getString("status");
            if ($status === "completed") {
                return $result;
            }
            if ($status === "failed" || $status === "cancelled") {
                $lastError = $result->response->getDict("last_error")->getString("message");
                if ($lastError !== "") {
                    $result->error = $lastError;
                } else {
                    $result->error = "The vector store file could not be processed.";
                }
                return $result;
            }

            usleep($waitMs * 1000);
        }

        $result->error = "Timed out while waiting for the vector store file to finish processing.";
        return $result;
    }

    /**
     * Deletes a Vector Store File
     * @param string $vectorStoreID
     * @param string $fileID
     * @return OpenAIOutput
     */
    public static function deleteVectorFile(string $vectorStoreID, string $fileID): OpenAIOutput {
        $response = self::delete("/vector_stores/$vectorStoreID/files/$fileID");
        return new OpenAIOutput($response);
    }



    /**
     * Creates a Completion and returns the Result
     * @param string                                  $model
     * @param string                                  $prompt
     * @param list<array{role:string,content:string}> $context          Optional.
     * @param Dictionary|null                         $schema           Optional.
     * @param bool                                    $removeReferences Optional.
     * @return OpenAIOutput
     */
    public static function createCompletion(
        string $model,
        string $prompt,
        array $context = [],
        ?Dictionary $schema = null,
        bool $removeReferences = true,
    ): OpenAIOutput {
        $timer  = new Timer();
        $params = [
            "model"    => $model,
            "messages" => array_merge($context, [
                [
                    "role"    => "user",
                    "content" => $prompt,
                ],
            ]),
        ];

        if ($schema !== null && $schema->isNotEmpty()) {
            $params["response_format"] = [
                "type"        => "json_schema",
                "json_schema" => [
                    "name"   => "Schema",
                    "strict" => true,
                    "schema" => [
                        "type"                 => "object",
                        "properties"           => $schema->toArray(),
                        "required"             => $schema->getKeys(),
                        "additionalProperties" => false,
                    ],
                ],
            ];
        }


        // Perform the Request
        $route    = "/chat/completions";
        $response = self::post($route, $params);
        $result   = new OpenAIOutput($response, url: self::BaseUrl, route: $route);

        // Check for errors
        if ($result->error !== "") {
            return $result;
        }

        // Generate the Output
        $choice = $response->getFirst("choices");
        if ($choice->isEmpty()) {
            return $result;
        }

        $text = $choice->getDict("message")->getString("content");
        if ($removeReferences) {
            $text = Strings::replacePattern($text, '/【.*】/', "");
        }

        $result->text         = $text;
        $result->inputTokens  = $response->getDict("usage")->getInt("prompt_tokens");
        $result->outputTokens = $response->getDict("usage")->getInt("completion_tokens");
        $result->runTime      = $timer->getElapsedSecondsInt();
        return $result;
    }

    /**
     * Returns the given Image as a base64 data url, to be sent in a Response
     * @param File $image
     * @return string
     */
    private static function getImageDataUrl(File $image): string {
        $filePath = $image->getTmpName();
        if ($filePath === "" || !Storage::fileExists($filePath)) {
            return "";
        }

        $content = Storage::readFile($filePath);
        if ($content === "") {
            return "";
        }

        $fileType = $image->getType();
        if ($fileType === "") {
            $fileType = "image/jpeg";
        }
        return "data:$fileType;base64," . base64_encode($content);
    }

    /**
     * Creates a Response and returns the Result
     * @param string                                  $model
     * @param string                                  $prompt
     * @param list<array{role:string,content:string}> $context          Optional.
     * @param Dictionary|null                         $schema           Optional.
     * @param list<File>                              $images           Optional.
     * @param string                                  $vectorStoreID    Optional.
     * @param bool                                    $allowWebSearch   Optional.
     * @param string                                  $allowedDomain    Optional.
     * @param bool                                    $store            Optional.
     * @param bool                                    $removeReferences Optional.
     * @param bool                                    $forceFileSearch  Optional.
     * @return OpenAIOutput
     */
    public static function createResponse(
        string $model,
        string $prompt,
        array $context = [],
        ?Dictionary $schema = null,
        array $images = [],
        string $vectorStoreID = "",
        bool $allowWebSearch = false,
        string $allowedDomain = "",
        bool $store = true,
        bool $removeReferences = true,
        bool $forceFileSearch = true,
    ): OpenAIOutput {
        $timer = new Timer();

        // With Images the content has to be sent as a list of parts
        $content = $prompt;
        if (count($images) > 0) {
            $content = [
                [
                    "type" => "input_text",
                    "text" => $prompt,
                ],
            ];
            foreach ($images as $image) {
                $dataUrl = self::getImageDataUrl($image);
                if ($dataUrl !== "") {
                    $content[] = [
                        "type"      => "input_image",
                        "image_url" => $dataUrl,
                    ];
                }
            }
        }

        $params = [
            "model" => $model,
            "store" => $store,
            "input" => array_merge($context, [
                [
                    "role"    => "user",
                    "content" => $content,
                ],
            ]),
        ];

        // Set the Schema
        if ($schema !== null && $schema->isNotEmpty()) {
            $params["text"] = [
                "format" => [
                    "type"   => "json_schema",
                    "name"   => "Schema",
                    "strict" => true,
                    "schema" => [
                        "type"                 => "object",
                        "properties"           => $schema->toArray(),
                        "required"             => $schema->getKeys(),
                        "additionalProperties" => false,
                    ],
                ],
            ];
        }

        // Parse the required tools
        $tools = [];
        if ($vectorStoreID !== "") {
            // Without forcing it, the model searches the files only when it needs them
            if ($forceFileSearch) {
                $params["tool_choice"] = "required";
            }
            $tools[] = [
                "type"             => "file_search",
                "vector_store_ids" => [ $vectorStoreID ],
            ];
        }
        if ($allowWebSearch) {
            $searchTool = [
                "type"                => "web_search",
                "search_context_size" => "medium",
                "external_web_access" => true,
            ];
            if ($allowedDomain !== "") {
                $searchTool["filters"] = [
                    "allowed_domains" => [ $allowedDomain ],
                ];
            }
            $tools[] = $searchTool;
        }
        if (count($tools) > 0) {
            $params["tools"] = $tools;
        }


        // Perform the Request
        $route    = "/responses";
        $response = self::post($route, $params);
        $result   = new OpenAIOutput($response, url: self::BaseUrl, route: $route);

        // Check for errors
        if ($result->error !== "") {
            return $result;
        }

        // Generate the Output
        $text = "";
        foreach ($response->getList("output") as $output) {
            if ($output->getString("type") === "message") {
                $text = $output->getFirst("content")->getString("text");
                break;
            }
        }
        if ($removeReferences) {
            $text = Strings::replacePattern($text, '/【.*?】/', "");
        }

        if ($text !== "") {
            $result->vectorStoreID = $vectorStoreID;
            $result->text          = $text;
            $result->inputTokens   = $response->getDict("usage")->getInt("input_tokens");
            $result->outputTokens  = $response->getDict("usage")->getInt("output_tokens");
            $result->runTime       = $timer->getElapsedSecondsInt();

            foreach ($response->getList("tools") as $tool) {
                $type = $tool->getString("type");
                if ($type  === "web_search" || $type  === "web_search_preview") {
                    $result->didSearchCall = true;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Transcribes an Audio
     * @param string $fileContent
     * @param string $fileName
     * @param string $language
     * @return OpenAIOutput
     */
    public static function transcribeAudio(
        string $fileContent,
        string $fileName,
        string $language,
    ): OpenAIOutput {
        $timer    = new Timer();
        $response = self::upload("/audio/transcriptions", [
            "file"            => new CURLStringFile($fileContent, $fileName),
            "model"           => "whisper-1",
            "language"        => $language,
            "response_format" => "verbose_json",
        ]);
        $result   = new OpenAIOutput($response);
        if (!$response->hasValue("text")) {
            return $result;
        }

        $outputTokens = 0;
        foreach ($response->getDict("segments") as $segment) {
            $outputTokens += count($segment->getArray("tokens"));
        }

        $result->text         = $response->getString("text");
        $result->language     = $response->getString("language");
        $result->duration     = (int)ceil($response->getFloat("duration"));
        $result->runTime      = $timer->getElapsedSecondsInt();
        $result->outputTokens = $outputTokens;
        return $result;
    }
}
