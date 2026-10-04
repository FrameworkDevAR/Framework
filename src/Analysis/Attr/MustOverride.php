<?php
namespace Framework\Analysis\Attr;

use Attribute;

/**
 * The Must Override Attribute
 *
 * A method or a property of a base that every class below it has to write
 * for itself. The one in the base is what a class gets when it forgets to,
 * which is why it can not be left to be found at runtime.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
class MustOverride {
}
