<?php

namespace App\Controller;

use App\Entity\SharedResource;
use App\Entity\User;
use App\Repository\SharedResourceRepository;
use App\Repository\UserRepository;
use App\Service\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ResourceController extends AbstractController
{
    public function __construct(
        private SharedResourceRepository $sharedResourceRepository,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private NotificationManager $notificationManager
    ) {}

    #[Route('/resources', name: 'app_resources_index')]
    public function index(Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $parentId = $request->query->get('parent');
        $parent = null;
        if ($parentId) {
            $parent = $this->sharedResourceRepository->find($parentId);
            if (!$parent) {
                throw $this->createNotFoundException('Dossier parent introuvable');
            }
        }

        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);

        if ($isAdmin) {
            // admin : voit tout
            $resources = $this->sharedResourceRepository->findBy(
                ['parent' => $parent],
                ['createdAt' => 'DESC']
            );
        } else {
            // user simple : public + ses propres ressources
            $qb = $this->sharedResourceRepository->createQueryBuilder('r');
            
            if ($parent) {
                $qb->andWhere('r.parent = :parent')
                ->setParameter('parent', $parent);
            } else {
                $qb->andWhere('r.parent IS NULL');
            }

            $qb->andWhere('(r.isPublic = true OR r.creator = :user)')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC');

            $resources = $qb->getQuery()->getResult();
        }

        // Racine des dossiers pour l’arborescence (colonne de gauche)
        if ($isAdmin) {
            $rootFolders = $this->sharedResourceRepository->findBy(
                ['parent' => null, 'resourceType' => 'folder'],
                ['title' => 'ASC']
            );
        } else {
            $qbRoot = $this->sharedResourceRepository->createQueryBuilder('r')
                ->andWhere('r.parent IS NULL')
                ->andWhere('r.resourceType = :folder')
                ->setParameter('folder', 'folder')
                ->andWhere('r.isPublic = true OR r.creator = :user')
                ->setParameter('user', $user)
                ->orderBy('r.title', 'ASC');

            $rootFolders = $qbRoot->getQuery()->getResult();
        }

        return $this->render('resource/index.html.twig', [
            'resources'    => $resources,
            'parent'       => $parent,
            'root_folders' => $rootFolders,
        ]);
    }

    #[Route('/resources/{id}', name: 'app_resources_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $resource = $this->sharedResourceRepository->find($id);

        if (!$resource) {
            throw $this->createNotFoundException('Resource not found');
        }

        return $this->render('resource/show.html.twig', [
            'resource' => $resource,
        ]);
    }

    #[Route('/resources/new', name: 'app_resources_new', methods: ['POST'])]
    public function new(Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'You must be logged in to create resources.');
            return $this->redirectToRoute('app_login');
        }

        $type       = $request->request->get('resource_type'); // 'folder' ou 'file'
        $name       = $request->request->get('name');
        $visibility = $request->request->get('visibility');    // 'public' ou 'private'
        $parentId   = $request->request->get('parent_id');
        $fileKind   = $request->request->get('file_kind');     // document|image|video|presentation

        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('uploaded_file');

        if (!$name || !$type) {
            $this->addFlash('error', 'Name and type are required.');
            return $this->redirectToRoute('app_resources_index', [
                'parent' => $parentId ?: null,
            ]);
        }

        $resource = new SharedResource();
        $resource->setTitle($name);
        $resource->setDescription(null);
        $resource->setCreator($user);
        $resource->setCreatedAt(new \DateTimeImmutable());
        $resource->setUpdatedAt(new \DateTimeImmutable());
        $resource->setIsPublic($visibility === 'public');

        // Gestion du parent
        if (!empty($parentId)) {
            $parent = $this->sharedResourceRepository->find($parentId);
            if ($parent) {
                $resource->setParent($parent);
            }
        }

        if ($type === 'folder') {
            $resource->setResourceType('folder');
            $resource->setPath('/');
            $resource->setMimeType('inode/directory');
            $resource->setSize(0);
        } else {
            // FICHIER
            if (!$uploadedFile) {
                $this->addFlash('error', 'You must select a file.');
                return $this->redirectToRoute('app_resources_index', [
                    'parent' => $parentId ?: null,
                ]);
            }

            // Type logique obligatoire
            if (!\in_array($fileKind, ['document', 'image', 'video', 'presentation'], true)) {
                $this->addFlash('error', 'Invalid file kind.');
                return $this->redirectToRoute('app_resources_index', [
                    'parent' => $parentId ?: null,
                ]);
            }

            // Vérifier les extensions pour les images
            $originalName = $uploadedFile->getClientOriginalName();
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if ($fileKind === 'image') {
                $allowedImageExt = ['jpg', 'jpeg', 'png'];
                if (!\in_array($extension, $allowedImageExt, true)) {
                    $this->addFlash('error', 'Allowed image types: jpg, jpeg, png.');
                    return $this->redirectToRoute('app_resources_index', [
                        'parent' => $parentId ?: null,
                    ]);
                }
            }

            // Dossier de stockage
            $projectDir = $this->getParameter('kernel.project_dir');
            $uploadDir  = $projectDir . '/public/storage/uploads';

            $fs = new Filesystem();
            if (!$fs->exists($uploadDir)) {
                $fs->mkdir($uploadDir, 0775);
            }

            // Nom de fichier unique
            $safeName       = bin2hex(random_bytes(8));
            $targetFilename = $safeName . '.' . $extension;

            // On récupère les infos AVANT le move
            $size     = $uploadedFile->getSize() ?: 0;
            $mimeType = $uploadedFile->getClientMimeType() ?: 'application/octet-stream';

            // On déplace le fichier vers le dossier final
            $uploadedFile->move($uploadDir, $targetFilename);

            // Chemin relatif pour la BDD
            $relativePath = '/storage/uploads/' . $targetFilename;

            $resource->setResourceType($fileKind);
            $resource->setPath($relativePath);
            $resource->setMimeType($mimeType);
            $resource->setSize($size);
        }

        $this->em->persist($resource);
        $this->em->flush();

        // 🔔 Notification admin : création
        $admin = $this->getAdminUser();
        if ($admin && $admin !== $user) {
            $this->notificationManager->notify(
                type: 'resource_created',
                recipient: $admin,
                sender: $user,
                title: 'Nouvelle ressource créée',
                content: sprintf(
                    '%s %s a créé la ressource "%s" (%s) le %s.',
                    $user->getFirstname(),
                    $user->getLastname(),
                    $resource->getTitle(),
                    $resource->getResourceType(),
                    (new \DateTimeImmutable())->format('d/m/Y H:i')
                ),
                priority: 'medium',
                relationId: $resource->getId(),
                actionUrl: $this->generateUrl('app_resources_show', ['id' => $resource->getId()]),
                metadata: [
                    'resource_id'   => $resource->getId(),
                    'resource_type' => $resource->getResourceType(),
                    'title'         => $resource->getTitle(),
                ]
            );
        }

        $this->addFlash('success', 'Ressource créée avec succès.');

        return $this->redirectToRoute('app_resources_index', [
            'parent' => $parentId ?: null,
        ]);
    }

    #[Route('/resources/{id}/delete', name: 'app_resources_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $resource = $this->sharedResourceRepository->find($id);
        if (!$resource) {
            $this->addFlash('error', 'Ressource introuvable.');
            return $this->redirectToRoute('app_resources_index');
        }

        // On garde des infos AVANT la suppression
        $title     = $resource->getTitle();
        $type      = $resource->getResourceType();
        $deletedAt = new \DateTimeImmutable();
        $parent    = $resource->getParent();

        $this->em->remove($resource);
        $this->em->flush();

        // 🔔 Notification admin : suppression
        $admin = $this->getAdminUser();
        if ($admin && $admin !== $user) {
            $this->notificationManager->notify(
                type: 'resource_deleted',
                recipient: $admin,
                sender: $user,
                title: 'Ressource supprimée',
                content: sprintf(
                    'La ressource "%s" (%s) a été supprimée par %s %s le %s.',
                    $title,
                    $type,
                    $user->getFirstname(),
                    $user->getLastname(),
                    $deletedAt->format('d/m/Y H:i')
                ),
                priority: 'low',
                relationId: null,
                actionUrl: null,
                metadata: [
                    'title'         => $title,
                    'resource_type' => $type,
                    'deleted_at'    => $deletedAt->format(\DateTimeInterface::ATOM),
                ]
            );
        }

        $this->addFlash('success', 'Ressource supprimée avec succès.');

        return $this->redirectToRoute('app_resources_index', [
            'parent' => $parent?->getId(),
        ]);
    }

    /**
     * Retourne un admin (premier user qui possède ROLE_ADMIN)
     */
    private function getAdminUser(): ?User
    {
        // roles est stocké en JSON, on fait un LIKE simple : %\"ROLE_ADMIN\"%
        return $this->userRepository->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_ADMIN"%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
