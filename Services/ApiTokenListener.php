<?php 
namespace Webkul\UVDesk\ApiBundle\Services;

use Webkul\UVDesk\ApiBundle\Entity\AccessToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use FOS\OAuthServerBundle\Security\Authentication\Token\OAuthToken;
use Webkul\UVDesk\CoreFrameworkBundle\Services\UserService;
use Symfony\Component\DependencyInjection\ContainerInterface;


class ApiTokenListener
{
    protected $userService;
    protected $em;
    protected $container;

    public function __construct(ContainerInterface $container, UserService $userService, EntityManagerInterface $em)
    {
        $this->userService = $userService;
        $this->container = $container;
        $this->em = $em;
    }

    public function checkToken() 
    {
        $response = false;
        if($this->container->get('security.token_storage')->getToken() instanceof OAuthToken) {
            $token = $this->container->get('security.token_storage')->getToken()->getToken();
            $accessToken = $this->em->getRepository('WebkulApiBundle:AccessToken')->findOneBy(array
                ('token' => $token));
            
            if($accessToken->getClient() && !$accessToken->getClient()->getCompany()) {
                return;
            }

            if($accessToken->getClient()->getCompany() !== $this->userService->getCurrentCompany()) {
                $json['error'] = 'invalid grant';
                $json['description'] = 'The access token provided is invalid.';
                $response = new JsonResponse($json, 401);
            }
        }

        return $response;
    }

    public function isThisApiRequest()
    {
        return $this->container->get('security.token_storage')->getToken()  && $this->container->get('security.token_storage')->getToken()  instanceOf OAuthToken;
    }    
}

?>