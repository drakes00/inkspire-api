<?php

namespace App\Entity;

use App\Repository\FileRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[ORM\Entity(repositoryClass: FileRepository::class)]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(unique: true, nullable: false)]
    private int $id;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 100000, nullable: true)]
    private ?string $content = null;

    #[ManyToOne(targetEntity: User::class, fetch: "EAGER")]
    #[ORM\JoinColumn(name: 'login', referencedColumnName: 'login', nullable: false)]
    private User $login;

    #[ManyToOne(targetEntity: Dir::class, fetch: "EAGER")]
    #[ORM\JoinColumn(name: 'dir_id', referencedColumnName: 'id', nullable: true)]
    private Dir|null $belong_to = null;

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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

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

    public function getIdDir(): ?int
    {
        return $this->id_dir;
    }

    public function setIdDir(?int $id_dir): static
    {
        $this->id_dir = $id_dir;

        return $this;
    }

    public function getBelongTo(): ?Dir
    {
        return $this->belong_to;
    }

    public function setBelongTo(?Dir $belong_to): void
    {
        $this->belong_to = $belong_to;
    }
}
