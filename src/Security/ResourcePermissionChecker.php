<?php
// Service pour centraliser les règles de permissions

namespace App\Security;

use App\Entity\SharedResource;
use App\Entity\ResourceAccess;
use App\Entity\User;
use App\Repository\ResourceAccessRepository;

class ResourcePermissionChecker
{
    public function __construct(
        private ResourceAccessRepository $accessRepo
    ) {}

    public function canView(SharedResource $resource, ?User $user): bool
    {
        if ($resource->isPublic()) {
            return true;
        }

        if (!$user) {
            return false;
        }

        // Super admin global
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Creator = admin de la ressource
        if ($resource->getCreator() && $resource->getCreator()->getId() === $user->getId()) {
            return true;
        }

        // Chercher un droit dans ResourceAccess (view/edit/admin)
        $access = $this->accessRepo->findOneBy([
            'resource' => $resource,
            'user'     => $user,
        ]);

        if (!$access) {
            return false;
        }

        return in_array($access->getAccessType(), ['view', 'edit', 'admin'], true);
    }

    public function canEdit(SharedResource $resource, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        if ($resource->getCreator() && $resource->getCreator()->getId() === $user->getId()) {
            return true;
        }

        $access = $this->accessRepo->findOneBy([
            'resource' => $resource,
            'user'     => $user,
        ]);

        if (!$access) {
            return false;
        }

        return in_array($access->getAccessType(), ['edit', 'admin'], true);
    }

    public function canAdmin(SharedResource $resource, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return $resource->getCreator() && $resource->getCreator()->getId() === $user->getId();
    }
}
