<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Message;
use AppBundle\Form\newMessageType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/community")
 */
class CommunityController extends Controller
{
    /**
     * @Route("/", name="communityList")
     */
    public function communityListAction()
    {
        $user = $this->getUser();
        $signup = [];

        $communitiesUser = $this->getDoctrine()->getRepository('AppBundle:UserCommunity')->findBy([
            'user' => $user,
            'isActive' => true,
            'isDeleted' => false
        ]);

        foreach($communitiesUser as $community){
            array_push($signup, $community->getCommunity());
        }

        $officials = $this->getDoctrine()->getRepository('AppBundle:Community')->findBy([
            'privacy' => 'default',
            'isSuspended' => false,
            'isDeleted' => false,
            'isBlock' => false
        ]);

        $admin = $this->getDoctrine()->getRepository('AppBundle:Community')->findBy([
            'admin' => $user,
            'isSuspended' => false,
            'isDeleted' => false,
            'isBlock' => false
        ]);

        $last = $this->getDoctrine()->getRepository('AppBundle:Community')->findBy([
            'privacy' => [
                'public', 'protected', 'default'
            ],
            'isBlock' => false,
            'isDeleted' => false,
            'isSuspended' => false
        ],[
            'creationDate' => 'DESC'
        ], 15);

        $communityList = $this->getDoctrine()->getRepository('AppBundle:Community')->findBy([
            'isBlock' => false,
            'isSuspended' => false,
            'isDeleted' => false
        ]);

        return $this->render('Community/comunitiesList.html.twig', [
            'user' => $user,
            'signup' => $signup,
            'officials' => $officials,
            'admin' => $admin,
            'last' => $last,
            'communityList' => $communityList
        ]);
    }

    /**
     * @Route("/general", name="generalCommunity")
     */
    public function generalCommunityAction(Request $request)
    {
        $user = $this->getUser();

        $community = $this->getDoctrine()->getRepository('AppBundle:Community')->findOneBy([
            'privacy' => 'default',
            'name' => 'General',
            'communityCreator' => null,
            'admin' => null
        ]);

        $msg = new Message();
        $form = $this->createForm(newMessageType::class, $msg);
        $form->handleRequest($request);

        $messages = $this->getDoctrine()->getRepository('AppBundle:Message')->findBy([
            'community' => $community,
            'isReply' => false,
            'isActive' => true,
            'isDeleted' => false,
            'isBlock' => false
        ],[
            'date' => 'DESC'
        ]);

        if($form->isSubmitted() && $form->isValid()){
            $msg->setUser($user);
            $msg->setCommunity($community);
            $msg->setDate(new \DateTime("now"));
            $msg->setIsActive(1);
            $msg->setIsBlock(0);
            $msg->setIsDeleted(0);
            $msg->setIsReply(0);
            $msg->setIP($this->get('request_stack')->getCurrentRequest()->getClientIp());

            $em = $this->getDoctrine()->getManager();
            $em->persist($msg);
            $em->flush();

            return $this->redirectToRoute('generalCommunity');
        }

        return $this->render('Community/community.html.twig', [
            'user' => $user,
            'access' => 'default',
            'community' => $community,
            'msg' => $form->createView(),
            'messages' => $messages
        ]);
    }

    /**
     * @Route("/general/{id}", name="messageDetailsGeneral")
     */
    public function messageDetailsGeneralAction($id, Request $request)
    {
        $user = $this->getUser();
        $access = "";

        $message = $this->getDoctrine()->getRepository('AppBundle:Message')->find($id);
        $form = null;


        if(count($message) == 0){
            $message = null;
            $access = 'notFound';
        }else{

            if($message->isIsDeleted()){
                $access = 'deleted';
            }elseif ($message->isIsBlock()) {
                $access = 'block';
            }elseif ($message->isIsActive() == false){
                $access = 'inactive';
            }else{
                $access = 'default';
                $msg = new Message();
                $form = $this->createForm(newMessageType::class, $msg);
                $form->handleRequest($request);

                if($form->isSubmitted() && $form->isValid()){
                    $msg->setUser($user);
                    $msg->setReply($message);
                    $msg->setCommunity($message->getCommunity());
                    $msg->setDate(new \DateTime("now"));
                    $msg->setIsActive(1);
                    $msg->setIsBlock(0);
                    $msg->setIsDeleted(0);
                    $msg->setIsReply(1);
                    $msg->setIP($this->get('request_stack')->getCurrentRequest()->getClientIp());

                    $em = $this->getDoctrine()->getManager();
                    $em->persist($msg);
                    $em->flush();

                    return $this->redirectToRoute('messageDetailsGeneral', [
                        'id' => $message->getId()
                    ]);
                }

                $form = $form->createView();
            }
        }

        return $this->render('Community/messageDetails.html.twig', [
            'user' => $user,
            'access' => $access,
            'message' => $message,
            'isGeneral' => true,
            'form' => $form
        ]);
    }

