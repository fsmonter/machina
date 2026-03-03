# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Fluent state machine builder API
- `HasStateMachine` trait for Eloquent models
- Integer and string backed enum support
- Transition guards with closures
- Query scopes (`whereState`, `whereNotState`)
- `StateTransitioned` event
- Serialization support (`toArray` / `fromArray`)
