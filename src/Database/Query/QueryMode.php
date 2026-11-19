<?php
namespace Framework\Database\Query;

/**
 * The Query Modes
 */
enum QueryMode {

    case Select;
    case Insert;
    case InsertIgnore;
    case Replace;
    case Update;
    case Delete;
    case Truncate;
}
