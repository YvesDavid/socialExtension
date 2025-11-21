<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificationNavbarController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository
    ) {}

    #[Route('/_notifications/navbar', name: 'app_notifications_navbar', methods: ['GET'])]
    public function navbar(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            // pas connecté : rien n'affiche
            return new Response('');
        }

        $uncheckedCount = $this->notificationRepository->count([
            'recipient' => $user,
            'checked'   => false,
        ]);

        $lastNotifications = $this->notificationRepository->createQueryBuilder('n')
            ->andWhere('n.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('notification/_navbar_icon.html.twig', [
            'unchecked_count' => $uncheckedCount,
            'notifications'   => $lastNotifications,
        ]);
    }
}
