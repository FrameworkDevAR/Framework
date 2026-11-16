<?php
namespace Framework\MCP\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Date\Date;

/**
 * The MCP Grant Model
 */
#[Model(
    description:   "The requests to connect an app to the MCP, with the code once it is allowed.",
    hasTimestamps: true,
    canCreate:     true,
    canEdit:       true,
)]
class MCPGrantModel {

    #[Field(isPrimary: true)]
    public string $requestID = "";

    #[Field(isKey: true)]
    public string $code = "";

    #[Field]
    public string $clientID = "";

    #[Field]
    public int $credentialID = 0;

    #[Field(isText: true)]
    public string $redirectUri = "";

    #[Field(isText: true)]
    public string $state = "";

    #[Field]
    public string $codeChallenge = "";

    #[Field]
    public string $scope = "";

    #[Field]
    public ?Date $expirationTime = null;
}
