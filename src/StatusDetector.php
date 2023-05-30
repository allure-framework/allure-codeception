<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExceptionWrapper;
use PHPUnit\Framework\SkippedTestError;
use Qameta\Allure\Model\Status;
use Qameta\Allure\Model\StatusDetails;
use Qameta\Allure\Setup\StatusDetectorInterface;
use Throwable;

/**
 * @deprecated This class is not used anymore and will be removed in next major version.
 * @psalm-suppress UnusedClass
 */
final class StatusDetector implements StatusDetectorInterface
{
    public function __construct(
        private StatusDetectorInterface $defaultStatusDetector,
    ) {
    }

    public function getStatus(Throwable $error): ?Status
    {
        return $this->getUnwrappedStatus(
            $this->unwrapError($error),
        );
    }

    private function getUnwrappedStatus(Throwable $error): Status
    {
        return match (true) {
            $error instanceof SkippedTestError => Status::skipped(),
            $error instanceof AssertionFailedError => Status::failed(),
            default => Status::broken(),
        };
    }

    public function getStatusDetails(Throwable $error): ?StatusDetails
    {
        $unwrappedError = $this->unwrapError($error);
        $unwrappedStatus = $this->getUnwrappedStatus($unwrappedError);

        return match (true) {
            Status::skipped() === $unwrappedStatus,
            Status::failed() === $unwrappedStatus => new StatusDetails(
                message: $error->getMessage(),
                trace: $error->getTraceAsString(),
            ),
            default => $this->defaultStatusDetector->getStatusDetails($unwrappedError),
        };
    }

    private function unwrapError(Throwable $error): Throwable
    {
        /** @psalm-suppress InternalMethod */
        return $error instanceof ExceptionWrapper
            ? $error->getOriginalException() ?? $error
            : $error;
    }

    private function buildMessage(Throwable $error): string
    {
        /** @psalm-suppress InternalMethod */
        return $error instanceof AssertionFailedError
            ? $error->toString()
            : $error->getMessage();
    }
}
