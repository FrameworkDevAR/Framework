<?php
namespace Tests\Provider;

use Framework\Core\Configs;
use Framework\Provider\SocketServer;

use Tests\TestHelpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

/**
 * The Socket Server, which collects the Topics of a Request and signs the Tokens
 *
 * The send is left out, as it needs a Socket Server to answer, and the send is
 * marked as registered before each test, so no test leaves one for the end of
 * the run.
 */
class SocketServerTest extends TestCase {
    use TestHelpers;

    /** @var mixed */
    private mixed $data = null;


    protected function setUp(): void {
        Configs::load();
        $this->data = $this->getPrivateStaticProperty(Configs::class, "data");

        $this->setPrivateStaticProperty(SocketServer::class, "isRegistered", true);
        SocketServer::takeTopics();
    }

    protected function tearDown(): void {
        $this->setPrivateStaticProperty(Configs::class, "data", $this->data);
        $this->setPrivateStaticProperty(SocketServer::class, "isRegistered", false);
        SocketServer::takeTopics();
        SocketServer::setSender("");
    }

    /**
     * Puts the Socket values in place of the ones of the repository
     * @param bool $isActive
     * @return void
     */
    private function useSocket(bool $isActive): void {
        $data = is_array($this->data) ? $this->data : [];
        $this->setPrivateStaticProperty(Configs::class, "data", array_merge($data, [
            "SOCKET_ACTIVE"      => $isActive,
            "SOCKET_SECRET"      => "a secret long enough for the signature",
            "SOCKET_TOKEN_HOURS" => 2,
        ]));
    }



    public function testATopicIsSentOnce(): void {
        $this->useSocket(true);

        SocketServer::add("a", "b", "a", "");
        SocketServer::add("b", "c");

        $this->assertSame([ "a", "b", "c" ], SocketServer::takeTopics());
        $this->assertSame([], SocketServer::takeTopics());
    }

    public function testNothingIsAddedWhenItIsNotActive(): void {
        $this->useSocket(false);

        SocketServer::add("a");

        $this->assertSame([], SocketServer::takeTopics());
    }

    public function testTheSenderIsKeptForTheSend(): void {
        SocketServer::setSender("a-tab");

        $this->assertSame("a-tab", $this->getPrivateStaticProperty(SocketServer::class, "sender"));
    }

    public function testTheTokenHasTheData(): void {
        $this->useSocket(true);

        $token = SocketServer::createToken("desktop", 45, [ "u45", "c6" ], 6);
        $data  = (array)JWT::decode($token, new Key("a secret long enough for the signature", "HS256"));

        $this->assertSame("desktop", $data["app"]);
        $this->assertSame(45, $data["id"]);
        $this->assertSame(6, $data["clientID"]);
        $this->assertSame([ "u45", "c6" ], $data["scopes"]);
        $this->assertSame(2 * 3600, $data["exp"] - $data["iat"]);
    }
}
