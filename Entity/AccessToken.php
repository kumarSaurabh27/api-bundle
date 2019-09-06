<?php

namespace Webkul\UVDesk\ApiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOS\OAuthServerBundle\Entity\AccessToken as BaseAccessToken;
use Symfony\Component\Security\Core\User\UserInterface as UserInterface;
use FOS\OAuthServerBundle\Model\ClientInterface as ClientInterface;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Entity\AccessTokenRepository")
 */
class AccessToken extends BaseAccessToken
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Webkul\UVDesk\CoreFrameworkBundle\Entity\User")
     */
    protected $user;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $name;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
