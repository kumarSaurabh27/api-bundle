<?php

namespace Webkul\UVDesk\ApiBundle\Entity;

use UserBundle\Security\User;
use Symfony\Component\Security\Core\User\UserInterface;

trait ApiTokenTrait
{
    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $username;

    public function setUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(
                sprintf("User must be an instance of %s", User::class)
            );
        }

        $this->username = $user->getUsername();
        $this->user = $user;
    }

    public function getUser()
    {
        if (!$this->user) {
            throw new \RuntimeException(
                "Unable to get user - user was not loaded by postLoad"
            );
        }

        return $this->user;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }
}