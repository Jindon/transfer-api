<?php

declare(strict_types=1);

namespace App\Tests\Transfer\Integration\Presentation\Http\Api\Request;

use App\Shared\Money\Currency;
use App\Transfer\Presentation\Http\Api\Request\TransferRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TransferRequestTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    public function setup(): void
    {
        parent::setup();

        self::bootKernel();

        $this->validator = static::getContainer()->get('validator');
    }

    public function testValidRequest(): void
    {
        $request = new TransferRequest(
            sourceAccountUuid: (string) Uuid::v7(),
            destinationAccountUuid: (string) Uuid::v7(),
            amount: 1000,
            currency: Currency::EUR,
            reference: 'refXXX',
        );

        $violations = $this->validator->validate($request);

        self::assertCount(0, $violations);
    }

    public function testSourceAndDestinationMustDiffer(): void
    {
        $uuid = (string) Uuid::v7();

        $request = new TransferRequest(
            sourceAccountUuid: $uuid,
            destinationAccountUuid: $uuid,
            amount: 1000,
            currency: Currency::EUR,
        );

        $violations = $this->validator->validate($request);

        self::assertCount(1, $violations);
        self::assertSame(
            'Source and destination must be different.',
            $violations[0]->getMessage()
        );
    }

    #[DataProvider('invalidRequests')]
    public function testValidationFails(
        TransferRequest $request,
        string $property,
    ): void {
        $violations = $this->validator->validate($request);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame($property, $violations[0]->getPropertyPath());
    }

    public static function invalidRequests(): iterable
    {
        return [
            'invalid source uuid' => [
                new TransferRequest(
                    'invalid',
                    (string) Uuid::v7(),
                    1000,
                    Currency::EUR,
                ),
                'sourceAccountUuid',
            ],
            'negative amount' => [
                new TransferRequest(
                    (string) Uuid::v7(),
                    (string) Uuid::v7(),
                    -100,
                    Currency::EUR,
                ),
                'amount',
            ],
            'invalid currency' => [
                new TransferRequest(
                    (string) Uuid::v7(),
                    (string) Uuid::v7(),
                    1000,
                    'ABCX'
                ),
                'currency',
            ],
        ];
    }
}