    /**
     * @Route("/deleteMessage/{id}", name="deleteMessage")
     */
    public function deleteMessageAction($id)
    {
        $status = 'Se ha producido un error desconocido.';
        $user = $this->getUser();

        $message = $this->getDoctrine()->getRepository('AppBundle:Message')->find($id);

        if(($message->getUser() == $user) && ($message->isIsActive() == true) && ($message->isIsDeleted() == false) && ($message->isIsBlock() == false)){
            if(count($message) == 1){
                $em = $this->getDoctrine()->getManager();

                $replies = $this->getDoctrine()->getRepository('AppBundle:Message')->findBy([
                    'isReply' => true,
                    'reply' => $message,
                    'isDeleted' => false,
                    'isActive' => true,
                    'isBlock' => false
                ]);

                for($i=0; $i<count($replies); $i++){
                    $replies[$i]->setIsDeleted(1);
                    $em->persist($replies[$i]);
                    $em->flush();
                }

                $message->setIsDeleted(1);
                $em->persist($message);
                $em->flush();

                $status = 'Mensaje eliminado correctamente.';
            }else{
                $status = 'No tienes permisos para borrar este mensaje.';
            }
        }else{
            $status = 'No tienes permisos para borrar este mensaje.';
        }

        return $this->render('Community/deleteMessage.html.twig', [
            'status' => $status
        ]);
    }

    /**
     * @Route("/{id}", name="viewCommunity")
     */
    public function viewCommunityAction($id, Request $request)
    {
        $community = $this->getDoctrine()->getRepository('AppBundle:Community')->find($id);

        $user = $this->getUser();
        $userAccess = false;
        $status = '';
        $messages = '';
        $form = '';

        if(count($community) == 1){
            if($community->getPrivacy() == 'default' && $community->getName() == 'General'){
                return $this->redirectToRoute('generalCommunity');
            }

            if($community->isIsBlock()){
                $access = 'blocked';
            }elseif($community->isIsSuspended()){
                $access = 'suspended';
            }elseif($community->isIsDeleted()){
                $access = 'deleted';
            }else{
                $access = 'active';
                $privacy = $community->getPrivacy();

                switch($privacy){
                    case 'public': {
                        $userAccess = true;
                        $status = 'full';
                        break;
                    }

                    case 'private': {
                        $check = $this->getDoctrine()->getRepository('AppBundle:UserCommunity')->findOneBy([
                            'community' => $community,
                            'user' => $user,
                            'isDeleted' => false
                        ]);

                        if(count($check) == 1){
                            if($check->getIsActive()){
                                $userAccess = true;
                                $status = 'full';
                            }else{
                                $status = 'notAccepted';
                            }
                        }else{
                            $userAccess = false;
                            $status = 'userRelationNotFound';
                        }

                        break;
                    }

                    case 'protected': {
                        $userAccess = true;

                        $check = $this->getDoctrine()->getRepository('AppBundle:UserCommunity')->findOneBy([
                            'community' => $community,
                            'user' => $user,
                            'isDeleted' => false
                        ]);

                        if(count($check) == 1){
                            if($check->getIsActive()){
                                $status = 'protectedAllow';
                            }else{
                                $status = 'protectedDeny';
                            }
                        }else{
                            $status = 'protectedDeny';
                        }

                        break;
                    }

                    case 'default': {
                        $userAccess = true;
                        $status = 'full';
                        break;
                    }
                }

                if($userAccess){
                    $messages = $this->getDoctrine()->getRepository('AppBundle:Message')->findBy([
                        'community' => $community,
                        'isReply' => false,
                        'isActive' => true,
                        'isDeleted' => false,
                        'isBlock' => false
                    ],[
                        'date' => 'DESC'
                    ]);

                    if($status == 'protectedAllow' || $status == 'full'){
                        $msg = new Message();
                        $form = $this->createForm(newMessageType::class, $msg);
                        $form->handleRequest($request);

                        if($form->isSubmitted() && $form->isValid()){
                            $msg->setUser($user);
                            $msg->setCommunity($community);
                            $msg->setDate(new \DateTime("now"));
                            $msg->setIsActive(1);
                            $msg->setIsBlock(0);
                            $msg->setIsDeleted(0);
                            $msg->setIsReply(0);
                            $msg->setIP($this->get('request_stack')->getCurrentRequest()->getClientIp());

                            $em = $this->getDoctrine()->getManager();
                            $em->persist($msg);
                            $em->flush();

                            return $this->redirectToRoute('viewCommunity', ['id'=>$community->getId()]);
                        }

                        $form = $form->createView();
                    }
                }
            }

        }else{
            $community = null;
            $access = 'notFound';
        }


        return $this->render('Community/community.html.twig', [
            'user' => $user,
            'community' => $community,
            'access' => $access,
            'status' => $status,
            'messages' => $messages,
            'msg' => $form,
            'userAccess' => $userAccess
        ]);
    }

