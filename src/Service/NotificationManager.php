<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationManager
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function notify(
        string $type,
        User $recipient,
        ?User $sender,
        string $title,
        string $content,
        string $priority = 'medium',
        ?int $relationId = null,
        ?string $actionUrl = null,
        ?array $metadata = null,
        ?\DateTimeInterface $expiresAt = null
    ): Notification {
        $notification = new Notification();

        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setContent($content);
        $notification->setPriority($priority);
        $notification->setRecipient($recipient);
        $notification->setSender($sender);
        $notification->setChecked(false);

        if ($relationId !== null) {
            $notification->setRelationId($relationId);
        }
        if ($actionUrl !== null) {
            $notification->setActionUrl($actionUrl);
        }
        if ($metadata !== null) {
            $notification->setMetadata($metadata);
        }
        if ($expiresAt !== null) {
            $notification->setExpiresAt($expiresAt);
        }

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }
}
