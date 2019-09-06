<?php

namespace Webkul\UVDesk\ApiBundle\Entity;

use FOS\OAuthServerBundle\Entity\AuthCode as BaseAuthCode;
use Doctrine\ORM\Mapping as ORM;
use Webkul\UVDesk\CoreFrameworkBundle\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface as UserInterface;
use FOS\OAuthServerBundle\Model\ClientInterface as ClientInterface;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Entity\AuthCodeRepository")
 */
class AuthCode extends BaseAuthCode
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
     * @ORM\ManyToOne(targetEntity="Webkul\UVDesk\ApiBundle\Entity\Client", inversedBy="authCodes")
     */
    protected $client;

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

    public function getClient(): ?ClientInterface
    {
        return $this->client;
    }

    public function setClient(?ClientInterface $client): self
    {
        $this->client = $client;

        return $this;
    }
}
