<?php

declare(strict_types=1);

use Machina\Exceptions\InvalidStateTransitionException;

it('can be instantiated', function () {
    $exception = new InvalidStateTransitionException('Test message');

    expect($exception)->toBeInstanceOf(InvalidStateTransitionException::class);
    expect($exception)->toBeInstanceOf(Exception::class);
    expect($exception->getMessage())->toBe('Test message');
});

it('can be instantiated with code and previous exception', function () {
    $previous = new Exception('Previous exception');
    $exception = new InvalidStateTransitionException('Test message', 100, $previous);

    expect($exception->getMessage())->toBe('Test message');
    expect($exception->getCode())->toBe(100);
    expect($exception->getPrevious())->toBe($previous);
});

it('can be thrown', function () {
    expect(fn () => throw new InvalidStateTransitionException('Cannot transition'))
        ->toThrow(InvalidStateTransitionException::class, 'Cannot transition');
});
