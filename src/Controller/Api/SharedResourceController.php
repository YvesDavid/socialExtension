<?php

namespace App\Controller\Api;

use App\Entity\SharedResource;
use App\Entity\User;
use App\Repository\SharedResourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\UserRepository; // temporaire pour eviter l'identification
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


#[Route('/api/v1/resources')]
class SharedResourceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SharedResourceRepository $sharedResourceRepository,
        private UserRepository $userRepository // temporaire pour eviter l'identification

    ) {
    }

    #[Route('', name: 'api_resources_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // 🔹 Récupérer l'utilisateur connecté
        /** @var User|null $user */
       // $user = $this->getUser(); // temporaire pour eviter l'identification
        $user = $this->userRepository->findOneBy(['email' => 'john.doe@test.com']);
        if (!$user) {
            // Pour l’instant, tu peux temporairement bypass ça en récupérant John Doe depuis le repo User
            return $this->json(['error' => 'Authentication required'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        // ✅ Validation minimale
        $requiredFields = ['title', 'resource_type', 'path', 'mime_type', 'size', 'is_public'];
        foreach ($requiredFields as $field) {
            if (!\array_key_exists($field, $data)) {
                return $this->json(['error' => sprintf('Missing field: %s', $field)], 400);
            }
        }

        $resource = new SharedResource();
        $resource
            ->setTitle($data['title'])
            ->setDescription($data['description'] ?? null)
            ->setResourceType($data['resource_type'])
            ->setPath($data['path'])
            ->setMimeType($data['mime_type'])
            ->setSize((int) $data['size'])
            ->setIsPublic((bool) $data['is_public'])
            ->setCreator($user)
        ;

        if (\array_key_exists('metadata', $data) && $data['metadata'] !== null) {
            // On s'attend à un objet JSON → array PHP
            $resource->setMetadata($data['metadata']);
        }

        // 🔹 Gestion du parent si fourni
        if (!empty($data['parent_id'])) {
            $parent = $this->sharedResourceRepository->find($data['parent_id']);
            if (!$parent) {
                return $this->json(['error' => 'Parent resource not found'], 404);
            }

            // TODO: check permission to create inside this parent
            $resource->setParent($parent);
        }

        // Dates
        $now = new \DateTimeImmutable();
        $resource->setCreatedAt($now);
        $resource->setUpdatedAt($now);

        $this->em->persist($resource);
        $this->em->flush();

        return $this->json(
            [
                'id' => $resource->getId(),
                'title' => $resource->getTitle(),
                'message' => 'Resource created successfully',
            ],
            201
        );
    }

    #[Route('/{id}', name: 'api_resources_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $resource = $this->sharedResourceRepository->find($id);

        if (!$resource) {
            return $this->json(['error' => 'Resource not found'], 404);
        }

        // TODO: vérifier que l'utilisateur a le droit de voir cette ressource
        // (is_public ou accès explicite)

        $creator = $resource->getCreator();
        $parent = $resource->getParent();

        // Exemple simple, inspiré de ton contrat JSON
        $response = [
            'id' => $resource->getId(),
            'title' => $resource->getTitle(),
            'description' => $resource->getDescription(),
            'resource_type' => $resource->getResourceType(),
            'path' => $resource->getPath(),
            'mime_type' => $resource->getMimeType(),
            'size' => $resource->getSize(),
            'is_public' => $resource->isPublic(),
            'creator' => $creator ? [
                'id' => $creator->getId(),
                'firstname' => $creator->getFirstname(),
                'lastname' => $creator->getLastname(),
            ] : null,
            'parent' => $parent ? [
                'id' => $parent->getId(),
                'title' => $parent->getTitle(),
            ] : null,
            'metadata' => $resource->getMetadata(),
            'access_rights' => [
                // Pour l’instant en dur, on affinera avec un service de permissions
                'can_edit' => $user && $creator && $creator->getId() === $user->getId(),
                'can_delete' => $user && $creator && $creator->getId() === $user->getId(),
                'can_share' => $user && $creator && $creator->getId() === $user->getId(),
            ],
            'versions' => [], // Placeholder pour plus tard
        ];

        return $this->json($response);
    }
///////////////////////////DELETE DES RESSOURCES/////////////////////////////////////////

    #[Route('/{id}', name: 'api_resources_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        $resource = $this->sharedResourceRepository->find($id);

        if (!$resource) {
            return $this->json(['error' => 'Resource not found'], Response::HTTP_NOT_FOUND);
        }

        // Pour plus tard : vérif que l'utilisateur a le droit de supprimer
        // (créateur, admin, etc.)

        // Optionnel : lecture du body JSON
        $data = json_decode($request->getContent() ?: '{}', true);
        $force = $request->query->getBoolean('force', false);
        $deletionReason = $data['deletion_reason'] ?? null;
        $notifyUsers = $data['notify_users'] ?? false;

        // Pour l’instant, on ignore deletionReason & notifyUsers
        // mais tu peux les loguer si tu veux

        $this->em->remove($resource);
        $this->em->flush();

        return $this->json([
            'message' => 'Resource deleted successfully',
            'force' => $force,
            'deletion_reason' => $deletionReason,
            'notify_users' => $notifyUsers,
        ]);
    }

///////////////////////////DOWNLOAD DES RESSOURCES/////////////////////////////////////////


    #[Route('/{id}/download', name: 'api_resources_download', methods: ['GET'])]
    public function download(int $id): Response
    {
        $resource = $this->sharedResourceRepository->find($id);

        if (!$resource) {
            return $this->json(['error' => 'Resource not found'], Response::HTTP_NOT_FOUND);
        }

        // Pour l’instant on ne vérifie pas les permissions (is_public, etc.)

        // On reconstruit le chemin physique du fichier
        $relativePath = $resource->getPath(); // ex: "/storage/documents/schedule.pdf"
        $projectDir = $this->getParameter('kernel.project_dir');
        $filePath = $projectDir . '/public' . $relativePath;

        if (!file_exists($filePath)) {
            return $this->json(['error' => 'File not found on disk', 'path' => $filePath], 404);
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($filePath) // nom du fichier téléchargé
        );

        // Headers utiles
        $response->headers->set('Content-Type', $resource->getMimeType());
        $response->headers->set('Content-Length', (string) $resource->getSize());

        return $response;
    }

}
