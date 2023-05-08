<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthorController extends AbstractController
{

    #[Route('api/author/{id}', name: 'detailAuthor', methods: ['GET'])]
    public function getAuthor(Author $author, SerializerInterface $serializer): JsonResponse
    {
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthor']);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }

    /**
     * @throws ORMException
     */
    #[Route('api/author/{id}', name: 'delAuthor', methods: ['DELETE'])]
    public function delAuthor(Author $author, EntityManagerInterface $em)
    {
        try {
            $em->remove($author);
            $em->flush();
        } catch (ORMException $e) {
            throw new ORMException('erreur : ' . $e);
        }
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('api/author', name: 'createAuthor', methods: ['POST'])]
    public function createAuthor(EntityManagerInterface $em,
                                 Request                $request,
                                 SerializerInterface    $serializer,
                                 UrlGeneratorInterface  $urlGenerator,
                                 ValidatorInterface     $validator): JsonResponse
    {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');
        $errors = $validator->validate($author);

        if(count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true);
        }

        $em->persist($author);
        $em->flush();

        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getBooks']);
        $location = $urlGenerator->generate('detailAuthor', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ['Location' => $location], true);
    }

    #[Route('api/author/{id}', name: 'updateAuthor', methods: ['PUT'])]
    public function updateAuthor(Author                 $currentAuthor,
                                 EntityManagerInterface $em,
                                 SerializerInterface    $serializer,
                                 Request                $request): JsonResponse
    {
        $updateAuthor = $serializer->deserialize($request->getContent(),
            Author::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAuthor]);

        $em->persist($updateAuthor);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
