<?php

namespace Webkul\UVDesk\ApiBundle\Controller\Ticket;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Webkul\UserBundle\Entity;
use Webkul\TicketBundle\Entity\Ticket;
use Webkul\TicketBundle\Entity\Thread;
use Webkul\TicketBundle\Entity\Draft;
use Webkul\TicketBundle\Entity\TicketLabel;
use Webkul\TicketBundle\Entity\Tag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webkul\UserBundle\Controller\BaseController;
use Webkul\TicketBundle\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Webkul\DefaultBundle\Utils\RateLimit\Annotations\ControllerUsagePolicy;

class TicketApiController extends Controller
{

    /**
     * Return a Collection of Tickets.
     * By Default a collection can have at most 15 ticket records with pagination data.
     * Method: GET
     * Example: <pre> GET /api/tickets.json?status=1&page=2 </pre>
     * Example2: <pre> GET /api/tickets.json?trashed&sort=t.id&direction=asc </pre>
     *
     * Api Doc: (
     *  resource=true,
     *  section="Ticket",
     *  description="Return a Collection of Tickets",
     *  filters={
     *      {"name"="new"},
     *      {"name"="unassigned"},
     *      {"name"="mine"},
     *      {"name"="starred", "dataType"="int"},
     *      {"name"="trashed"},
     *      {"name"="label", "dataType"="int","description"="labelId"},
     *      {"name"="status", "dataType"="int","description"="statusId"},
     *      {"name"="agent", "dataType"="int","description"="agentId"},
     *      {"name"="customer", "dataType"="int","description"="customerId"},
     *      {"name"="priority", "dataType"="int","description"="priorityId"},
     *      {"name"="group", "dataType"="int","description"="groupId"},
     *      {"name"="team", "dataType"="int","description"="teamId"},
     *      {"name"="tags", "dataType"="int","description"="tagId"},
     *      {"name"="mailbox", "dataType"="int","description"="mailboxId"},
     *      {"name"="sort", "dataType"="string", "pattern"="(t.id|t.updatedAt|agentName|c.email|name) ASC|DESC", "default"="t.id"},
     *      {"name"="page", "dataType"="int", "default"="1"},
     *      {"name"="search", "dataType"="string", "description"="search for Ticket"},
     *      {"name"="actAsType", "dataType"="string", "description"="admin can actAs customer, options: customer, agent"},
     *      {"name"="actAsEmail", "dataType"="string", "description"="email of acted user"}
     *  }
     * )
     * @param Object  "HTTP Request object" 
     * @return JSON "JSON response"
     */
    public function ticketListRestAction(Request $request)
    {
        if($response = $this->get('api.token.service')->checkToken()) {
            return $response;
        }

        $json = [];
        $ticketRepository = $this->getDoctrine()->getRepository('UVDeskCoreFrameworkBundle:Ticket');
        $userRepository = $this->getDoctrine()->getRepository('UVDeskCoreFrameworkBundle:User');

        $em = $this->getDoctrine()->getManager();
        
        $isAdmin = in_array($this->getUser()->getRoles(), ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']);

        if($isAdmin && $request->query->get('actAsType')) {
            switch($request->query->get('actAsType')) {
                case 'customer': 
                    $user = $em->getRepository(':User')
                          ->findOneBy(
                                array('email' => $request->query->get('actAsEmail'))
                            );
                    if($user) {
                        $json = $repository->getAllCustomerTickets($request->query, $this->container, $user);
                    } else {
                        $json['error'] = 'Error! Resource not found.';
                        return new JsonResponse($json, Response::HTTP_NOT_FOUND);
                    }
                    return new JsonResponse($json);
                    break;
                case 'agent':
                    $company = $this->get('user.service')->getCurrentCompany();
                    $user = $this->get('api.user.service')->getAgentByCompanyAndEmail($company, $request->query->get('actAsEmail'));
                    if($user) {
                        $request->query->set('agent', $user->getId());
                    } else {
                        $json['error'] = 'Error! Resource not found.';
                        return new JsonResponse($json, Response::HTTP_NOT_FOUND);
                    }
                    break;
                default:
                    $json['error'] = 'Error! invalid actAs details.';
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                    break;
            }
        }

        $json = $ticketRepository->getAllTickets($request->query, $this->container);
        $json['userDetails'] = [
                        'user' => $this->getUser()->getId(),
                        'name' => $this->getUser()->getDetail()['agent']->getFirstname().' '.$this->getUser()->getDetail()['agent']->getLastname(),
                        'pic' => $this->getUser()->smallThumbnail,
                        'role' => $isAdmin ? : (in_array('ROLE_AGENT_AGENT_KICK', $this->get('user.service')->getAgentPrivilege($this->getUser()->getId()))),
                        ];
        // $json['agents'] = $this->get('user.service')->getAgentsPartialDetails();
        // $json['status'] = $this->get('ticket.service')->getStatus();
        $json['group'] = $userRepository->getSupportGroups(); 
        $json['team'] =  $userRepository->getSupportTeams();
        // $json['priority'] = $this->get('ticket.service')->getPriorities();
        // $json['type'] = $this->get('ticket.service')->getTypes();
        // $json['source'] = $this->get('ticket.service')->getSources();
        if($isAdmin) {
            $json['mailbox'] = $this->get('ticket.service')->getMailboxes();
        }

        return new JsonResponse($json);
    }
}