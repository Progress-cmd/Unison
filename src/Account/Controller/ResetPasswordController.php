<?php

namespace App\Account\Controller;

use App\Account\Entity\User;
use App\Account\Form\ResetPasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

// The page reached from the email link, open to people who aren't logged in.
final class ResetPasswordController extends AbstractController
{
    private const SESSION_KEY = 'reset_password_token';

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
    ) {
    }

    // The link of the email. The token goes into the session and leaves the address bar,
    // so it can't leak through the browser history or to another site.
    #[Route('/reset-password/{token}', name: 'app_reset_password_token', methods: ['GET'])]
    public function storeToken(Request $request, string $token): Response
    {
        $request->getSession()->set(self::SESSION_KEY, $token);

        return $this->redirectToRoute('app_reset_password');
    }

    #[Route('/reset-password', name: 'app_reset_password')]
    public function reset(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $session = $request->getSession();
        $token = $session->get(self::SESSION_KEY);

        if (null === $token) {
            return $this->redirectToRoute('app_login');
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            $session->remove(self::SESSION_KEY);
            $this->addFlash('error', 'Ce lien n\'est plus valable : il a déjà servi ou il a plus de 7 jours. Demande-en un nouveau.');

            return $this->redirectToRoute('app_login');
        }

        // A link sent before the account was disabled.
        if ($user->isDisabled()) {
            $session->remove(self::SESSION_KEY);
            $this->addFlash('error', 'Ce compte est désactivé.');

            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A link works only once.
            $this->resetPasswordHelper->removeResetRequest($token);
            $session->remove(self::SESSION_KEY);

            $user->setPassword($hasher->hashPassword($user, $form->get('newPassword')->getData()));
            $em->flush();

            $this->addFlash('success', 'Mot de passe enregistré. Tu peux te connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('account/reset_password.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }
}
