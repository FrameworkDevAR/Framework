<?php
namespace Tests\Notification;

use Framework\Notification\NotificationOutput;
use Framework\Notification\NotificationResult;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class NotificationOutputTest extends TestCase {

    /**
     * What a Provider answered, and the Output made from it
     * @param string             $externalID
     * @param string             $error
     * @param NotificationResult $expected
     * @return void
     */
    #[DataProvider("providerFromProvider")]
    public function testTheResultComesFromTheIDOfTheProvider(
        string $externalID,
        string $error,
        NotificationResult $expected,
    ): void {
        $output = NotificationOutput::fromProvider($externalID, $error);

        $this->assertSame($expected, $output->result);
        $this->assertSame($externalID, $output->externalID);
        $this->assertSame($error, $output->error);
    }

    /**
     * @return array<string,array{string,string,NotificationResult}>
     */
    public static function providerFromProvider(): array {
        return [
            "it was taken"              => [ "an-id", "", NotificationResult::Sent ],
            "some devices were refused" => [ "an-id", "Invalid device", NotificationResult::Sent ],
            "it was refused"            => [ "", "Not subscribed", NotificationResult::ProviderError ],
            "there was no answer"       => [ "", "", NotificationResult::ProviderError ],
        ];
    }

    public function testASentOneKeepsItsIDAndTheErrorsOfItsDevices(): void {
        $output = NotificationOutput::sent("an-id", "Invalid device");

        $this->assertSame(NotificationResult::Sent, $output->result);
        $this->assertSame("an-id", $output->externalID);
        $this->assertSame("Invalid device", $output->error);
    }

    public function testAFailedOneKeepsItsError(): void {
        $output = NotificationOutput::failed("Not subscribed");

        $this->assertSame(NotificationResult::ProviderError, $output->result);
        $this->assertSame("", $output->externalID);
        $this->assertSame("Not subscribed", $output->error);
    }

    public function testAFailedOneWithNoErrorHadNoResponse(): void {
        $output = NotificationOutput::failed();

        $this->assertSame(NotificationResult::ProviderError, $output->result);
        $this->assertSame("No response", $output->error);
    }

    public function testANewOneHoldsNothing(): void {
        $output = new NotificationOutput();

        $this->assertSame(NotificationResult::None, $output->result);
        $this->assertSame("", $output->externalID);
        $this->assertSame("", $output->error);
    }
}
