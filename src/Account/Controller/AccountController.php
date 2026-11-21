<?php

namespace App\Account\Controller;

use App\Account\Entity\User;
use App\Account\Form\AccountType;
use App\Account\Form\ChangePasswordType;
use App\Account\Form\ThemeType;
use App\History\Repository\ListenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account')]
    public function index(#[CurrentUser] User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, ListenRepository $listenRepository): Response
    {
        $accountForm = $this->createForm(AccountType::class, $user);
        $passwordForm = $this->createForm(ChangePasswordType::class);
        $themeForm = $this->createForm(ThemeType::class, $user);

        $accountForm->handleRequest($request);
        $passwordForm->handleRequest($request);
        $themeForm->handleRequest($request);

        if ($accountForm->isSubmitted() && $accountForm->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Informations enregistrées.');

            return $this->redirectToRoute('app_account');
        }

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $password = $passwordForm->get('newPassword')->getData();

            $passwordHash = $passwordHasher->hashPassword($user, $password);

            $user->setPassword($passwordHash);

            $em->flush();

            $this->addFlash('success', 'Mot de passe changé.');

            return $this->redirectToRoute('app_account');
        }

        if ($themeForm->isSubmitted() && $themeForm->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_account');
        }

        if ($accountForm->isSubmitted()) {
            $em->refresh($user);
        }

        return $this->render('account/index.html.twig', [
            'accountForm' => $accountForm,
            'passwordForm' => $passwordForm,
            'themeForm' => $themeForm,
            // The link to « Mes écoutes ».
            'nbListens' => $listenRepository->count(['user' => $user]),
            'listenedSeconds' => $listenRepository->listenedSeconds(null, $user),
        ]);
    }
}
