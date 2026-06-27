<?php

declare(strict_types=1);

namespace App\Tests\Security\Unit;

use App\Security\ApiKeyEntryPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class ApiKeyEntryPointTest extends TestCase
{
    public function testStartReturnsUnauthorizedResponse(): void
    {
        $entryPoint = new ApiKeyEntryPoint();

        $response = $entryPoint->start(
            new Request(),
            new AuthenticationException(),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        self::assertJsonStringEqualsJsonString(
            json_encode([
                'type' => 'unauthorized',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'X-Api-Key header is required',
            ]),
            $response->getContent(),
        );
    }
}
