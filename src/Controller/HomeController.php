<?php
// src/Controller/HomeController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Comment;
use App\Entity\MicroPost;
use App\Entity\UserProfile;
use Doctrine\ORM\EntityManager;
use App\Repository\MicroPostRepository;
use App\Repository\UserProfileRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface; // Importa l'EntityManager

class HomeController extends AbstractController
{

    #[Route('/', name: 'app_home')]
    public function index(MicroPostRepository $posts): Response
    {
        
        // Breadcrumbs for the home page
        $breadcrumbs = [
            ['name' => 'Home']
        ];

        return $this->render('home/index.html.twig', [

            'breadcrumbs' => $breadcrumbs,
            'posts' => $posts->findBy([], ['created' => 'DESC']),
        ]);
    }
}
