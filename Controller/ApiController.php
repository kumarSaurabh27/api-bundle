<?php

namespace Webkul\UVDesk\ApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webkul\UserBundle\Controller\BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ApiController extends Controller
{
    /* lifetime of access token */ 
    protected $lifetime = 5184000; //3600*24*31*2 i.e. 2months

    private function getClient() 
    {
        $repository = $this->getDoctrine()->getRepository('UVDeskApiBundle:Client');
        $result = $repository->createQueryBuilder('c')
            ->getQuery()->getOneOrNullResult();

        return $result;
    }

    private function getAcessTokensByUserId($userId) 
    {
        $repository = $this->getDoctrine()->getRepository('UVDeskApiBundle:AccessToken');
        $result = $repository->createQueryBuilder('a')
            ->where('a.user = :userId')
            ->andWhere('a.client = :client')
            ->setParameter('userId', $userId)
            ->setParameter('client', $this->getClient())
            ->getQuery()->getArrayResult();

        return $result;
    }

    public function accessTokenXhrAction(Request $request)
    {
        $json = [];
        $user = $this->getCurrentUser();
        if($user && $request->isXmlHttpRequest()) {
            $req = $request->request->all()?:json_decode($request->getContent(),true);
            $data = $this->get('fos_oauth_server.access_token_manager');

            if($request->getMethod() == 'GET') {
                $json = $this->getAcessTokensByUserId($user->getId());
            } else if($request->getMethod() == 'POST') {
                $client = $this->getClient();

                if(!$client) {
                    // create a client for api
                    $kernel = $this->get('kernel');
                    $application = new Application($kernel);
                    $application->setAutoExit(false);
                    $messages = 10;
                    $input = new ArrayInput(array(
                    'command' => 'oauth-server:client:create',
                    '--redirect-uri' =>['/'],
                    '--grant-type' => ["authorization_code", "password", "refresh_token"],
                    '--company-id' => $company,
                    ));
                    // You can use NullOutput() if you don't need the output
                    $output = new BufferedOutput();
                    $application->run($input, $output);
                    $content = $output->fetch();
                    $clientData = json_decode($content, true);
                    $client = $this->getClient();
                }
                if(isset($req['name']) && ($reqName = $req['name'])) {
                    $token = $data->createToken();
                    $token->setName($reqName);
                    $token->setUser($user);
                    $token->setExpiresAt(time() + $this->lifetime);
                    $token->setClient($client);
                    $accessTokenString = strtoupper(md5(time()) . rand(10000,99999) . md5(time()));
                    $token->setToken($accessTokenString);
                    $token->setScope('API');

                    $data->updateToken($token);

                    $json['alertClass'] = 'success';
                    $json['alertMessage'] = $this->get('translator')->trans('Success! access token added successfully');
                    $json['id'] = $token->getId();
                    $json['expiresAt'] = $token->getExpiresAt();
                    $json['token'] = $token->getToken();
                } else {
                    $json['alertClass'] = 'danger';
                    $json['alertMessage'] = $this->get('translator')->trans('Error! provide a token name.');
                    $statusCode = Response::HTTP_BAD_REQUEST;
                }

            } elseif($request->getMethod() == 'DELETE') {
                $id = $request->attributes->get('id');
                if(isset($id)) {
                    $token = $data->findTokenBy([
                        'id' => $id,
                        'user' => $this->getCurrentUser(),
                        ]);

                    if($token) {
                        $data->deleteToken($token);
                        $json['alertClass'] = 'success';
                        $json['alertMessage'] = $this->get('translator')->trans('Success! access token deleted successfully');
                    } else {
                        $error = true;
                    }
                } else {
                    $error = true;
                }                
            } elseif($request->getMethod() == 'PUT') {
                if(isset($req['name']) && ($name = $req['name'])) {
                    $token = isset($req['token'])?$data->findTokenByToken($req['token']):null;
                    if($token) {
                        $token->setName($name);
                        $data->updateToken($token);
                        $json['alertClass'] = 'success';
                        $json['alertMessage'] = $this->get('translator')->trans('Success! access token updated successfully');
                    } else {
                        $json['alertClass'] = 'danger';
                        $json['alertMessage'] = $this->get('translator')->trans('Error! invalid token');    
                        $statusCode = Response::HTTP_BAD_REQUEST;
                    }
                } else {
                    $json['alertClass'] = 'danger';
                    $json['alertMessage'] = $this->get('translator')->trans('Error! provide a token name.');
                    $statusCode = Response::HTTP_BAD_REQUEST;
                }

            } elseif($request->getMethod() == 'PATCH') {
                if(isset($req['token'])) {
                    $token = $data->findTokenByToken($req['token']);
                    if($token) {
                        $token->setExpiresAt(time() + $this->lifetime);
                        $data->updateToken($token);
                        $json['expiresAt'] = $token->getExpiresAt();
                        $json['alertClass'] = 'success';
                        $json['alertMessage'] = $this->get('translator')->trans('Success! access token refreshed successfully');
                    } else {
                        $error = true;
                    }
                } else {
                    $error = true;
                }
            }

        } else {
            $this->noResultFound();
        }
        
        if(isset($error) && $error) {
            $json['alertClass'] = 'danger';
            $json['alertMessage'] = $this->get('translator')->trans('Error! invalid token');    
            $statusCode = Response::HTTP_BAD_REQUEST;
        }

        if(isset($statusCode)) {
            return new JsonResponse($json, $statusCode);
        } else {
            return new JsonResponse($json);
        }
    }

