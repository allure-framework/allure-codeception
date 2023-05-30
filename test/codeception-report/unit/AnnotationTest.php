<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Report\Unit;

use Codeception\Test\Unit;
use Qameta\Allure\Attribute;

class AnnotationTest extends Unit
{
    #[Attribute\DisplayName('Test title')]
    public function testTitleAnnotation(): void
    {
        $this->expectNotToPerformAssertions();
    }

    #[Attribute\Description('Test description with `markdown`')]
    public function testDescriptionAnnotation(): void
    {
        $this->expectNotToPerformAssertions();
    }

    #[Attribute\Severity(Attribute\Severity::MINOR)]
    public function testSeverityAnnotation(): void
    {
        $this->expectNotToPerformAssertions();
    }

    #[Attribute\Parameter('foo', 'bar')]
    public function testParameterAnnotation(): void
    {
        $this->expectNotToPerformAssertions();
    }

    #[Attribute\Story('Story 1')]
    #[Attribute\Story('Story 2')]
    public function testStoriesAnnotation(): void
    {
        $this->expectNotToPerformAssertions();
    }

    #[Attribute\Feature('Feature 1')]
    #[Attribute\Feature('Feature 2')]
    public function testFeaturesAnnotation(): void
    {
        $this->expectNotToPerformAssertions();
    }
}
