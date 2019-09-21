<?php

namespace Webkul\UVDesk\ApiBundle\Security\Guards;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webkul\UVDesk\ApiBundle\Utils\UVDeskException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class APIGuard extends AbstractGuardAuthenticator
{
    private $firewall;
    private $container;

    public function __construct(ContainerInterface $container, FirewallMap $firewall)
	{
        $this->firewall = $firewall;
		$this->container = $container;
	}

    /**
     * Check whether this guard is applicable for the current request.
     */
    public function supports(Request $request)
    {
        return 'OPTIONS' != $request->getRealMethod() && 'uvdesk_api' === $this->firewall->getFirewallConfig($request)->getName();
    }

    /**
     * Retrieve and prepare credentials from the request.
     */
    public function getCredentials(Request $request)
    {
        $credentials = ['email' => null, 'auth_token' => null];

        if (strpos(strtolower($request->headers->get('Authorization')), 'basic') === 0) {
            $authorization_key = substr($request->headers->get('Authorization'), 6);
            try {
                list($email, $auth_token) = explode(':', base64_decode($authorization_key));
                return ['email' => $email, 'auth_token' => $auth_token];
            } catch (\Exception $e) {
                dump($e->getMessage()); die;
            }
        }
        return $credentials;
    }

    /**
     * Retrieve the current user on behalf of which the request is being performed.
     */
    public function getUser($credentials, UserProviderInterface $provider)
    {
        return $provider->loadUserByUsername($credentials['email']);
    }

    /**
     * Process the provided credentials and check whether the current request is properly authenticated.
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        return (!empty($credentials['auth_token']) && $this->container->getParameter('uvdesk.api.auth_token') === $credentials['auth_token']);
    }

    /**
     * Disable support for the "remember me" functionality.
     */
    public function supportsRememberMe()
    {
        return false;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        switch ($exception->getMessageKey()) {
            case 'Username could not be found.':
                $data = [
                    'status' => false,
                    'message' => 'No such user found',
                    'error_code' => UVDeskException::USER_NOT_FOUND,
                ];
                break;
            case 'Invalid Credentials.':
                $data = [
                    'status' => false,
                    'message' => 'Invalid credentials provided.',
                    'error_code' => UVDeskException::INVALID_CREDNETIALS,
                ];
                break;
            default:
                $data = [
                    'status' => false,
                    'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
                    'error_code' => UVDeskException::UNEXPECTED_ERROR,
                ];
                break;
        }

        return new JsonResponse($data, Response::HTTP_FORBIDDEN);
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        $data = [
            'status' => false,
            'message' => 'Authentication Required',
            'error_code' => UVDeskException::API_NOT_AUTHENTICATED,
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
