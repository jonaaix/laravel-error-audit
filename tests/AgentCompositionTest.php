<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Agents\ErrorAuditAgent;
use Aaix\LaravelErrorAudit\Enums\IssueCategoryEnum;
use Aaix\LaravelErrorAudit\Enums\UrgencyEnum;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;

it('composes its traits without a method collision', function (string $agent): void {
   $instance = new $agent;

   expect($instance)->toBeInstanceOf(Agent::class)
      ->and($instance)->toBeInstanceOf(HasStructuredOutput::class)
      ->and(method_exists($instance, 'prompt'))->toBeTrue()
      ->and($instance->totalCostUsd())->toBe(0.0)
      ->and($instance->lastCost())->toBeNull();
})->with([ErrorAuditAgent::class]);

it('keeps a prompt signature the contract accepts', function (string $agent): void {
   $declared = (new ReflectionMethod($agent, 'prompt'))->getDeclaringClass()->getName();

   expect($declared)->toBe($agent);
})->with([ErrorAuditAgent::class]);

it('tracks its own cost through the shared trait', function (string $agent): void {
   $instance = new $agent;

   expect($instance->costs())->toBe([]);

   $instance->resetCosts();

   expect($instance->totalCostUsd())->toBe(0.0);
})->with([ErrorAuditAgent::class]);

it('states the instructions it will send', function (string $agent): void {
   expect((string) (new $agent)->instructions())->not->toBeEmpty();
})->with([ErrorAuditAgent::class]);

it('constrains the assessment to the known urgencies and categories', function (): void {
   $schema = (new ErrorAuditAgent)->schema(new JsonSchemaTypeFactory);

   expect(array_keys($schema))
      ->toBe(['urgency', 'category', 'title', 'likelyCause', 'suggestedAction'])
      ->and($schema['urgency']->toArray()['enum'])
      ->toBe(array_column(UrgencyEnum::cases(), 'value'))
      ->and($schema['category']->toArray()['enum'])
      ->toBe(array_column(IssueCategoryEnum::cases(), 'value'));
});
