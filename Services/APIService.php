<?php
namespace Webkul\UVDesk\ApiBundle\Services;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DependencyInjection\ContainerInterface;

class APIService
{
    public function __construct($userService)
    {
        // dump($winner);die('saurabh');
        $this->user_service = $userService;
    }
    /**
     * objectSerializer This function convert Entity object into json contenxt
     * @param Object $object Customer Entity object
     * @return JSON Customer JSON context
     */
    public function objectSerializer($object,$ignoredFileds = null) {
        $encoder = new JsonEncoder();

        $normalizer = new GetSetMethodNormalizer(null);
        $normalizer->setCircularReferenceHandler(function ($object) {
            return $object->getId();
        });

        $normalizer->setIgnoredAttributes($ignoredFileds);
        $serializer = new Serializer(array($normalizer), array($encoder));
        return $context = $serializer->serialize($object, 'json');
    }

    public function isAccessAuthorizedForMember($scope, $user)
    {
        try {
            $userRole = $user->getAgentInstance()->getSupportRole()->getCode();
        } catch (\Exception $error) {
            $userRole = '';
        }
        switch ($userRole) {
            
            case 'ROLE_SUPER_ADMIN':
            case 'ROLE_ADMIN':
                return true;
            case 'ROLE_AGENT':
                $agentPrivileges =  $this->user_service->getUserPrivileges($user->getAgentInstance()->getId());
                $agentPrivileges = array_merge($agentPrivileges, ['saved_filters_action', 'saved_replies']);
                return in_array($scope, $agentPrivileges) ? true : false;
            case 'ROLE_CUSTOMER':
            default:
                break;
        }

        return true;
    }
    

}
