<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    #[Route('/api/users/search', name: 'api_users_search', methods: ['GET'])]
    public function searchUsers(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $currentUser = $this->getUser();
        $query = $request->query->get('q', '');
        
        $users = $em->getRepository(User::class)
            ->searchByUsername($query, $currentUser->getUserIdentifier());

        $usersData = array_map(fn($user) => ['username' => $user->getUsername()], $users);

        return $this->json($usersData);
    }

    #[Route('/api/messages/send', name: 'api_messages_send', methods: ['POST'])]
    public function sendMessage(
        Request $request,
        EntityManagerInterface $em,
        HubInterface $hub
    ): JsonResponse {
        $currentUser = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['receiver']) || !isset($data['content'])) {
            return $this->json(['error' => 'Receiver and content required'], Response::HTTP_BAD_REQUEST);
        }

        // Get the sender from the repository (the actual User entity)
        $sender = $em->getRepository(User::class)->findOneBy(['username' => $currentUser->getUserIdentifier()]);
        $receiver = $em->getRepository(User::class)->findOneBy(['username' => $data['receiver']]);

        if (!$receiver) {
            return $this->json(['error' => 'Receiver not found'], Response::HTTP_NOT_FOUND);
        }

        $message = new Message();
        $message->setSender($sender);
        $message->setReceiver($receiver);
        $message->setContent($data['content']);
        $message->setSentAt(new \DateTimeImmutable());

        $em->persist($message);
        $em->flush();

        // Publish to Mercure for real-time delivery
        $update = new Update(
            'chat/' . $receiver->getUsername(),
            json_encode([
                'id' => $message->getId(),
                'sender' => $sender->getUsername(),
                'receiver' => $receiver->getUsername(),
                'content' => $message->getContent(),
                'sentAt' => $message->getSentAt()->format('Y-m-d H:i:s')
            ])
        );

        $hub->publish($update);

        return $this->json([
            'message' => 'Message sent',
            'id' => $message->getId(),
            'sentAt' => $message->getSentAt()->format('Y-m-d H:i:s')
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/messages/{username}', name: 'api_messages_history', methods: ['GET'])]
    public function getMessages(
        string $username,
        EntityManagerInterface $em
    ): JsonResponse {
        $currentUser = $this->getUser();
        
        // Get actual User entities from repository
        $currentUserEntity = $em->getRepository(User::class)->findOneBy(['username' => $currentUser->getUserIdentifier()]);
        $otherUser = $em->getRepository(User::class)->findOneBy(['username' => $username]);

        if (!$otherUser) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Use repository method instead of inline query
        $messages = $em->getRepository(Message::class)
            ->findConversation($currentUserEntity, $otherUser);

        $messagesData = array_map(fn($msg) => [
            'id' => $msg->getId(),
            'sender' => $msg->getSender()->getUsername(),
            'receiver' => $msg->getReceiver()->getUsername(),
            'content' => $msg->getContent(),
            'sentAt' => $msg->getSentAt()->format('Y-m-d H:i:s')
        ], $messages);

        return $this->json($messagesData);
    }
}