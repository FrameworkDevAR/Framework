<?php
namespace Tests\Analysis\Fixture\Email;

use Framework\Email\EmailMessage;
use Framework\System\Template;

abstract class TemplateBaseEmail extends EmailMessage {

    public static ?Template $template = Template::EmailCode;
}
