<?php

namespace App\Controller;

use App\Application\Dashboard\DashboardReadModel;
use App\Entity\ActiveRoute;
use App\Entity\User;
use App\Repository\ActiveRouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(private readonly DashboardReadModel $dashboard)
    {
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function page(): Response
    {
        $data = $this->data();
        $json = htmlspecialchars(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = htmlspecialchars((string) ($data['user']['displayName'] ?? 'Dashboard'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return new Response('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>ED Trade Routes - '.$title.'</title></head><body><main><h1>ED Trade Routes</h1><section id="dashboard" data-dashboard="'.$json.'"><h2>Current leg</h2><pre>'.htmlspecialchars(json_encode($data['activeRoute'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</pre><h2>Route metrics</h2><pre>'.htmlspecialchars(json_encode($data['metrics'], JSON_PRETTY_PRINT), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</pre><h2>Plugin and cargo</h2><pre>'.htmlspecialchars(json_encode(['plugin' => $data['plugin'], 'cargo' => $data['cargo']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</pre></section></main></body></html>');
    }

    #[Route('/api/dashboard', name: 'dashboard_json', methods: ['GET'])]
    public function json(): JsonResponse
    {
        return $this->json($this->data());
    }

    #[Route('/api/dashboard/alternative', name: 'dashboard_select_alternative', methods: ['POST'])]
    public function selectAlternative(Request $request, ActiveRouteRepository $activeRoutes, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->authenticatedUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'authentication_required'], 401);
        }

        try {
            $payload = $request->toArray();
            $identifier = $payload['routeIdentifier'] ?? null;
            $legs = $payload['legs'] ?? null;
            if (!is_string($identifier) || $identifier === '' || !is_array($legs) || !array_is_list($legs) || $legs === []) {
                throw new \InvalidArgumentException('routeIdentifier and a non-empty ordered legs array are required.');
            }
            foreach ($legs as $leg) {
                if (!is_array($leg)) {
                    throw new \InvalidArgumentException('Every route leg must be an object.');
                }
            }
        } catch (\Throwable $exception) {
            return $this->json(['error' => 'invalid_alternative', 'message' => $exception->getMessage()], 422);
        }

        $active = $activeRoutes->findForUser($user);
        if (!$active instanceof ActiveRoute) {
            $active = new ActiveRoute($user, $identifier, $legs);
            $activeRoutes->save($active);
            $selection = 'activated';
        } elseif ($active->isCurrentLegBound()) {
            $active->replaceFollowingLegs($legs);
            $selection = 'deferred';
        } else {
            $active->replaceRoute($identifier, $legs);
            $selection = 'activated';
        }
        $entityManager->flush();

        return $this->json(['selection' => $selection, 'activeRoute' => $this->dashboard->forUser($user)['activeRoute']]);
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        $user = $this->authenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication is required.');
        }
        return $this->dashboard->forUser($user);
    }

    private function authenticatedUser(): ?User
    {
        $user = $this->getUser();
        return $user instanceof User ? $user : null;
    }
}
