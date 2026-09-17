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
        return $this->render('dashboard.html.twig');
    }

    #[Route('/api/dashboard', name: 'dashboard_json', methods: ['GET'])]
    public function dashboardJson(): JsonResponse
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
            $payload = $request->getContentTypeFormat() === 'json' ? $request->toArray() : $request->request->all();
            $identifier = $payload['routeIdentifier'] ?? null;
            if (!is_string($identifier) || $identifier === '') {
                throw new \InvalidArgumentException('routeIdentifier is required.');
            }
        } catch (\Throwable $exception) {
            return $this->json(['error' => 'invalid_alternative', 'message' => $exception->getMessage()], 422);
        }

        $active = $activeRoutes->findForUser($user);
        if (!$active instanceof ActiveRoute) {
            return $this->json(['error' => 'alternative_not_found'], 404);
        }
        $alternative = array_values(array_filter(
            $active->getAlternatives(),
            static fn (array $candidate): bool => ($candidate['routeIdentifier'] ?? null) === $identifier,
        ))[0] ?? null;
        if (!is_array($alternative) || !isset($alternative['legs']) || !is_array($alternative['legs'])) {
            return $this->json(['error' => 'alternative_not_found'], 404);
        }
        $legs = $alternative['legs'];
        if ($active->isCurrentLegBound()) {
            $active->replaceFollowingLegs(array_slice($legs, $active->getCurrentLegIndex() + 1));
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
