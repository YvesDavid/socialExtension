<?php

namespace App\Entity;

use App\Repository\ResourceAccessRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResourceAccessRepository::class)]
class ResourceAccess
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $accessType = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $grantedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: SharedResource::class, inversedBy: 'accessRules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SharedResource $resource = null;

    #[ORM\ManyToOne(inversedBy: 'resourceAccesses')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'resourceAccessesGranted')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $grantedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccessType(): ?string
    {
        return $this->accessType;
    }

    public function setAccessType(string $accessType): static
    {
        $this->accessType = $accessType;

        return $this;
    }

    public function getGrantedAt(): ?\DateTimeImmutable
    {
        return $this->grantedAt;
    }

    public function setGrantedAt(\DateTimeImmutable $grantedAt): static
    {
        $this->grantedAt = $grantedAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

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

    public function getResource(): ?SharedResource
    {
        return $this->resource;
    }

    public function setResource(?SharedResource $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getGrantedBy(): ?User
    {
        return $this->grantedBy;
    }

    public function setGrantedBy(?User $grantedBy): static
    {
        $this->grantedBy = $grantedBy;

        return $this;
    }
}
