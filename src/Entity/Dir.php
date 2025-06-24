<?php

namespace App\Entity;

use App\Repository\DirRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ManyToOne;
use phpDocumentor\Reflection\Types\This;

#[ORM\Entity(repositoryClass: DirRepository::class)]
class Dir
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(unique: true, nullable: false)]
    private int $id;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 550, nullable: true)]
    private ?string $context = null;


    #[ManyToOne(targetEntity: Dir::class)]
    #[ORM\JoinColumn(name: 'dir_id', referencedColumnName: 'id', nullable: true)]
    private Dir|null $belong_to = null;

    #[ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'login', referencedColumnName: 'login', nullable: false)]
    private User $login;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getLogin(): User
    {
        return $this->login;
    }

    public function setLogin(User $login): static
    {
        $this->login = $login;

        return $this;
    }

    public function getBelongTo(): ?Dir
    {
        return $this->belong_to;
    }

    public function setBelongTo(?Dir $belong_to): static
    {
        $this->belong_to = $belong_to;

        return $this;
    }
}
