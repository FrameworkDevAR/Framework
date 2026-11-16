<?php
namespace Framework\MCP\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Date\Date;

/**
 * The MCP Token Model
 */
#[Model(
    description:   "The tokens an app logs in to the MCP with, as a credential, stored hashed.",
    hasTimestamps: true,
    canCreate:     true,
)]
class MCPTokenModel {

    #[Field(isPrimary: true)]
    public string $accessToken = "";

    #[Field(isKey: true)]
    public string $refreshToken = "";

    #[Field]
    public string $clientID = "";

    #[Field(isKey: true)]
    public int $credentialID = 0;

    #[Field]
    public ?Date $expirationTime = null;

    #[Field]
    public ?Date $refreshTime = null;
}
