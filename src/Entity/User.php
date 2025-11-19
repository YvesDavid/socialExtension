<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $firstname = null;

    #[ORM\Column(length: 50)]
    private ?string $lastname = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $birthdate = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAT = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, SharedResource>
     */
    #[ORM\OneToMany(targetEntity: SharedResource::class, mappedBy: 'creator')]
    private Collection $sharedResources;

    /**
     * @var Collection<int, ResourceAccess>
     */
    #[ORM\OneToMany(targetEntity: ResourceAccess::class, mappedBy: 'user')]
    private Collection $resourceAccesses;

    /**
     * @var Collection<int, ResourceAccess>
     */
    #[ORM\OneToMany(targetEntity: ResourceAccess::class, mappedBy: 'grantedBy')]
    private Collection $resourceAccessesGranted;

    public function __construct()
    {
        $this->sharedResources = new ArrayCollection();
        $this->resourceAccesses = new ArrayCollection();
        $this->resourceAccessesGranted = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getBirthdate(): ?\DateTimeInterface
    {
        return $this->birthdate;
    }

    public function setBirthdate(\DateTimeInterface $birthdate): static
    {
        $this->birthdate = $birthdate;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function getCreatedAT(): ?\DateTimeImmutable
    {
        return $this->createdAT;
    }

    public function setCreatedAT(\DateTimeImmutable $createdAT): static
    {
        $this->createdAT = $createdAT;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, SharedResource>
     */
    public function getSharedResources(): Collection
    {
        return $this->sharedResources;
    }

    public function addSharedResource(SharedResource $sharedResource): static
    {
        if (!$this->sharedResources->contains($sharedResource)) {
            $this->sharedResources->add($sharedResource);
            $sharedResource->setCreator($this);
        }

        return $this;
    }

    public function removeSharedResource(SharedResource $sharedResource): static
    {
        if ($this->sharedResources->removeElement($sharedResource)) {
            // set the owning side to null (unless already changed)
            if ($sharedResource->getCreator() === $this) {
                $sharedResource->setCreator(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ResourceAccess>
     */
    public function getResourceAccesses(): Collection
    {
        return $this->resourceAccesses;
    }

    public function addResourceAccess(ResourceAccess $resourceAccess): static
    {
        if (!$this->resourceAccesses->contains($resourceAccess)) {
            $this->resourceAccesses->add($resourceAccess);
            $resourceAccess->setUser($this);
        }

        return $this;
    }

    public function removeResourceAccess(ResourceAccess $resourceAccess): static
    {
        if ($this->resourceAccesses->removeElement($resourceAccess)) {
            if ($resourceAccess->getUser() === $this) {
                $resourceAccess->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ResourceAccess>
     */
    public function getResourceAccessesGranted(): Collection
    {
        return $this->resourceAccessesGranted;
    }

    public function addResourceAccessesGranted(ResourceAccess $resourceAccessesGranted): static
    {
        if (!$this->resourceAccessesGranted->contains($resourceAccessesGranted)) {
            $this->resourceAccessesGranted->add($resourceAccessesGranted);
            $resourceAccessesGranted->setGrantedBy($this);
        }

        return $this;
    }

    public function removeResourceAccessesGranted(ResourceAccess $resourceAccessesGranted): static
    {
        if ($this->resourceAccessesGranted->removeElement($resourceAccessesGranted)) {
            if ($resourceAccessesGranted->getGrantedBy() === $this) {
                $resourceAccessesGranted->setGrantedBy(null);
            }
        }

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        if (empty($roles)) {
            $roles[] = 'ROLE_USER';
        }
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // si tu stockes du plain text temporaire, efface-le ici
    }

}


