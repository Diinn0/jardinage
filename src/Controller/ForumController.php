<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\ForumCategory;
use App\Entity\User;
use App\Entity\UserComment;
use App\Repository\CommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ForumController extends AbstractController
{
    /**
     * @Route("/forum", name="forum")
     */
    public function index(): Response
    {
        $categoriesRepository = $this->getDoctrine()->getRepository(ForumCategory::class);
        $categories = $categoriesRepository->findMainCategories();
        foreach ($categories as $category) {
            $category->getSubCategories()->initialize();
            $category->getComments()->initialize();
        }
        return $this->render('forum/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    /**
     * @Route("/forum/c/{category?}", name="showCategory", priority="2")
     */
    public function showCategory(String $category): Response
    {
        $categoriesRepository = $this->getDoctrine()->getRepository(ForumCategory::class);
        $parentCategory = $categoriesRepository->find($category);
        $categories = $categoriesRepository->findSubCategoriesByMain($parentCategory);
        foreach ($categories as $category) {
            $category->getSubCategories()->initialize();
        }
        $parentCategory->getComments()->initialize();

        return $this->render('forum/index.html.twig', [
            'parentCategory' => $parentCategory,
            'categories' => $categories,
            'parents' => $this->getParents($parentCategory),
        ]);
    }

    /**
     * @Route("/forum/p/{post?}", name="showPost", priority="2")
     */
    public function showPost(String $post): Response
    {
        $commentRepository = $this->getDoctrine()->getRepository(Comment::class);
        /** @var Comment $comment */ $comment = $commentRepository->find($post);

        $comment->getAnswers()->initialize();
        return $this->render('forum/post.html.twig', [
            'comment' => $comment,
            'parents' => $this->getParents($comment->getCategory()),
        ]);
    }

    /**
     * @Route("/forum/p/{post}/send", name="sendPost", methods={"POST"})
     */
    public function sendPost(String $post, Request $request): RedirectResponse
    {
        $commentRepository = $this->getDoctrine()->getRepository(Comment::class);
        $parent = $commentRepository->find($post);
        $userCommentRepository = $this->getDoctrine()->getRepository(UserComment::class);

        $email = $request->get("email");
        $isAnonyme = $request->get("anonyme");
        $content = $request->get("content");

        if ($content == null)
        {
            return $this->redirectToRoute("showPost", [
                'post' => $post,
            ]);
        }

        if ($email == null)
        {
            return $this->redirectToRoute("showPost", [
                'post' => $post,
            ]);
        }

        $user = $userCommentRepository->findOneBy(array("email" => $email));
        if ($user == null) {
            $user = new UserComment();
        }
        $user->setEmail($email);

        $comment = new Comment();

        if ($isAnonyme != null && $isAnonyme == "no") {
            $username = $request->get("username");

            if ($username != null)
            {
                $comment->setAnonyme(false);
                $user->setUsername($username);
            }
        }

        $user->setIp($request->getClientIp());
        $this->getDoctrine()->getManager()->persist($user);

        $comment->setContent($content);
        $comment->setUserComment($user);
        $comment->setTitle($parent->getTitle());
        $comment->setParents($parent);
        $comment->setDate(new \DateTime());
        $comment->setCategory($parent->getCategory());

        $this->getDoctrine()->getManager()->persist($comment);
        $this->getDoctrine()->getManager()->flush();

        return $this->redirectToRoute("showPost", [
            'post' => $post,
        ]);
    }

    /**
     * @Route("/forum/c/{category?}/new", name="newPost", methods={"POST"})
     */
    public function newPost(String $category, Request $request): RedirectResponse
    {
        $forumCategoryRepository = $this->getDoctrine()->getRepository(ForumCategory::class);
        $category = $forumCategoryRepository->findOneBy(array("id" => $category));

        $userCommentRepository = $this->getDoctrine()->getRepository(UserComment::class);

        $email = $request->get("email");
        $isAnonyme = $request->get("anonyme");
        $content = $request->get("content");
        $title = $request->get("title");

        if ($content == null)
        {
            return $this->redirectToRoute("showCategory", [
                "category" => $category
            ]);
        }

        if ($email == null)
        {
            return $this->redirectToRoute("showCategory", [
                "category" => $category
            ]);
        }

        $user = $userCommentRepository->findOneBy(array("email" => $email));
        if ($user == null) {
            $user = new UserComment();
        }
        $user->setEmail($email);

        $comment = new Comment();

        if ($isAnonyme != null && $isAnonyme == "no") {
            $username = $request->get("username");

            if ($username != null)
            {
                $comment->setAnonyme(false);
                $user->setUsername($username);
            }
        }

        $image = $request->get('image');

        switch ($image) {
            default:
                $image = "grenouilleContent.svg";
                break;
            case 1:
                $image = "grenouilleAsk.svg";
                break;
            case 2:
                $image = "grenouilleBuy.svg";
                break;
            case 3:
                $image = "grenouilleMaintenance.svg";
                break;
            case 4:
                $image = "grenouilleVenere.svg";
                break;
        }

        $comment->setImage($image);

        $user->setIp($request->getClientIp());
        $this->getDoctrine()->getManager()->persist($user);

        $comment->setContent($content);
        $comment->setUserComment($user);
        $comment->setTitle($title);
        $comment->setDate(new \DateTime());
        $comment->setCategory($category);

        $this->getDoctrine()->getManager()->persist($comment);
        $this->getDoctrine()->getManager()->flush();

        return $this->redirectToRoute("showPost", [
            "post" => $comment->getId()
        ]);

    }

    /**
     * @Route("/forum/c/{category?}/new", name="newPostForm", methods={"GET"})
     */
    public function newPostForm(String $category, Request $request): Response {
        $categoriesRepository = $this->getDoctrine()->getRepository(ForumCategory::class);
        $parentCategory = $categoriesRepository->find($category);

        return $this->render("forum/newPost.html.twig", [
            'parents' => $this->getParents($parentCategory)
        ]);
    }

    private function getParents(ForumCategory $category): ArrayCollection
    {
        $categories = new ArrayCollection();
        while ($category->getMainCategory() != null)
        {
            $categories->add($category);
            $category = $category->getMainCategory();
        }
        $categories->add($category);

        return $categories;
    }
}