    public function clientViewAction(Request $request)
    {
		$this->isAuthorized('ROLE_ADMIN');

		return $this->render('UVDeskApiBundle:Default:apiClientView.html.twig', [
            'list_items' => $this->getListItems($request),
            'information_items' => $this->getRightSidebarInfoItems($request),
		]);
    }

    public function clientXhrAction(Request $request)
    {
        $json = [];
        if($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            if($request->getMethod() == 'GET') {
                $client = $this->getClient();
                if($client) {
                    $json['id'] = $client->getId();
                    $json['clientId'] = $client->getPublicId();
                    $json['secret'] = $client->getSecret();
                    $json['redirectUris'] = $client->getRedirectUris();
                    $json['allowedGrantTypes'] = $client->getAllowedGrantTypes();
                }
            } else if($request->getMethod() == 'POST' || $request->getMethod() == 'PUT') {
                $data = json_decode($request->getContent(),true);
                if(!$data || !isset($data['grant-type[]']) || !isset($data['redirect-uri[]']) ) {
                    $json['alertClass'] = 'danger';
                    $json['alertMessage'] = $this->get('translator')->trans('Error: Provide redirect-uri, grant-type');
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST); 
                }
                if(gettype($data['redirect-uri[]']) === 'string') {
                    $data['redirect-uri[]'] = array(0 => $data['redirect-uri[]']);
                }
                if(gettype($data['grant-type[]']) === 'string') {
                    $data['grant-type[]'] = array(0 => $data['grant-type[]']);
                }

                $client = $this->getClient();

                if($request->attributes->get('id')) {
                    if(!$client) {
                        $this->noResultFound();
                    } else {
                        $em = $this->getDoctrine()->getManager();
                        $client->setRedirectUris($data['redirect-uri[]']);
                        $client->setAllowedGrantTypes($data['grant-type[]']);
                        $em->persist($client);
                        $em->flush();
                        $json['alertClass'] = 'success';
                        $json['alertMessage'] = $this->get('translator')->trans('Success: Client updated successfully');
                        $json['id'] = $client->getId();
                        $json['clientId'] = $client->getPublicId();
                        $json['secret'] = $client->getSecret();
                        $json['redirectUris'] = $client->getRedirectUris();
                        $json['allowedGrantTypes'] = $client->getAllowedGrantTypes();
                    }
                } else {
                    if($client) {
                        $json['alertClass'] = 'danger';
                        $json['alertMessage'] = $this->get('translator')->trans('Error: Client already Exists');
                        return new JsonResponse($json, Response::HTTP_CONFLICT);
                    }
                    $kernel = $this->get('kernel');
                    $application = new Application($kernel);
                    $application->setAutoExit(false);
                    $messages = 10;
                    $input = new ArrayInput(array(
                       'command' => 'oauth-server:client:create',
                       '--redirect-uri' => $data['redirect-uri[]'],
                       '--grant-type' => $data['grant-type[]'],
                       '--company-id' => $this->getCurrentCompany(),
                    ));
                    // You can use NullOutput() if you don't need the output
                    $output = new BufferedOutput();
                    $application->run($input, $output);

                    // return the output, don't use if you used NullOutput()
                    $content = $output->fetch();
                    $json = json_decode($content, true);
                    $json['alertClass'] = 'success';
                    $json['alertMessage'] = $this->get('translator')->trans('Success: Client added successfully');
                }
            } else {
                $this->noResultFound();
            }           
        } else {
            $this->noResultFound();
        }

        return new JsonResponse($json);
    }

    protected function cleanExpiredToken()
    {
        $kernel = $this->get('kernel');
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $messages = 10;
        $input = new ArrayInput(array(
           'command' => 'fos:oauth-server:clean'
        ));
        // You can use NullOutput() if you don't need the output
        $output = new BufferedOutput();
        $application->run($input, $output);

        // return the output, don't use if you used NullOutput()
        $content = $output->fetch();
        // return new Response(""), if you used NullOutput()
        $json['alertClass'] = 'success';
        $json['alertMessage'] = $this->get('translator')->trans('Success: Expired tokens cleaned successfully');
        // return new Response($content);
    }

}
