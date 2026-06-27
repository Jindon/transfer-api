<?php

declare(strict_types=1);

namespace App\Tests\Security\Unit;

use App\Security\ApiKeyAuthenticator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticatorTest extends TestCase
{
    private const string API_KEY = 'secret-api-key';

    private ApiKeyAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new ApiKeyAuthenticator(self::API_KEY);
    }

    public function testSupportsReturnsTrueWhenHeaderExists(): void
    {
        $request = new Request();
        $request->headers->set('X-Api-Key', self::API_KEY);

        $this->assertTrue($this->authenticator->supports($request));
    }

    public function testSupportsReturnsFalseWhenHeaderMissing(): void
    {
        $this->assertFalse(
            $this->authenticator->supports(new Request())
        );
    }

    public function testAuthenticateReturnsPassport(): void
    {
        $request = new Request();
        $request->headers->set('X-Api-Key', self::API_KEY);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $this->assertTrue($passport->hasBadge(UserBadge::class));
    }

    #[DataProvider('invalidApiKeys')]
    public function testAuthenticateThrowsAuthenticationException(?string $apiKey): void
    {
        $request = new Request();

        if (null !== $apiKey) {
            $request->headers->set('X-Api-Key', $apiKey);
        }

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessageIs('Invalid Api Key');

        $this->authenticator->authenticate($request);
    }

    public static function invalidApiKeys(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'invalid' => ['wrong-key'],
        ];
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAuthenticationSuccessReturnsNull(): void
    {
        $response = $this->authenticator->onAuthenticationSuccess(
            new Request(),
            $this->createMock(TokenInterface::class),
            'main',
        );

        $this->assertNull($response);
    }

    public function testAuthenticationFailureReturnsUnauthorizedResponse(): void
    {
        $response = $this->authenticator->onAuthenticationFailure(
            new Request(),
            new AuthenticationException('Invalid Api Key'),
        );

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'type' => 'unauthorized',
                'detail' => 'Invalid Api Key',
                'status' => Response::HTTP_UNAUTHORIZED,
            ]),
            $response->getContent(),
        );
    }
}
