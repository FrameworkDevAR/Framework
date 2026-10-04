<?php
namespace Tests\Analysis\Fixture\Notification;

class ChildNotification extends BaseNotification {

    public static string $description = "The send comes from its base";

    public static array $title = [
        "es" => "Hola",
        "en" => "Hello",
    ];

    public static array $message = [
        "es" => "Bienvenido",
        "en" => "Welcome",
    ];
}
