<?php
// src/DataFixtures/AppFixtures.php

namespace App\DataFixtures;

use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\MicroPost;
use App\Entity\User;
use App\Entity\UserProfile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $userPasswordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // Inizializza Faker per la lingua italiana
        $faker = Factory::create('it_IT');

        // Array per tenere traccia delle entità create e poterle collegare
        $users = [];
        $posts = [];

        // --- 1. CREAZIONE DEGLI UTENTI E DEI LORO PROFILI ---

        // Creo un utente di test con dati noti per il login
        $userTest = new User();
        $userTest->setEmail('test@test.com');
        $userTest->setPassword($this->userPasswordHasher->hashPassword($userTest, '12345678'));
        $userTest->setIsVerified(true);
        $manager->persist($userTest);
        $this->createUserProfile($userTest, $faker, $manager);
        $users[] = $userTest;

        // Creo 10 utenti casuali
        for ($i = 0; $i < 10; $i++) {
            $user = new User();
            $user->setEmail($faker->unique()->email());
            $user->setPassword($this->userPasswordHasher->hashPassword($user, '12345678'));
            $user->setIsVerified($faker->boolean(80)); // 80% di probabilità di essere verificato
            $manager->persist($user);
            $this->createUserProfile($user, $faker, $manager);
            $users[] = $user;
        }

        // --- 2. CREAZIONE DEI MICROPOST ---

        for ($i = 0; $i < 30; $i++) {
            $microPost = new MicroPost();
            $microPost->setTitle($faker->sentence(5));
            $microPost->setText($faker->realText(200));
            $microPost->setCreated(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-6 months', 'now')));
            
            // Associo il post a un autore casuale tra gli utenti creati
            $microPost->setAuthor($users[array_rand($users)]);

            $manager->persist($microPost);
            $posts[] = $microPost;
        }

        // --- 3. CREAZIONE DEI COMMENTI ---

        for ($i = 0; $i < 50; $i++) {
            $comment = new Comment();
            $comment->setText($faker->sentence(15, true));

            // Scelgo un post e un utente casuali
            $randomPost = $posts[array_rand($posts)];
            $randomUser = $users[array_rand($users)];

            // La data del commento deve essere successiva a quella del post
            $comment->setCreated(\DateTimeImmutable::createFromMutable(
                $faker->dateTimeBetween($randomPost->getCreated()->format('Y-m-d H:i:s'))
            ));

            $comment->setPost($randomPost);
            $comment->setUser($randomUser);
            
            $manager->persist($comment);
        }

        // --- 4. CREAZIONE DEI LIKE ---

        $likesTracker = []; // Per evitare like duplicati (stesso utente/stesso post)
        for ($i = 0; $i < 100; $i++) {
            $randomPost = $posts[array_rand($posts)];
            $randomUser = $users[array_rand($users)];

            $likeKey = $randomUser->getEmail() . '-' . spl_object_hash($randomPost);
            
            // Se l'utente non ha già messo like a questo post, lo creo
            if (!in_array($likeKey, $likesTracker)) {
                $like = new Like();
                $like->setPost($randomPost);
                $like->setUser($randomUser);
                
                $manager->persist($like);
                $likesTracker[] = $likeKey;
            }
        }


        // Applica tutte le modifiche al database in un'unica transazione
        $manager->flush();
    }
    
    // Funzione di utilità per creare un UserProfile e associarlo a un User
    private function createUserProfile(User $user, \Faker\Generator $faker, ObjectManager $manager): void
    {
        $userProfile = new UserProfile();
        $userProfile->setUser($user); // Associazione fondamentale
        $userProfile->setUsername($faker->unique()->userName());
        $userProfile->setBio($faker->realText(100));
        $userProfile->setLocation($faker->city());
        $userProfile->setWebsiteUrl($faker->optional(0.5)->url()); // 50% di probabilità di avere un sito
        $userProfile->setCompany($faker->optional(0.5)->company());
        $userProfile->setDateOfBirth($faker->optional(0.8)->dateTimeBetween('-50 years', '-18 years'));
        $userProfile->setCreated(\DateTimeImmutable::createFromMutable($faker->dateTimeThisDecade()));
        
        $manager->persist($userProfile);
    }
}