<?php

declare(strict_types=1);

use Tests\Models\TestModel;
use Tests\TestState;

beforeEach(function () {
    TestModel::create(['state' => TestState::Pending]);
    TestModel::create(['state' => TestState::Processing]);
    TestModel::create(['state' => TestState::Completed]);
    TestModel::create(['state' => TestState::Failed]);
    TestModel::create(['state' => TestState::Cancelled]);
});

it('filters by single state', function () {
    $models = TestModel::whereState(TestState::Pending)->get();

    expect($models)->toHaveCount(1);
    expect($models->first()->state)->toBe(TestState::Pending);
});

it('filters by multiple states', function () {
    $models = TestModel::whereState(TestState::Completed, TestState::Failed)->get();

    expect($models)->toHaveCount(2);
    $states = $models->pluck('state')->all();
    expect($states)->toContain(TestState::Completed);
    expect($states)->toContain(TestState::Failed);
});

it('excludes by single state', function () {
    $models = TestModel::whereNotState(TestState::Pending)->get();

    expect($models)->toHaveCount(4);
    $states = $models->pluck('state')->all();
    expect($states)->not->toContain(TestState::Pending);
});

it('excludes by multiple states', function () {
    $models = TestModel::whereNotState(TestState::Completed, TestState::Failed, TestState::Cancelled)->get();

    expect($models)->toHaveCount(2);
    $states = $models->pluck('state')->all();
    expect($states)->toContain(TestState::Pending);
    expect($states)->toContain(TestState::Processing);
});
