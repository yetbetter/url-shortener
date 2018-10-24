<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Url;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use App\Form\UrlForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use App\Service\UrlChecker;

class UrlController extends AbstractController
{
    /**
     * @Route("/urls", name="urls")
     */
    public function index()
    {
        $urls = $this->fetchUserUrls();

        return $this->generateHtmlPage('url/index.html.twig', ['urls' => $urls]);
    }

    private function fetchUserUrls()
    {
        return $this->getDoctrine()->getRepository(User::class)->find($this->getUser()->getId())->getUrls();
    }

    private function generateHtmlPage(string $templeateName, array $data=[])
    {
        return $this->render($templeateName, $data);
    }

    /**
     * @Route("/urls/create", name="urls_create")
     */
    public function create(Request $request, UrlChecker $urlChecker)
    {
        $form = $this->generateUrlForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $url = $this->getUrlFromForm($form);

            if ($this->isValidUrl($urlChecker, $url)) {            
                $this->saveUrl($form);

                $this->createNotification('success', 'Url added!');

                return $this->redirectToRoute('urls');
            }

            $this->createNotification('error', 'Url is not work');
        }

        return $this->generateHtmlPage(
            'url/create.html.twig', 
            ['form' => $form->createView()]
        );
    }

    private function getUrlEntity()
    {
        return new Url();
    }

    private function generateUrlForm()
    {
        return $this->createForm(UrlForm::class, $this->getUrlEntity());
    }

    private function getUrlFromForm($form)
    {
        return $form->get('name')->getData();
    }

    private function isValidUrl($urlChecker, $url)
    {
        $httpCode = $urlChecker->check($url);

        if ($httpCode == 200) {
            return true;
        }

        return false;
    }

    private function saveUrl($form)
    {
        $url = $this->getUrlEntity();
        $url->setUser($this->getUser());
        $url->setName($form->get('name')->getData());
        $url->setShortName($form->get('short_name')->getData());
        $url->setIsShared(false);

        $em = $this->getDoctrine()->getManager();
        $em->persist($url);
        $em->flush();
    }

    private function createNotification($name, $message)
    {
        return $this->addFlash($name, $message);
    }

    /**
     * @Route("/urls/share/{id}", name="urls_share")
     */
    public function share($id)
    {
        $this->shareUrl($id);

        return $this->redirectToRoute('urls');
    }

    private function shareUrl($id)
    {
        $em = $this->getDoctrine()->getManager();

        $url = $em->getRepository(Url::class)->find($id);

        if (!$url) {
            throw $this->createNotFoundException(
                'Url does not exists'
            );
        }

        $url->setIsShared(true);
        $em->flush();
    }

    /**
     * @Route("/urls/shared", name="urls_shared")
     */
    public function sharedUrls()
    {
        $urls = $this->fetchSharedUrls();

        return $this->generateHtmlPage('url/shared.html.twig', ['urls' => $urls]);
    }

    private function fetchSharedUrls()
    {
        return $this->getDoctrine()->getRepository(Url::class)->findBy(['is_shared' => true]);
    }
}