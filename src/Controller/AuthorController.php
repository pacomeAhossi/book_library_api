<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use  Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AuthorController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des livres.
     * 
     * @Route("/api/authors", name="app_authors", methods={"GET"})
     */
    public function getAllAuthors(AuthorRepository $authorRepo, SerializerInterface $serializer,
                                    Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllAuthors-". $page. "-". $limit;

        $jsonAuthorList = $cache->get($idCache, function (ItemInterface $item) use ($authorRepo, $page, $limit, $serializer) {
            $item->tag("booksCache");
            $authorList = $authorRepo->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(["getAuthors"]);
            return $serializer->serialize($authorList, 'json', $context);
        });

        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }


    /**
     * Cette méthode permet de récupérer un auteur en particulier en fonction de son id
     * 
     * @Route("/api/authors/{id}", name="app_authors_detail",  methods={"GET"})
     */
    public function getDetailAuthor(Author $author, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(["getAuthors"]);
        $jsonBook = $serializer->serialize($author, 'json', $context);
        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);

    }
    

    // Création d'un author

    /**
     * Cette méthode permet de créer un nouvel auteur. Elle ne permet pas d'associer des livres à cet auteur.
     * 
     * @Route("/api/authors", name="app_create_author",  methods={"POST"})
     *@IsGranted("ROLE_ADMIN", message="Vous n'avez pas le droit nécessaire pour créer un auteur")
     */
    public function createAuthor(Request $request, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator,
                                    SerializerInterface $serializer, ValidatorInterface $validator): JsonResponse
    {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');
         //Vérification de la validité des attributs de l'objet avant la persistance
         $errors = $validator->validate($author);
         if($errors->count() > 0){
             return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
         }
        $em->persist($author);
        $em->flush();

        $context = SerializationContext::create()->setGroups(["getAuthors"]);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);

        $location = $urlGenerator->generate('app_authors_detail', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ['Location' => $location], true);
    }

    // Update d'un author
     /**
      * Cette méthode permet de modifier un auteur en fonction de son id

     * @Route("/api/authors/{id}", name="app_authors_update",  methods={"PUT"})
     * @IsGranted("ROLE_ADMIN", message="Vous n'avez pas le droit nécessaire pour modifier un auteur")
     */
    public function UpdateAuthor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em,
                                    Author $currentAuthor, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $errors = $validator->validate($currentAuthor);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $newAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json');
        $currentAuthor->setFirstName($newAuthor->getFirstName());
        $currentAuthor->setLastName($newAuthor->getLastName());
        
        $em->persist($currentAuthor);
        $em->flush();

        $cache->invalidateTags(["authorsCache"]);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);

    }

    // Delete d'un author
    /**
     * Cette méthode permet de supprimer un auteur en fonction de son id
     * 
     * @Route("api/authors/{id}", name="app_delete_authors", methods={"DELETE"})
     * @IsGranted("ROLE_ADMIN", message="Vous n'avez pas le droit nécessaire pour supprimer un auteur")
     */
    public function deleteAuthor(Author $author, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        
        $cache->invalidateTags(["authorsCache"]);
        $em->remove($author);
        $em->flush();
       // dd($author->getBooks());
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
