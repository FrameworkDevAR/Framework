<?php
namespace Framework\MCP\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;

/**
 * The MCP App Model
 */
#[Model(
    description:   "The apps that registered to connect to the MCP, with their urls.",
    hasTimestamps: true,
    canCreate:     true,
)]
class MCPAppModel {

    #[Field(isPrimary: true)]
    public string $clientID = "";

    #[Field]
    public string $name = "";

    #[Field(isText: true)]
    public string $redirectUris = "";
}
