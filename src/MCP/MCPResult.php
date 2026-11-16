<?php
namespace Framework\MCP;

use Framework\Utils\Dictionary;

/**
 * The MCP Result
 */
class MCPResult {

    public bool       $isError = false;
    public string     $error   = "";
    public Dictionary $data;



    /**
     * Creates a new MCPResult instance
     * @param Dictionary|null $data  Optional.
     * @param string          $error Optional.
     */
    public function __construct(?Dictionary $data = null, string $error = "") {
        $this->data    = $data ?? new Dictionary();
        $this->error   = $error;
        $this->isError = $error !== "";
    }
}
