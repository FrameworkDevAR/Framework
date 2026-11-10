<?php
namespace Framework\Database\Model;

use Attribute;

/**
 * The Model Attribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Model {

    public string $fantasyName;
    public string $description;

    public bool $hasUsers;
    public bool $hasTimestamps;
    public bool $hasEnv;
    public bool $canCreate;
    public bool $canEdit;
    public bool $canDelete;
    public bool $skipList;



    /**
     * The Model Attribute
     * @param string $fantasyName   Optional.
     * @param string $description   Optional.
     * @param bool   $hasUsers      Optional.
     * @param bool   $hasTimestamps Optional.
     * @param bool   $hasEnv        Optional.
     * @param bool   $canCreate     Optional.
     * @param bool   $canEdit       Optional.
     * @param bool   $canDelete     Optional.
     * @param bool   $skipList      Optional.
     */
    public function __construct(
        string $fantasyName = "",
        string $description = "",
        bool $hasUsers = false,
        bool $hasTimestamps = false,
        bool $hasEnv = false,
        bool $canCreate = false,
        bool $canEdit = false,
        bool $canDelete = false,
        bool $skipList = false,
    ) {
        $this->fantasyName   = $fantasyName;
        $this->description   = $description;
        $this->hasUsers      = $hasUsers;
        $this->hasTimestamps = $hasTimestamps;
        $this->hasEnv        = $hasEnv;
        $this->canCreate     = $canCreate;
        $this->canEdit       = $canEdit;
        $this->canDelete     = $canDelete;
        $this->skipList      = $skipList;
    }
}
