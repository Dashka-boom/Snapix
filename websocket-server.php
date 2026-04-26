<?php
require __DIR__ . '/vendor/autoload.php';

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class SnapixChatServer implements MessageComponentInterface
{
    /** @var SplObjectStorage<ConnectionInterface, array{user_id:int, chat_id:int}> */
    private SplObjectStorage $clients;

    public function __construct(private string $host, private int $port)
    {
        $this->clients = new SplObjectStorage();
        echo "Snapix WebSocket server started on ws://{$this->host}:{$this->port}\n";
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn, [
            'user_id' => 0,
            'chat_id' => 0,
        ]);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode((string) $msg, true);
        if (!is_array($data)) {
            $this->sendError($from, 'invalid_json');
            return;
        }

        $type = (string) ($data['type'] ?? '');

        if ($type === 'auth') {
            $userId = (int) ($data['user_id'] ?? 0);
            $chatId = (int) ($data['chat_id'] ?? 0);

            if ($userId <= 0) {
                $this->sendError($from, 'auth_required');
                return;
            }

            $this->clients[$from] = [
                'user_id' => $userId,
                'chat_id' => $chatId,
            ];

            $from->send(json_encode([
                'type' => 'auth_ok',
                'user_id' => $userId,
                'chat_id' => $chatId,
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        if ($type === 'new_message') {
            $sender = $this->clients[$from] ?? ['user_id' => 0, 'chat_id' => 0];
            $chatId = (int) ($data['chat_id'] ?? $sender['chat_id'] ?? 0);

            if ($chatId <= 0 || (int) ($sender['user_id'] ?? 0) <= 0) {
                $this->sendError($from, 'not_authenticated');
                return;
            }

            $payload = json_encode([
                'type' => 'new_message',
                'chat_id' => $chatId,
                'sender_id' => (int) $sender['user_id'],
                'message_id' => (int) ($data['message_id'] ?? 0),
            ], JSON_UNESCAPED_UNICODE);

            foreach ($this->clients as $client) {
                $clientData = $this->clients[$client] ?? ['chat_id' => 0];
                if ((int) ($clientData['chat_id'] ?? 0) === $chatId) {
                    $client->send($payload);
                }
            }
            return;
        }

        $this->sendError($from, 'unknown_type');
    }

    public function onClose(ConnectionInterface $conn): void
    {
        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
        }
    }

    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        echo "WebSocket error: {$e->getMessage()}\n";
        $conn->close();
    }

    private function sendError(ConnectionInterface $conn, string $error): void
    {
        $conn->send(json_encode([
            'type' => 'error',
            'error' => $error,
        ], JSON_UNESCAPED_UNICODE));
    }
}

$host = getenv('SNAPIX_WS_HOST') ?: '127.0.0.1';
$port = (int) (getenv('SNAPIX_WS_PORT') ?: 8090);
if ($port <= 0) {
    $port = 8090;
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new SnapixChatServer($host, $port)
        )
    ),
    $port,
    $host
);

$server->run();
