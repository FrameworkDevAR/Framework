<?php
namespace Tests\Analysis\Fixture\Base;

use Framework\Analysis\Attr\MustOverride;

class MustOverridePropertyBase {

    #[MustOverride]
    public static string $label = "";

    public static string $title = "";
}
