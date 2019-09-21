<?php
namespace Webkul\UVDesk\ApiBundle\Services;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\DomCrawler\Crawler;

class APIService
{
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

}
