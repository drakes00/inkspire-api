<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use newrelic\DistributedTracePayload;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
// On définit ici tous les end points manipulant la classe USer. Cela va permettre de classifier les end points en User.
#[ApiResource(operations: [
    new Post(routeName: 'user_signup', name: 'sign_up'),
    new Post(routeName: 'user_signin', name: 'sign_in'),
    new Post(routeName: 'user_deco', name: 'deco')
])]
class User
{
    #[ORM\Id]
    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(
        message: "The login is empty"
    )]
    private ?string $login = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    private ?string $password = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $token = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function setLogin(string $login): static
    {
        $this->login = $login;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): static
    {
        $this->token = $token;

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
}
