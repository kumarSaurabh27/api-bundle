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
use Webkul\UVDesk\ApiBundle\Utils\UVDeskException;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webkul\UVDesk\CoreFrameworkBundle\Workflow\Events as CoreWorkflowEvents;


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
        $json = [];
        $ticketRepository = $this->getDoctrine()->getRepository('UVDeskCoreFrameworkBundle:Ticket');
        $userRepository = $this->getDoctrine()->getRepository('UVDeskCoreFrameworkBundle:User');

        $em = $this->getDoctrine()->getManager();
        // $isAdmin = in_array($this->getUser()->getRoles(), ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']);
        // dump(($this->getUser()->getUserInstance())); die;

        //if($isAdmin && $request->query->get('actAsType')) {
        if($request->query->get('actAsType')) {    
            switch($request->query->get('actAsType')) {
                case 'customer': 
                    $user = $this->getUser();
                    if($user) {
                        $json = $repository->getAllCustomerTickets($request->query, $this->container, $user);
                    } else {
                        $json['error'] = 'Error! Resource not found.';
                        return new JsonResponse($json, Response::HTTP_NOT_FOUND);
                    }
                    return new JsonResponse($json);
                    break;
                case 'agent':
                    $user = $this->getUser();
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
                        'name' => $this->getUser()->getFirstName().' '.$this->getUser()->getLastname(),
                        //'role' => $isAdmin ? : (in_array('ROLE_AGENT_AGENT_KICK', $this->get('user.service')->getAgentPrivilege($this->getUser()->getId()))),
                        ];
        $json['agents'] = $this->get('user.service')->getAgentsPartialDetails();
        $json['status'] = $this->get('ticket.service')->getStatus();
        $json['group'] = $userRepository->getSupportGroups(); 
        $json['team'] =  $userRepository->getSupportTeams();
        $json['priority'] = $this->get('ticket.service')->getPriorities();
        $json['type'] = $this->get('ticket.service')->getTypes();
        $json['source'] = $this->get('ticket.service')->getAllSources();
        // if($isAdmin) {
        //     $json['mailbox'] = $this->get('ticket.service')->getMailboxes();
        // }

        return new JsonResponse($json);
    }



    public function viewTicketRestAction(Request $request) 
    {
        $ticketId = $request->attributes->get('ticketId');

        $em = $this->getDoctrine()->getManager();
        $ticket = $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->findOneBy(array('id' => $ticketId));     
        $userRepository = $this->getDoctrine()->getRepository('UVDeskCoreFrameworkBundle:User');

        if(!$ticket) {
            $json['error'] = $this->translate('Error! resource not found');
            return new JsonResponse($json, Response::HTTP_NOT_FOUND);
        }
        // $this->denyAccessUnlessGranted('VIEW', $ticket);

        // if(!$ticket->getIsAgentView()) {
        //     $ticket->setIsAgentView(1);
        //     $em->persist($ticket);
        //     $em->flush();
        // }
        
        // $labels = array(
        //                 'predefind' => $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->getPredefindLabelDetails($this->container),
        //                 'custom' => $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->getCustomLabelDetails($this->container)
        //             );
        // $todoList = $this->container->get('ticket.service')->getTicketTodoById($ticket->getIncrementId());
        
        //$ignoredFileds = array('password','createdAt', 'updatedAt', 'users','salt','sessionId','token','facebook','twitter','company','groups','tmpMarks','path','profilePic','contactNumber','roles','userName','jobTitle','privileges','agents','threads','absolutePath','accountNonExpired','accountNonLocked','credentialsNonExpired','customerTickets','isEmailPending','validationCode','completeImageUrl','webPath','todos','userRoles','drafts','data','ticket','userSaveReplies','ticketValues', 'activityNotifications', 'dateAdded', 'dateUpdated');
        $ignoredFileds = array('password');
        $userDetails = [
                        'user' => $this->getUser()->getId(),
                        'name' => $this->getUser()->getFirstName().' '.$this->getUser()->getLastname(),
                        ];


        $ticketObj = $ticket;
        // $ticket = json_decode($this->container->get('api.service')->objectSerializer($ticket, $ignoredFileds),true);
        // $ticket['createdAt'] = $ticketObj->getCreatedAt();
        // $ticket['updatedAt'] = $ticketObj->getUpdatedAt();
   
     

        return new JsonResponse([
            'ticket' => $ticket,
            // 'labels' => $labels,
            //'todo' => $todoList,
            'userDetails' => $userDetails,
            //'createThread' => $this->get('ticket.service')->getCreateReply($ticket['id']),
            // 'ticketTotalThreads' => $this->get('ticket.service')->getTicketTotalThreads($ticket['id']),
            'status' => $this->get('ticket.service')->getStatus(),
            'group' => $userRepository->getSupportGroups(),
            'team' => $userRepository->getSupportTeams(),
            'priority' => $this->get('ticket.service')->getPriorities(),
            'type' => $this->get('ticket.service')->getTypes(),
        ]);
    }

    /**
     * move ticket to Trash by given id
     * @param Object  "HTTP Request object with json request in Content" 
     * @return JSON "JSON response"
     */
    public function deleteTicketRestAction(Request $request)
    {
        // try {
        //     $this->isAuthorized('ROLE_AGENT_DELETE_TICKET');
        // } catch(AccessDeniedException $e) {
        //     $json['error'] = $this->get('translator')->trans('Error! Access Denied');
        //     $json['description'] = $this->get('translator')->trans('You are not authorized to perform this Action.');
        //     return new JsonResponse($json, Response::HTTP_UNAUTHORIZED);
        // }

        $ticketId = $request->attributes->get('ticketId');
        $em = $this->getDoctrine()->getManager();
        $ticket = $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->find($ticketId);

        if(!$ticket) {
            $json['error'] = $this->translate('Error! resource not found');
            return new JsonResponse($json, Response::HTTP_NOT_FOUND);
        }
        // $this->denyAccessUnlessGranted('VIEW', $ticket);
        if(!$ticket->getIsTrashed()) {
            $ticket->setIsTrashed(1);
            $em->persist($ticket);
            $em->flush();       

            // Trigger ticket delete event
            $event = new GenericEvent(CoreWorkflowEvents\Ticket\Delete::getId(), [
                'entity' => $ticket,
            ]);

            $json['message'] = 'Success ! Ticket moved to trash successfully.';
            $statusCode = Response::HTTP_OK;
        } else {
            $json['error'] = 'Warning ! Ticket is already in trash.';
            $statusCode = Response::HTTP_BAD_REQUEST;
        }
        
        return new JsonResponse($json, $statusCode);
    }

    public function assignAgentRestAction(Request $request) 
    {
        // try {
        //     $this->isAuthorized('ROLE_AGENT_ASSIGN_TICKET');
        // } catch(AccessDeniedException $e) {
        //     $json['error'] = $this->get('translator')->trans('Error! Access Denied');
        //     $json['description'] = $this->get('translator')->trans('You are not authorized to perform this Action.');
        //     return new JsonResponse($json, Response::HTTP_UNAUTHORIZED);
        // }

        $json = [];
        $data = json_decode($request->getContent(), true);
        $ticketId = $request->attributes->get('ticketId');

        $em = $this->getDoctrine()->getManager();
        $ticket = $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->findOneBy(array('id' => $ticketId));

        if($ticket) {
            if(isset($data['id'])) {
                $agent = $em->getRepository('UVDeskCoreFrameworkBundle:User')->find($data['id']);
            } else {
                $json['error'] = $this->translate('missing fields');   
                $json['description'] = $this->translate('required: id ');     
                return new JsonResponse($json, Response::HTTP_BAD_REQUEST);   
            }
            if($agent) {
                $flag = 0;
                $agentInfo = [];
                if($ticket->getAgent() != $agent) {
                    $ticketAgent = $ticket->getAgent();
                    $currentAgent = $ticketAgent ? ($ticketAgent->getDetail()['agent'] ? $ticketAgent->getDetail()['agent']->getName() : $this->translate('UnAssigned')) : $this->translate('UnAssigned');
                    $targetAgent = $agent->getDetail()['agent'] ? $agent->getDetail()['agent']->getName() : $this->translate('UnAssigned');
                    
                    $notePlaceholders = $this->get('ticket.service')->getNotePlaceholderValues($currentAgent, $targetAgent, 'agent');    
                    $flag = 1;
                    $agentInfo['agent'] = $data['id'];
                    $agentInfo['firstName'] = current(explode(' ',$targetAgent));
                    $agentInfo['name'] = $targetAgent;
                    $agentInfo['lastReplyAgent'] = $this->get('ticket.service')->getlastReplyAgentName($ticket->getId());
                    $agentInfo['profileImage'] = $agent->smallThumbnail;
                }

                $ticket->setAgent($agent);
                $em->persist($ticket);
                $em->flush();   
                //Event Triggered
                if($flag) {
                    // $this->get('event.manager')->trigger([
                    //         'event' => 'ticket.agent.updated',
                    //         'entity' => $ticket,
                    //         'targetEntity' => $agent,
                    //         'notePlaceholders' => $notePlaceholders
                    //     ]);            
                }
                // $json['agentInfo'] = $agentInfo;
                $json['message'] = 'Success ! Agent successfully assigned.';
                $statusCode = Response::HTTP_OK;
            } else {
                $json['error'] = 'invalid resource';
                $json['description'] = 'Error ! Invalid agent.';
                $statusCode = Response::HTTP_NOT_FOUND;
            }     
        } else {
            $json['error'] = $this->translate('invalid ticket');
            $statusCode = Response::HTTP_NOT_FOUND;
        }

        return new JsonResponse($json, $statusCode);    
    }
}