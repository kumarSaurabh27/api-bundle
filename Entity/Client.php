<?php

namespace Webkul\UVDesk\ApiBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use FOS\OAuthServerBundle\Entity\Client as BaseClient;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface as UserInterface;
use FOS\OAuthServerBundle\Model\ClientInterface as ClientInterface;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Entity\ClientRepository")
 */
class Client extends BaseClient
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\OneToMany(targetEntity="Webkul\UVDesk\ApiBundle\Entity\AuthCode", mappedBy="client")
     */
    private $authCodes;

    /**
     * @ORM\OneToMany(targetEntity="Webkul\UVDesk\ApiBundle\Entity\RefreshToken", mappedBy="client")
     */
    private $refreshTokens;

    public function __construct()
    {
        $this->authCodes = new ArrayCollection();
        $this->refreshTokens = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection|AuthCode[]
     */
    public function getAuthCodes(): Collection
    {
        return $this->authCodes;
    }

    public function addAuthCode(AuthCode $authCode): self
    {
        if (!$this->authCodes->contains($authCode)) {
            $this->authCodes[] = $authCode;
            $authCode->setClient($this);
        }

        return $this;
    }

    public function removeAuthCode(AuthCode $authCode): self
    {
        if ($this->authCodes->contains($authCode)) {
            $this->authCodes->removeElement($authCode);
            // set the owning side to null (unless already changed)
            if ($authCode->getClient() === $this) {
                $authCode->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|RefreshToken[]
     */
    public function getRefreshTokens(): Collection
    {
        return $this->refreshTokens;
    }

    public function addRefreshToken(RefreshToken $refreshToken): self
    {
        if (!$this->refreshTokens->contains($refreshToken)) {
            $this->refreshTokens[] = $refreshToken;
            $refreshToken->setClient($this);
        }

        return $this;
    }

    public function removeRefreshToken(RefreshToken $refreshToken): self
    {
        if ($this->refreshTokens->contains($refreshToken)) {
            $this->refreshTokens->removeElement($refreshToken);
            // set the owning side to null (unless already changed)
            if ($refreshToken->getClient() === $this) {
                $refreshToken->setClient(null);
            }
        }

        return $this;
    }
}
