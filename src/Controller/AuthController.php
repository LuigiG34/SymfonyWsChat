<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['username']) || !isset($data['password'])) {
            return $this->json(['error' => 'Username and password required'], Response::HTTP_BAD_REQUEST);
        }

        // Check if user already exists
        $existingUser = $em->getRepository(User::class)->findOneBy(['username' => $data['username']]);
        if ($existingUser) {
            return $this->json(['error' => 'Username already taken'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setUsername($data['username']);
        $user->setRoles(['ROLE_USER']);
        
        // Hash the password
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Validate the entity
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($user);
        $em->flush();

        return $this->json([
            'message' => 'User registered successfully',
            'username' => $user->getUsername()
        ], Response::HTTP_CREATED);
    }

    /**
     * Login is handled by JsonAuthenticator
     * This route just needs to exist for the authenticator to trigger
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // This will never be reached because JsonAuthenticator handles the response
        // But we need this route to exist
        return $this->json(['error' => 'This should not be reached'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        // Symfony's logout handler will handle this
        return $this->json(['message' => 'Logged out successfully']);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        // Get the currently authenticated user
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles()
        ]);
    }

    #[Route('/api/mercure-token', name: 'api_mercure_token', methods: ['GET'])]
    public function getMercureToken(\App\Service\MercureTokenGenerator $tokenGenerator): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $tokenGenerator->generateToken($user->getUserIdentifier());

        return $this->json(['token' => $token]);
    }
}