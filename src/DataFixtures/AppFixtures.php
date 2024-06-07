<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Entity\User;
use App\Entity\Author;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    } 
    
    public function load(ObjectManager $manager): void
    {
        
        // Création d'un user "normal"
          $user = new User();
          $user->setEmail("user@bookapi.com");
          $user->setRoles(["ROLE_USER"]);
          $user->setPassword($this->userPasswordHasher->hashPassword($user, "password"));
          $manager->persist($user);
          
          // Création d'un user admin
          $userAdmin = new User();
          $userAdmin->setEmail("admin@bookapi.com");
          $userAdmin->setRoles(["ROLE_ADMIN"]);
          $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "password"));
          $manager->persist($userAdmin);
  
        
        $listAuthor = [];
        for ($i=0; $i < 10; $i++) { 
            $author = new Author();
            $author->setLastName('Lastname '. $i);
            $author->setFirstName('Firstname '. $i);
            $manager->persist($author);

            $listAuthor[] = $author;
        }

        for ($i=0; $i < 20; $i++) { 
            $book = new Book();
            $book->setTitle('Livre '. $i);
            $book->setCoverText('Quatrième de couverture numéro : '. $i);
            $book->setComment('Commentaire du bibliothécaire '. $i);
            $book->setAuthor($listAuthor[array_rand($listAuthor)]);
            $manager->persist($book);
        }

        $manager->flush();
    }
}
