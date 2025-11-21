<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

#[Route('/notifications', name: 'app_notifications_')]
class NotificationUiController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $em
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $notifications = $this->notificationRepository->createQueryBuilder('n')
            ->andWhere('n.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $uncheckedCount = $this->notificationRepository->count([
            'recipient' => $user,
            'checked'   => false,
        ]);

        return $this->render('notification/index.html.twig', [
            'notifications'   => $notifications,
            'unchecked_count' => $uncheckedCount,
        ]);
    }

    #[Route('/{id}/go', name: 'go', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function go(int $id): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        /** @var Notification|null $notification */
        $notification = $this->notificationRepository->find($id);
        if (!$notification || $notification->getRecipient()->getId() !== $user->getId()) {
            // pas trouvé ou pas pour cet utilisateur
            return $this->redirectToRoute('app_notifications_index');
        }

        // Marquer comme lue
        if (!$notification->isChecked()) {
            $notification->setChecked(true);
            $notification->setCheckedAt(new \DateTimeImmutable());
            $notification->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();
        }

        $targetUrl = $notification->getActionUrl();

        if ($targetUrl) {
            // on redirige vers l'URL stockée dans la notif (détail ressource, event, etc.)
            return $this->redirect($targetUrl);
        }

        // sinon on retourne sur la liste
        return $this->redirectToRoute('app_notifications_index');
    }

    #[Route('/clear-read', name: 'clear_read', methods: ['POST'])]
    public function clearRead(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Protection CSRF
        if (!$this->isCsrfTokenValid('clear_read_notifications', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_notifications_index');
        }

        // Récupérer toutes les notifications lues de cet utilisateur
        $readNotifications = $this->notificationRepository->createQueryBuilder('n')
            ->andWhere('n.recipient = :user')
            ->andWhere('n.checked = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        if (count($readNotifications) === 0) {
            $this->addFlash('info', 'Aucune notification lue à supprimer.');
            return $this->redirectToRoute('app_notifications_index');
        }

        foreach ($readNotifications as $notif) {
            $this->em->remove($notif);
        }

        $this->em->flush();

        $this->addFlash('success', 'Les notifications lues ont été supprimées.');

        return $this->redirectToRoute('app_notifications_index');
    }
}
