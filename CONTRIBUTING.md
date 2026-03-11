# Contributing to Temant Container

Thank you for considering a contribution! This document outlines the guidelines for contributing to this project.

## Requirements

- PHP 8.2 or higher
- [Composer](https://getcomposer.org/)

## Getting Started

1. Fork the repository and clone your fork:

   ```bash
   git clone git@github.com:YOUR_USERNAME/Temant-Container.git
   cd Temant-Container
   ```

2. Install dependencies:

   ```bash
   composer install
   ```

3. Verify everything works:

   ```bash
   composer test
   ```

## Development Workflow

### Branching

- Create a feature branch from `main`:

  ```bash
  git checkout -b feature/your-feature-name
  ```

- Use descriptive branch names: `feature/add-tagged-singletons`, `fix/circular-binding-detection`, `docs/update-readme`.

### Making Changes

1. **Write code** in the `Src/` directory following the existing structure.
2. **Write tests** in the `Tests/` directory for every change.
3. **Run the full suite** before committing:

   ```bash
   composer test
   ```

### Commit Messages

Use clear, descriptive commit messages following the conventional commits style:

```
feat: add tagged singleton support
fix: resolve circular binding detection edge case
refactor: extract parameter resolution into dedicated methods
test: add coverage for nullable built-in parameters
docs: update README with extend() examples
```

## Coding Standards

### PHP Style

- Use `declare(strict_types=1)` in every PHP file.
- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style.
- Use typed properties, parameters, and return types everywhere.
- Use `readonly` for properties that should not change after construction.
- Use `final` on classes that are not designed for extension.

### DocBlocks

- Every public method must have a complete PHPDoc block with `@param`, `@return`, and `@throws` annotations.
- Every class must have a class-level docblock explaining its purpose.
- Omit `@param` / `@return` types only when they add nothing beyond the native type declaration.

### Architecture

- Keep the public API surface small. Prefer private methods for internal logic.
- Maintain PSR-11 compliance: all container exceptions must implement `ContainerExceptionInterface`.
- New features should not break existing tests or the public API without discussion.
- Avoid adding dependencies. The library intentionally has zero runtime dependencies beyond `psr/container`.

## Testing

### Running Tests

```bash
# PHPUnit tests only
composer phpunit

# PHPStan static analysis only
composer phpstan

# Both
composer test
```

### Writing Tests

- Place tests in `Tests/`, mirroring the `Src/` directory structure.
- Use the `Tests\Temant\Container` namespace (not the production namespace).
- Use PHPUnit `#[Test]` attributes instead of the `test` method name prefix.
- Place test fixtures in `Tests/Fixtures/`.
- Each test method should test a single behavior and have a descriptive name.
- Test both success paths and error paths (expected exceptions).

### Test Structure Example

```php
<?php

declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;

final class YourFeatureTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    #[Test]
    public function descriptiveTestName(): void
    {
        // Arrange
        $this->container->set('id', fn() => new \stdClass());

        // Act
        $result = $this->container->get('id');

        // Assert
        self::assertInstanceOf(\stdClass::class, $result);
    }
}
```

## Static Analysis

The project uses [PHPStan](https://phpstan.org/) at the maximum level (`max`). All code in `Src/` must pass without errors:

```bash
composer phpstan
```

If you believe a PHPStan error is a false positive, document your reasoning in the PR. Do not add `@phpstan-ignore` annotations without discussion.

## Pull Requests

1. Ensure all tests pass and PHPStan reports no errors.
2. Keep PRs focused: one feature or fix per PR.
3. Write a clear PR description explaining **what** changed and **why**.
4. Reference any related issues (e.g., "Closes #12").
5. Be open to feedback -- code review is a collaborative process.

### PR Checklist

- [ ] Tests pass (`composer test`)
- [ ] New/changed code has tests
- [ ] PHPStan passes at max level
- [ ] DocBlocks are complete on public methods
- [ ] No unnecessary dependencies added
- [ ] Commit messages are clear and descriptive

## Reporting Issues

When reporting a bug, please include:

- PHP version (`php -v`)
- Package version (`composer show temant/container`)
- A minimal code example that reproduces the issue
- Expected behavior vs. actual behavior
- Full stack trace if applicable

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
