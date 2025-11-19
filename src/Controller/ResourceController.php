<?php

// src/Controller/ResourceController.php
namespace App\Controller;

use App\Entity\SharedResource;
use App\Repository\SharedResourceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;



class ResourceController extends AbstractController
{
    public function __construct(
        private SharedResourceRepository $sharedResourceRepository,
        private EntityManagerInterface $em,
        private UserRepository $userRepository, 
    ) {}

    #[Route('/resources', name: 'app_resources_index')]
    public function index(Request $request): Response
    {
        $parentId = $request->query->get('parent');

        $parent = null;
        if ($parentId) {
            $parent = $this->sharedResourceRepository->find($parentId);
            if (!$parent) {
                throw $this->createNotFoundException('Dossier parent introuvable');
            }
        }

        // Contenu du dossier courant
        $criteria = ['parent' => $parent];
        $resources = $this->sharedResourceRepository->findBy($criteria, ['createdAt' => 'DESC']);

        // Racine des dossiers pour l’arborescence (colonne de gauche)
        $rootFolders = $this->sharedResourceRepository->findBy(
            ['parent' => null, 'resourceType' => 'folder'],
            ['title' => 'ASC']
        );

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
        $user = $this->userRepository->findOneBy(['email' => 'admin@test.com']); // provisoire, on mettra getUser() après la partie login
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $type = $request->request->get('resource_type'); // 'folder' ou 'file'
        $name = $request->request->get('name');
        $visibility = $request->request->get('visibility'); // 'public' ou 'private'
        $parentId = $request->request->get('parent_id');
        $fileKind = $request->request->get('file_kind'); // document|image|video|presentation

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
            $uploadDir = $projectDir . '/public/storage/uploads';

            $fs = new Filesystem();
            if (!$fs->exists($uploadDir)) {
                $fs->mkdir($uploadDir, 0775);
            }

            // Nom de fichier unique
            $safeName = bin2hex(random_bytes(8));
            $targetFilename = $safeName . '.' . $extension;

            // On récupère les infos AVANT le move
            $size = $uploadedFile->getSize() ?: 0;
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

        $this->addFlash('success', 'Ressource créée avec succès.');

        return $this->redirectToRoute('app_resources_index', [
            'parent' => $parentId ?: null,
        ]);
    }
}