<?php

namespace App\Playback\Controller;

use App\Account\Entity\User;
use App\Playback\Entity\PlaybackState;
use App\Playback\Repository\PlaybackStateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PlaybackStateController extends AbstractController
{
    #[Route('/playback/state', name: 'app_playback_state_save', methods: ['POST'])]
    public function save(Request $request, #[CurrentUser] User $user, PlaybackStateRepository $playbackStateRepository, EntityManagerInterface $em): Response
    {
        $data = $request->toArray();

        if (!$this->isCsrfTokenValid('playback_state', $data['_token'] ?? '')) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $queue = $this->cleanIds($data['queue'] ?? []);
        $sourceQueue = $this->cleanIds($data['sourceQueue'] ?? []);

        $currentIndex = (int) ($data['currentIndex'] ?? 0);
        if ($currentIndex < 0 || $currentIndex >= count($queue)) {
            $currentIndex = 0;
        }

        $position = max(0, (int) ($data['position'] ?? 0));
        $shuffle = (bool) ($data['shuffle'] ?? false);

        $repeatMode = $data['repeatMode'] ?? 'off';
        if (!in_array($repeatMode, ['off', 'all', 'one'], true)) {
            $repeatMode = 'off';
        }

        $state = $playbackStateRepository->findOneBy(['user' => $user]);

        if (null === $state) {
            $state = new PlaybackState($user);
            $em->persist($state);
        }

        $state->update($queue, $sourceQueue, $currentIndex, $position, $shuffle, $repeatMode);
        $em->flush();

        return new Response(null, 204);
    }

    private function cleanIds(mixed $ids): array
    {
        $ids = array_map(intval(...), (array) $ids);
        $ids = array_filter($ids, fn (int $id) => $id > 0);

        return array_slice(array_values($ids), 0, 500);
    }
}
