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
use Symfony\Component\Security\Core\User\UserInterface;
use Webkul\UVDesk\CoreFrameworkBundle\Entity\SupportLabel;


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
            $json['error'] = $this->translate('Error! Resource not found.');
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

    /**
     * This method is used to add Label.
     * Method : POST
     * Example: /api/ticket/label/add
     * @param Object  "HTTP Request object with json request in Content" 
     * @param User Object "Current User Object" 
     * @return JSON "JSON response"
     */
    public function addLabelRestAction(Request $request, UserInterface $user) 
    {
        if('POST' == $request->getMethod()){
            $user_id = $user;
            $data = json_decode($request->getContent(), true);
            
            if(!empty($data['name'])){
                $em = $this->getDoctrine()->getManager();
                $label = new SupportLabel();
                
                $label->setName($data['name']);
                $label->setColorCode($data['colorCode']);
                $label->setUser($user_id);
                
                $em->persist($label);
                $em->flush();
                
                $json['message'] = 'Success ! Label added successfully.';
                //$json['label'] = json_decode($this->get('default.service')->objectSerializer($label,array('user'), true);
                $statusCode = Response::HTTP_OK;
            } else {
                $json['message'] = 'Error ! Label name can not be blank.';
                $statusCode = Response::HTTP_NOT_FOUND;
            }
        } else {
            $json['message'] = 'Invalid request method.';
            $statusCode = Response::HTTP_METHOD_NOT_ALLOWED;
        }
        return new JsonResponse($json, $statusCode);
    }

    /**
     * This method is used to edit a label.
     * Method: PUT
     * Example: /api/ticket/label/edit/15
     * @param Object  "HTTP Request object with json request in Content"  
     * @return JSON "JSON response"
     */
    public function editLabelRestAction(Request $request)
    {
        if('PUT' == $request->getMethod()){
            $data = json_decode($request->getContent(), true);
            if(isset($data['name'])){
                $em = $this->getDoctrine()->getManager();
                $label = $em->getRepository('UVDeskCoreFrameworkBundle:SupportLabel')->findOneBy(['id' => $request->attributes->get('id')]);
                if(!is_null($label)){
                    $label->setName($data['name']);
                    $label->setColorCode(isset($data['colorCode']) ? $data['colorCode'] : null);
                    $em->persist($label);
                    $em->flush();

                    $json['message'] = 'Success ! Label edited successfully.';
                    //$json['label'] = json_decode($this->get('default.service')->objectSerializer($label,array('user'), true);
                    $statusCode = Response::HTTP_OK;
                } else {
                    $json['message'] = 'Error ! No label found with given id.';
                    $statusCode = Response::HTTP_NOT_FOUND;
                }
            } else {
                $json['message'] = 'Error ! Label name can not be blank.';
                $statusCode = Response::HTTP_NOT_FOUND;
            }
        } else {
            $json['message'] = 'Invalid request method.';
            $statusCode = Response::HTTP_METHOD_NOT_ALLOWED;
        }
        return new JsonResponse($json, $statusCode);
    }

    /**
     * This method is used to delete a label.
     * Method: PUT
     * Example: /api/ticket/label/delete/15
     * @param Object  "HTTP Request object with json request in Content"  
     * @return JSON "JSON response"
     */
    public function deleteLabelRestAction(Request $request)
    {
        if('DELETE' == $request->getMethod()){

            $em = $this->getDoctrine()->getManager();
            $label = $em->getRepository('UVDeskCoreFrameworkBundle:SupportLabel')->findOneBy(['id' => $request->attributes->get('id')]);
            if(!is_null($label)){
                $em->remove($label);
                $em->flush();

                $json['message'] = 'Success ! Label deleted successfully.';
                $statusCode = Response::HTTP_OK;
            } else {
                $json['message'] = 'Error ! No label found with given id.';
                $statusCode = Response::HTTP_NOT_FOUND;
            }
        } else {
            $json['message'] = 'Invalid request method.';
            $statusCode = Response::HTTP_METHOD_NOT_ALLOWED;
        }
        return new JsonResponse($json, $statusCode);
    }

    /**
     * This method is used to trash single/multiple ticket.
     * Method: PUT
     * Example: /api/tickets/trash
     * Example Request Content: <code> { "ids": [5576,5784] } </code>
     * parameters={
     *      {"name"="ids", "dataType"="array", "required"=true, "description"="ticket ids to be trashed"}
     *  }
     * @param Object  "HTTP Request object with json request in Content"
     * @param $user "Object "Current User Object"  
     * @return JSON "JSON response"
     */
    public function massDeleteRestAction(Request $request, UserInterface $user)
    {
        if('PUT' == $request->getMethod()){
            if($this->container->get('api.service')->isAccessAuthorizedForMember('ROLE_AGENT_DELETE_TICKET',$user)){
                $data = json_decode($request->getContent(), true);
                if(!isset($data['ids'])) {
                    $json['error'] = 'required: ids ';
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }
                $ids = $data['ids'];
                $em = $this->getDoctrine()->getManager();
                foreach ($ids as $id) {
                    $ticket = $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->findOneBy(array('id' => $id));
                    if($ticket) {
                        $ticket->setIsTrashed(1);
                        $em->persist($ticket);
                        $em->flush();
                        
                    } else {
                        $json['message'] = 'Error ! No such tickets with id: '.$id;
                        return new JsonResponse($json, Response::HTTP_NOT_FOUND);
                    }
                }
                $json['message'] = (count($ids) > 1) ? 'Success ! Tickets moved to trash successfully.' : 'Success ! Ticket moved to trash successfully.';
                $statusCode = Response::HTTP_OK;
            } else {
                $json['message'] = 'Invalid request method.';
                $statusCode = Response::HTTP_UNAUTHORIZED;
            }    
        } else {
            $json['message'] = 'Invalid request method.';
            $statusCode = Response::HTTP_METHOD_NOT_ALLOWED; 
        }
        return new JsonResponse($json, $statusCode);
    }

    /**
     * This method is used to permanently single/multiple ticket.
     * Method: DELETE
     * Example: /api/tickets/DELETE
     * Example Request Content: <code> { "ids": [5576,5784] } </code>
     * parameters={
     *      {"name"="ids", "dataType"="array", "required"=true, "description"="ticket ids to be deleted permanently"}
     *  }
     * @param Object  "HTTP Request object with json request in Content"
     * @param $user "Object "Current User Object"  
     * @return JSON "JSON response"
     */
    public function massDeleteForeverRestAction(Request $request, UserInterface $user)
    {
        if('DELETE' == $request->getMethod()){
            if($this->container->get('api.service')->isAccessAuthorizedForMember('ROLE_AGENT_DELETE_TICKET',$user)){
                $data = json_decode($request->getContent(), true);
                if(!isset($data['ids'])) {
                    $json['error'] = 'required: ids ';
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }
                $ids = $data['ids'];
                $em = $this->getDoctrine()->getManager();
                foreach ($ids as $id) {
                    $ticket = $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->findOneBy(array('id' => $id));
                    if($ticket) {
                        $em->remove($ticket);
                        $em->flush();  
                    } else {
                        $json['message'] = 'Error ! No such tickets with id: '.$id;
                        return new JsonResponse($json, Response::HTTP_NOT_FOUND);
                    }
                }
                $json['message'] = 'Success ! Tickets deleted successfully.';
                $json['message'] = (count($ids) > 1) ? 'Success ! Tickets deleted successfully.' : 'Success ! Ticket deleted successfully.';
                $statusCode = Response::HTTP_OK;
            } else {
                $json['message'] = 'Invalid request method.';
                $statusCode = Response::HTTP_UNAUTHORIZED;
            }    
        } else {
            $json['message'] = 'Invalid request method.';
            $statusCode = Response::HTTP_METHOD_NOT_ALLOWED; 
        }
        return new JsonResponse($json, $statusCode);
    }

    /**
     * This method is used to restore single/multiple ticket.
     * Method: PUT
     * Example: /api/tickets/trash
     * Example Request Content: <code> { "ids": [5576,5784] } </code>
     * parameters={
     *      {"name"="ids", "dataType"="array", "required"=true, "description"="ticket ids to be restored"}
     *  }
     * @param Object  "HTTP Request object with json request in Content"
     * @param $user "Object "Current User Object"  
     * @return JSON "JSON response"
     */
    public function massRestoreRestAction(Request $request, UserInterface $user)
    {
        if('PUT' == $request->getMethod()){
            if($this->container->get('api.service')->isAccessAuthorizedForMember('ROLE_AGENT_RESTORE_TICKET',$user)){
                $data = json_decode($request->getContent(), true);
                if(!isset($data['ids'])) {
                    $json['error'] = 'required: ids ';
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }
                $ids = $data['ids'];
                $em = $this->getDoctrine()->getManager();
                foreach ($ids as $id) {
                    $ticket = $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->findOneBy(array('id' => $id));
                    if($ticket) {
                        $ticket->setIsTrashed(0);
                        $em->persist($ticket);
                        $em->flush();
                        
                    } else {
                        $json['message'] = 'Error ! No such tickets with id: '.$id;
                        return new JsonResponse($json, Response::HTTP_NOT_FOUND);
                    }
                }
               
                $json['message'] = (count($ids) > 1) ? 'Success ! Tickets restored successfully.' : 'Success ! Ticket restored successfully.';
                $statusCode = Response::HTTP_OK;
            } else {
                $json['message'] = 'Invalid request method.';
                $statusCode = Response::HTTP_UNAUTHORIZED;
            }    
        } else {
            $json['message'] = 'Invalid request method.';
            $statusCode = Response::HTTP_METHOD_NOT_ALLOWED; 
        }
        return new JsonResponse($json, $statusCode);
    }

    /**
     * This method is used to assign single/multiple ticket to a agent.
     * Method: PUT
     * Example: /api/tickets/assign-agent
     * Example Request Content: <code> { "ids": [57,78], "id": "99"} </code>
     * parameters={
     *      {"name"="ids", "dataType"="array", "required"=true, "description"="ticket ids"}
     *      {"name"="agentId", "dataType"="integer", "required"=true, "description"="agent id"}
     *  }
     * @param Object  "HTTP Request object with json request in Content"
     * @param $user "Object "Current User Object"  
     * @return JSON "JSON response"
     */
    public function massAssignAgentRestAction(Request $request, UserInterface $user)
    {
        if('PUT' == $request->getMethod()){
            if($this->container->get('api.service')->isAccessAuthorizedForMember('ROLE_AGENT_ASSIGN_TICKET',$user)){
                $data = json_decode($request->getContent(), true);
                
                if(!isset($data['ids']) && !isset($data['agentId'])) {
                    $json['error'] = 'required ticket id / agent id';
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }
                $em = $this->getDoctrine()->getManager();
                //Checking Agent Provided.
                $agent = $em->getRepository('UVDeskCoreFrameworkBundle:UserInstance')->findOneBy(array('user' => $data['agentId']));
                if(!is_null($agent)){
                    $agentRole = $agent->getSupportRole()->getId();
                    $isAgent = in_array($agentRole,['1','2','3']);
                    if(!$isAgent){
                        $json['error'] = 'The user '.$data['agentId'].' is not registered as Agent/Admin.';
                        return new JsonResponse($json, Response::HTTP_BAD_REQUEST); 
                    }
                } else {
                    $json['error'] = 'No user/agent found with id : '.$data['agentId'];
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }
                
                $ids = $data['ids'];
                foreach ($ids as $id) {
                    $ticket = $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->findOneBy(array('id' => $id));
                    if($ticket) {
                        //$agent->getUser() : getting user from user Instance

                        $ticket->setAgent($agent->getUser());
                        $em->persist($ticket);
                        $em->flush();
                        
                    } else {
                        $json['message'] = 'Error ! No such tickets with id: '.$id;
                        return new JsonResponse($json, Response::HTTP_NOT_FOUND);
                    }
                }
                $json['message'] = (count($ids) > 1) ? 'Success ! Tickets assigned to agent successfully.' : 'Success ! Ticket assigned to agent successfully.';
                $statusCode = Response::HTTP_OK;
            } else {
                $json['message'] = 'Invalid request method.';
                $statusCode = Response::HTTP_UNAUTHORIZED;
            }    
        } else {
            $json['message'] = 'Invalid request method.';
            $statusCode = Response::HTTP_METHOD_NOT_ALLOWED; 
        }
        return new JsonResponse($json, $statusCode);
    }

    /**
     * This method is used to Change status of single/multiple ticket.
     * Method: PUT
     * Example: /api/tickets/set-status
     * Example Request Content: <code> { "ids": [57,78], "statusId": "1"} </code>
     * 
     * Hint: statusId: 1|2|3|4|5|6 for open|pending|resolved|closed|Spam|Answered repectively
     * 
     * parameters={
     *      {"name"="ids", "dataType"="array", "required"=true, "description"="ticket ids"},
     *      {"name"="statusId", "dataType"="integer", "required"=true, "description"="status id"}
     *  }
     * @param Object  "HTTP Request object with json request in Content"
     * @param $user "Object "Current User Object"  
     * @return JSON "JSON response"
     */
    public function massStatusChangeRestAction(Request $request, UserInterface $user)
    {
        if('PUT' == $request->getMethod()){
            if($this->container->get('api.service')->isAccessAuthorizedForMember('ROLE_AGENT_UPDATE_TICKET_STATUS',$user)){
                $data = json_decode($request->getContent(), true);
                
                if(!isset($data['ids']) && !isset($data['statusId'])) {
                    $json['error'] = 'required ticket id / status id';
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }
                $em = $this->getDoctrine()->getManager();
                //Checking Status.
                $status = $em->getRepository('UVDeskCoreFrameworkBundle:TicketStatus')->findOneBy(array('id' => $data['statusId']));
                if(!is_null($status)){

                    //setting status on each tickect
                    $ids = $data['ids'];
                    foreach ($ids as $id) {
                        $ticket = $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->findOneBy(array('id' => $id));
                        if($ticket) {
                            $ticket->setStatus($status);
                            $em->persist($ticket);
                            $em->flush();    
                        } else {
                            $json['message'] = 'Error ! No such tickets with id: '.$id;
                            return new JsonResponse($json, Response::HTTP_NOT_FOUND);
                        }
                    }    
                } else {
                    $json['error'] = 'No status found with id : '.$data['statusId'];
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }

                $json['message'] = (count($ids) > 1) ? 'Success ! Status of tickets changed successfully.' : 'Success ! Status of ticket changed successfully.';
                $statusCode = Response::HTTP_OK;
            } else {
                $json['message'] = 'Invalid request method.';
                $statusCode = Response::HTTP_UNAUTHORIZED;
            }    
        } else {
            $json['message'] = 'Invalid request method.';
            $statusCode = Response::HTTP_METHOD_NOT_ALLOWED; 
        }
        return new JsonResponse($json, $statusCode);
    }

    /**
     * This method is used to Change group of single/multiple ticket.
     * Method: PUT
     * Example: /api/tickets/set-group
     * Example Request Content: <code> { "ids": [57,78], "groupId": "1"} </code>
     *  
     * parameters={
     *      {"name"="ids", "dataType"="array", "required"=true, "description"="ticket ids"},
     *      {"name"="groupId", "dataType"="integer", "required"=true, "description"="group id"}
     *  }
     * @param Object  "HTTP Request object with json request in Content"
     * @param $user "Object "Current User Object"  
     * @return JSON "JSON response"
     */
    public function massGroupChangeRestAction(Request $request, UserInterface $user)
    {
        if('PUT' == $request->getMethod()){
            if($this->container->get('api.service')->isAccessAuthorizedForMember('ROLE_AGENT_ASSIGN_TICKET_GROUP',$user)){
                $data = json_decode($request->getContent(), true);
                if(!isset($data['ids']) && !isset($data['groupId'])) {
                    $json['error'] = 'required ticket id / group id';
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }
                $em = $this->getDoctrine()->getManager();

                //Checking group.
                $group = $em->getRepository('UVDeskCoreFrameworkBundle:SupportGroup')->findOneBy(array('id' => $data['groupId']));
                if(!is_null($group)){

                    $ids = $data['ids'];
                    foreach ($ids as $id) {
                        //checking ticket
                        $ticket = $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->findOneBy(array('id' => $id));
                        if($ticket) {
                            $ticket->setSupportGroup($group);
                            $em->persist($ticket);
                            $em->flush();    
                        } else {
                            $json['message'] = 'Error ! No such tickets with id: '.$id;
                            return new JsonResponse($json, Response::HTTP_NOT_FOUND);
                        }
                    }    
                } else {
                    $json['error'] = 'No group found with id : '.$data['groupId'];
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }

                $json['message'] = (count($ids) > 1) ? 'Success ! Group of tickets changed successfully.' : 'Success ! Group of ticket changed successfully.';
                $statusCode = Response::HTTP_OK;
            } else {
                $json['message'] = 'Invalid request method.';
                $statusCode = Response::HTTP_UNAUTHORIZED;
            }    
        } else {
            $json['message'] = 'Invalid request method.';
            $statusCode = Response::HTTP_METHOD_NOT_ALLOWED; 
        }
        return new JsonResponse($json, $statusCode);
    }

    /**
     * This method is used to Change priority of single/multiple ticket.
     * Method: PUT
     * Example: /api/tickets/set-priority
     * Example Request Content: <code> { "ids": [57,78], "priorityId": "1"} </code>
     *  
     * parameters={
     *      {"name"="ids", "dataType"="array", "required"=true, "description"="ticket ids"},
     *      {"name"="priorityId", "dataType"="integer", "required"=true, "description"="priority id"}
     *  }
     * @param Object  "HTTP Request object with json request in Content"
     * @param $user "Object "Current User Object"  
     * @return JSON "JSON response"
     */
    public function massPriorityChangeRestAction(Request $request, UserInterface $user)
    {
        if('PUT' == $request->getMethod()){
            if($this->container->get('api.service')->isAccessAuthorizedForMember('ROLE_AGENT_UPDATE_TICKET_PRIORITY',$user)){
                $data = json_decode($request->getContent(), true);
                if(!isset($data['ids']) && !isset($data['priorityId'])) {
                    $json['error'] = 'required ticket id / priority id';
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }
                $em = $this->getDoctrine()->getManager();

                //Checking priority.
                $priority = $em->getRepository('UVDeskCoreFrameworkBundle:TicketPriority')->findOneBy(array('id' => $data['priorityId']));
                if(!is_null($priority)){
                    $ids = $data['ids'];
                    foreach ($ids as $id) {
                        //checking ticket
                        $ticket = $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->findOneBy(array('id' => $id));
                        if($ticket) {
                            $ticket->setPriority($priority);
                            $em->persist($ticket);
                            $em->flush();    
                        } else {
                            $json['message'] = 'Error ! No such tickets with id: '.$id;
                            return new JsonResponse($json, Response::HTTP_NOT_FOUND);
                        }
                    }    
                } else {
                    $json['error'] = 'No priority found with id : '.$data['priorityId'];
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }

                $json['message'] = (count($ids) > 1) ? 'Success ! Priority of tickets changed successfully.' : 'Success ! Priority of ticket changed successfully.';
                $statusCode = Response::HTTP_OK;
            } else {
                $json['message'] = 'Invalid request method.';
                $statusCode = Response::HTTP_UNAUTHORIZED;
            }    
        } else {
            $json['message'] = 'Invalid request method.';
            $statusCode = Response::HTTP_METHOD_NOT_ALLOWED; 
        }
        return new JsonResponse($json, $statusCode);
    }

    /**
     * This method is used to set label of single/multiple ticket.
     * Method: PUT
     * Example: /api/tickets/set-label
     * Example Request Content: <code> { "ids": [57,78], "labelId": "1"} </code>
     *  
     * parameters={
     *      {"name"="ids", "dataType"="array", "required"=true, "description"="ticket ids"},
     *      {"name"="labelId", "dataType"="integer", "required"=true, "description"="label id"}
     *  }
     * @param Object  "HTTP Request object with json request in Content"
     * @param $user "Object "Current User Object"  
     * @return JSON "JSON response"
     */
    public function massMoveToLabelRestAction(Request $request, UserInterface $user)
    {
        if('PUT' == $request->getMethod()){
            if($this->container->get('api.service')->isAccessAuthorizedForMember('ROLE_AGENT_UPDATE_TICKET_label',$user)){
                $data = json_decode($request->getContent(), true);
                if(!isset($data['ids']) && !isset($data['labelId'])) {
                    $json['error'] = 'required ticket id / label id';
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }
                $em = $this->getDoctrine()->getManager();

                //Checking priority.
                $priority = $em->getRepository('UVDeskCoreFrameworkBundle:TicketPriority')->findOneBy(array('id' => $data['priorityId']));
                if(!is_null($priority)){
                    $ids = $data['ids'];
                    foreach ($ids as $id) {
                        //checking ticket
                        $ticket = $em->getRepository('UVDeskCoreFrameworkBundle:Ticket')->findOneBy(array('id' => $id));
                        if($ticket) {
                            $ticket->setPriority($priority);
                            $em->persist($ticket);
                            $em->flush();    
                        } else {
                            $json['message'] = 'Error ! No such tickets with id: '.$id;
                            return new JsonResponse($json, Response::HTTP_NOT_FOUND);
                        }
                    }    
                } else {
                    $json['error'] = 'No priority found with id : '.$data['priorityId'];
                    return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
                }

                $json['message'] = (count($ids) > 1) ? 'Success ! Priority of tickets changed successfully.' : 'Success ! Priority of ticket changed successfully.';
                $statusCode = Response::HTTP_OK;
            } else {
                $json['message'] = 'Invalid request method.';
                $statusCode = Response::HTTP_UNAUTHORIZED;
            }    
        } else {
            $json['message'] = 'Invalid request method.';
            $statusCode = Response::HTTP_METHOD_NOT_ALLOWED; 
        }
        return new JsonResponse($json, $statusCode);
    }

}