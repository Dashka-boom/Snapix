<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/config.php';

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class SnapixChatServer implements MessageComponentInterface
{
    /** @var SplObjectStorage<ConnectionInterface, array{user_id:int, chat_id:int}> */
    private SplObjectStorage $clients;

    public function __construct(private string $host, private int $port, private PDO $pdo)
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
            $messageId = (int) ($data['message_id'] ?? 0);

            if ($chatId <= 0 || $messageId <= 0) {
    $this->sendError($from, 'invalid_data');
    return;
}

            $message = $this->loadMessageForChat($chatId, $messageId);
            if ($message === null) {
                $this->sendError($from, 'message_not_found');
                return;
            }

            foreach ($this->clients as $client) {
                $clientData = $this->clients[$client] ?? ['chat_id' => 0, 'user_id' => 0];
                if ((int) ($clientData['chat_id'] ?? 0) === $chatId) {
                    $messageForClient = $message;
                    $messageForClient['is_mine'] = (int) ($clientData['user_id'] ?? 0) === (int) $message['sender_id'];
                    $payload = json_encode([
                        'type' => 'new_message',
                        'chat_id' => $chatId,
                        'message' => $messageForClient,
                    ], JSON_UNESCAPED_UNICODE);
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

    private function loadMessageForChat(int $chatId, int $messageId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, sender_id, message_text, created_at FROM messages WHERE id = :id AND chat_id = :chat_id LIMIT 1');
        $stmt->execute([
            'id' => $messageId,
            'chat_id' => $chatId,
        ]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$message) {
            return null;
        }

        return [
            'id' => (int) $message['id'],
            'sender_id' => (int) $message['sender_id'],
            'message_text' => (string) $message['message_text'],
            'created_at' => (string) $message['created_at'],
            'created_at_human' => date('d.m.Y H:i', strtotime((string) $message['created_at'])),

             'deleted_for_all' => false,
             'is_edited' => false,
             'reply_to' => null,
             'forwarded_from' => null,
             'shared_post' => null,
             'reactions' => [],
             'my_reaction' => null,
        ];
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
            new SnapixChatServer($host, $port, $pdo)
        )
    ),
    $port,
    $host
);

$server->run();
