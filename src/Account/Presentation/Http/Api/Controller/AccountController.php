<?php

declare(strict_types=1);

namespace App\Account\Presentation\Http\Api\Controller;

use App\Account\Domain\Exception\AccountNotFoundException;
use App\Account\Domain\Repository\AccountRepositoryInterface;
use App\Account\Presentation\Http\Api\Response\AccountResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
    ) {
    }

    #[Route('/api/accounts', name: 'list_accounts', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $accounts = array_map(
            AccountResponse::fromEntity(...),
            $this->accountRepository->findAll(),
        );

        return $this->json($accounts);
    }

    #[Route('/api/accounts/{uuid}', name: 'show_account', methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $account = $this->accountRepository->findByUuid($uuid)
            ?? throw new AccountNotFoundException($uuid);

        return $this->json(AccountResponse::fromEntity($account));
    }
}