    /**
     * @Route("/message/{id}", name="messageDetails")
     */
    public function messageDetailsAction($id, Request $request)
    {
        $user = $this->getUser();
        $access = "";
        $userAccess = false;
        $status = "";

        $message = $this->getDoctrine()->getRepository('AppBundle:Message')->find($id);

        $form = null;


        if(count($message) == 0){
            $message = null;
            $access = 'notFound';
        }else{
            if($message->getCommunity()->getPrivacy() == 'default' && $message->getCommunity()->getName() == 'General'){
                return $this->redirectToRoute('messageDetailsGeneral', ['id'=>$message->getId()]);
            }

            if($message->isIsDeleted()){
                $access = 'deleted';
            }elseif ($message->isIsBlock()) {
                $access = 'block';
            }elseif ($message->isIsActive() == false){
                $access = 'inactive';
            }else{
                $access = 'active';
                $privacy = $message->getCommunity()->getPrivacy();

                switch($privacy){
                    case 'public': {
                        $userAccess = true;
                        $status = 'full';
                        break;
                    }

                    case 'private': {
                        $check = $this->getDoctrine()->getRepository('AppBundle:UserCommunity')->findOneBy([
                            'community' => $message->getCommunity(),
                            'user' => $user,
                            'isDeleted' => false
                        ]);

                        if(count($check) == 1){
                            if($check->getIsActive()){
                                $userAccess = true;
                                $status = 'full';
                            }else{
                                $status = 'notAccepted';
                            }
                        }else{
                            $userAccess = false;
                            $status = 'userRelationNotFound';
                        }

                        break;
                    }

                    case 'protected': {
                        $userAccess = true;

                        $check = $this->getDoctrine()->getRepository('AppBundle:UserCommunity')->findOneBy([
                            'community' => $message->getCommunity(),
                            'user' => $user,
                            'isDeleted' => false
                        ]);

                        if(count($check) == 1){
                            if($check->getIsActive()){
                                $status = 'protectedAllow';
                            }else{
                                $status = 'protectedDeny';
                            }
                        }else{
                            $status = 'protectedDeny';
                        }

                        break;
                    }

                    case 'default': {
                        $status = null;
                        break;
                    }
                }

                if($userAccess){
                    if($status == 'protectedAllow' || $status == 'full'){
                        $msg = new Message();
                        $form = $this->createForm(newMessageType::class, $msg);
                        $form->handleRequest($request);

                        if($form->isSubmitted() && $form->isValid()){
                            $msg->setUser($user);
                            $msg->setReply($message);
                            $msg->setCommunity($message->getCommunity());
                            $msg->setDate(new \DateTime("now"));
                            $msg->setIsActive(1);
                            $msg->setIsBlock(0);
                            $msg->setIsDeleted(0);
                            $msg->setIsReply(1);
                            $msg->setIP($this->get('request_stack')->getCurrentRequest()->getClientIp());

                            $em = $this->getDoctrine()->getManager();
                            $em->persist($msg);
                            $em->flush();

                            return $this->redirectToRoute('messageDetails', [
                                'id' => $message->getId()
                            ]);
                        }

                        $form = $form->createView();
                    }
                }
            }
        }

        return $this->render('Community/messageDetails.html.twig', [
            'user' => $user,
            'access' => $access,
            'message' => $message,
            'isGeneral' => false,
            'status' => $status,
            'form' => $form
        ]);
    }
}