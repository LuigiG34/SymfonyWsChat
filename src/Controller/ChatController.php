<?php

namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class ChatController extends AbstractController
{
    #[Route('/chat', name: 'chat')]
    public function chat(): Response
    {
        return $this->render('chat.html.twig');
    }
}
