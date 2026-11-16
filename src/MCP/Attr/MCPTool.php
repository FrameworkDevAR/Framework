<?php
namespace Framework\MCP\Attr;

use Framework\System\Access;

use Attribute;

/**
 * The MCP Tool Attribute
 */
#[Attribute(Attribute::TARGET_METHOD)]
class MCPTool {

    public Access $access;
    public string $description;
    public string $name;
    public string $title;

    public ?bool $readOnly;
    public ?bool $destructive;
    public ?bool $idempotent;
    public ?bool $openWorld;



    /**
     * The MCP Tool Attribute
     * @param Access    $access
     * @param string    $description Optional. The first line of the doc comment by default.
     * @param string    $name        Optional.
     * @param string    $title       Optional.
     * @param bool|null $readOnly    Optional.
     * @param bool|null $destructive Optional.
     * @param bool|null $idempotent  Optional.
     * @param bool|null $openWorld   Optional.
     */
    public function __construct(
        Access $access,
        string $description = "",
        string $name = "",
        string $title = "",
        ?bool $readOnly = null,
        ?bool $destructive = null,
        ?bool $idempotent = null,
        ?bool $openWorld = null,
    ) {
        $this->access      = $access;
        $this->description = $description;
        $this->name        = $name;
        $this->title       = $title;
        $this->readOnly    = $readOnly;
        $this->destructive = $destructive;
        $this->idempotent  = $idempotent;
        $this->openWorld   = $openWorld;
    }
}
