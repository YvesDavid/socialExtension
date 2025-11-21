<?php

namespace App\Controller\Api;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/notifications', name: 'api_notifications_')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $em
    ) {}

    /**
     * Créer une notification
     *
     * POST /api/v1/notifications
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['type'], $data['title'], $data['content'], $data['priority'], $data['recipient_id'])) {
            return $this->json(['error' => 'Missing fields'], 400);
        }

        $recipient = $this->userRepo->find($data['recipient_id']);
        if (!$recipient) {
            return $this->json(['error' => 'Recipient not found'], 404);
        }

        $sender = null;
        if (!empty($data['sender_id'])) {
            $sender = $this->userRepo->find($data['sender_id']);
        }

        $notification = new Notification();
        $notification->setType($data['type']);
        $notification->setTitle($data['title']);
        $notification->setContent($data['content']);
        $notification->setPriority($data['priority']);
        $notification->setRecipient($recipient);
        $notification->setSender($sender);

        if (!empty($data['relation_id'])) {
            $notification->setRelationId($data['relation_id']);
        }

        if (!empty($data['action_url'])) {
            $notification->setActionUrl($data['action_url']);
        }

        if (!empty($data['expires_at'])) {
            $notification->setExpiresAt(new \DateTime($data['expires_at']));
        }

        if (!empty($data['metadata'])) {
            $notification->setMetadata($data['metadata']);
        }

        // checked = false par défaut dans ton constructeur normalement
        $this->em->persist($notification);
        $this->em->flush();

        return $this->json([
            'id'           => $notification->getId(),
            'type'         => $notification->getType(),
            'title'        => $notification->getTitle(),
            'content'      => $notification->getContent(),
            'priority'     => $notification->getPriority(),
            'recipient_id' => $recipient->getId(),
            'sender_id'    => $sender?->getId(),
            'checked'      => $notification->isChecked(),
            'created_at'   => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], 201);
    }

    /**
     * Lister les notifications du user connecté
     *
     * GET /api/v1/notifications
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        $checked  = $request->query->get('checked');   // "true"/"false" ou null
        $type     = $request->query->get('type');
        $priority = $request->query->get('priority');
        $page     = max(1, (int) $request->query->get('page', 1));
        $limit    = max(1, (int) $request->query->get('limit', 10));
        $offset   = ($page - 1) * $limit;

        $qb = $this->notificationRepo->createQueryBuilder('n')
            ->andWhere('n.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC');

        if ($checked !== null) {
            $qb->andWhere('n.checked = :checked')
               ->setParameter('checked', filter_var($checked, FILTER_VALIDATE_BOOLEAN));
        }

        if ($type) {
            $qb->andWhere('n.type = :type')
               ->setParameter('type', $type);
        }

        if ($priority) {
            $qb->andWhere('n.priority = :priority')
               ->setParameter('priority', $priority);
        }

        // total
        $qbCount = clone $qb;
        $total = (int) $qbCount->select('COUNT(n.id)')->getQuery()->getSingleScalarResult();

        $list = $qb->setFirstResult($offset)
                   ->setMaxResults($limit)
                   ->getQuery()
                   ->getResult();

        // nombre de notifications non checkées
        $unchecked = $this->notificationRepo->count([
            'recipient' => $user,
            'checked'   => false,
        ]);

        $data = array_map(function (Notification $n) {
            return [
                'id'         => $n->getId(),
                'type'       => $n->getType(),
                'title'      => $n->getTitle(),
                'content'    => $n->getContent(),
                'priority'   => $n->getPriority(),
                'checked'    => $n->isChecked(),
                'created_at' => $n->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $list);

        return $this->json([
            'total'     => $total,
            'unchecked' => $unchecked,
            'page'      => $page,
            'limit'     => $limit,
            'data'      => $data,
        ]);
    }

    /**
     * Marquer une notification comme checkée (lue)
     *
     * PUT /api/v1/notifications/{id}/read
     */
    #[Route('/{id}/read', name: 'mark_checked', methods: ['PUT'])]
    public function markChecked(int $id): JsonResponse
    {
        $notification = $this->notificationRepo->find($id);
        if (!$notification) {
            return $this->json(['error' => 'Notification not found'], 404);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user || $user->getId() !== $notification->getRecipient()->getId()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $notification->setChecked(true);
        $notification->setCheckedAt(new \DateTimeImmutable());
        $notification->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $this->json([
            'id'          => $notification->getId(),
            'checked'     => $notification->isChecked(),
            'checked_at'  => $notification->getCheckedAt()?->format(\DateTimeInterface::ATOM),
            'updated_at'  => $notification->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
