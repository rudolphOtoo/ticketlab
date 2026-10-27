# Contributing to TicketLab

Thank you for your interest in contributing to TicketLab! This document outlines the process for contributing to this project.

## Branching Strategy

We follow a Git Flow-inspired branching strategy:

- `main` - Production-ready code. All releases are tagged from this branch.
- `feature/*` - New features (e.g., `feature/payment-integration`)
- `fix/*` - Bug fixes (e.g., `fix/ticket-validation`)
- `hotfix/*` - Critical production fixes (e.g., `hotfix/security-patch`)
- `refactor/*` - Code refactoring without changing functionality
- `chore/*` - Maintenance tasks, dependency updates

## Commit Message Convention

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

**Types:**
- `feat` - A new feature
- `fix` - A bug fix
- `docs` - Documentation only changes
- `style` - Changes that do not affect the meaning of the code (formatting, etc.)
- `refactor` - A code change that neither fixes a bug nor adds a feature
- `perf` - A code change that improves performance
- `test` - Adding missing tests or correcting existing tests
- `chore` - Changes to the build process or auxiliary tools
- `ci` - Changes to CI configuration files and scripts
- `security` - Security fixes or improvements

**Examples:**
```
feat(zones): add zone-scoped query filtering
fix(paystack): correct fee calculation rounding
docs: update README with setup instructions
```

## Pull Request Process

1. **Create a branch** from `main` following the naming convention above
2. **Make your changes** with clear, atomic commits
3. **Write/update tests** for any new functionality
4. **Run quality checks** locally:
   ```bash
   composer lint:check
   composer types:check
   php artisan test
   ```
5. **Update documentation** if necessary (README, code comments)
6. **Submit a PR** with:
   - Clear title following Conventional Commits
   - Description of changes and motivation
   - Reference to any related issues
   - Screenshots for UI changes
7. **Code review** - Address feedback from reviewers
8. **Merge** - PRs require passing CI checks and approval

## Code Standards

- **PHP**: Follow PSR-12 standards, enforced by Laravel Pint
- **Type Safety**: Use type hints, return types, and proper PHPDoc where appropriate
- **Testing**: Write tests for new features and bug fixes
- **Security**: Never commit secrets, API keys, or credentials. Use environment variables
- **Database**: Use migrations for schema changes, seeders for test data
- **Architecture**: Follow Laravel conventions and existing project patterns

## Getting Help

If you have questions, please open an issue with the `question` label.
