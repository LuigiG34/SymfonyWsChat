<?php

namespace App\WebSocket;


use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;


final class ChatServer implements MessageComponentInterface
{
    /** @var \SplObjectStorage<ConnectionInterface, array{room:string,user:string}> */
    private \SplObjectStorage $clients;


    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }


    public function onOpen(ConnectionInterface $conn): void
    {
        $query = $conn->httpRequest?->getUri()?->getQuery() ?? '';
        parse_str($query, $p);


        $room = isset($p['room']) && $p['room'] !== '' ? (string)$p['room'] : 'general';
        $user = isset($p['user']) && $p['user'] !== '' ? (string)$p['user'] : ('anon-' . substr(bin2hex(random_bytes(3)), 0, 6));


        $this->clients->attach($conn, ['room' => $room, 'user' => $user]);
        $conn->send(json_encode(['type' => 'system', 'message' => "joined room {$room} as {$user}"]));
    }


    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $meta = $this->clients[$from] ?? ['room' => 'general', 'user' => 'anon'];


        $payload = json_decode((string)$msg, true);
        $text = is_array($payload) && isset($payload['text'])
            ? trim((string)$payload['text'])
            : trim((string)$msg);
        if ($text === '') return;


        $out = json_encode([
            'type' => 'message',
            'room' => $meta['room'],
            'user' => $meta['user'],
            'text' => $text,
            'ts' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);


        foreach ($this->clients as $client) {
            $m = $this->clients[$client];
            if ($m['room'] === $meta['room']) {
                $client->send($out);
            }
        }
    }


    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
    }


    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $conn->send(json_encode(['type' => 'error', 'message' => $e->getMessage()]));
        $conn->close();
    }
}
