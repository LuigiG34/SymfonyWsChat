<?php

namespace App\Command;


use App\WebSocket\ChatServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\SocketServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


#[AsCommand(name: 'app:ws:serve', description: 'Start the WebSocket server')]
class StartWsServerCommand extends Command
{
    public function __construct(private readonly ChatServer $chat)
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>WebSocket server listening on 0.0.0.0:8080</info>');


        $loop = LoopFactory::create();
        $socket = new SocketServer('0.0.0.0:8080', [], $loop);


        new IoServer(
            new HttpServer(new WsServer($this->chat)),
            $socket,
            $loop
        );


        $loop->run();
        return Command::SUCCESS;
    }
}
