# Contributing to TicketLab

Thank you for your interest in contributing to TicketLab! This document outlines the process for contributing to this project.

## Branching Strategy

We follow an adapted Git Flow branching strategy anchored on `dev`:

- `main` - Production-ready, locked releases only.
- `dev` - Active integration branch and shared foundation baseline. All feature branches originate from and merge back into `dev`.
- `feature/*` - New features (e.g., `feature/track-1-checkout`, `feature/track-2-scanner`)
- `fix/*` - Bug fixes (e.g., `fix/ticket-validation`)
- `hotfix/*` - Critical production fixes branching directly from `main`
- `refactor/*` - Code refactoring without changing functionality
- `chore/*` - Maintenance tasks, tooling and dependency updates

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

1. **Create a branch** from `dev` following the naming convention above.
2. **Make your changes** in small, focused increments following `PACE: BITE`.
3. **Write/update tests** for any new functionality (Pest).
4. **Run quality checks** locally:
```bash
composer lint:check
composer types:check
php artisan test
```
5. **Update documentation** if necessary ([README.md](file:///c:/Users/Fafa/Desktop/CodeSpace/ticketlab/README.md), [plan/MASTER_PLAN.md](file:///c:/Users/Fafa/Desktop/CodeSpace/ticketlab/plan/MASTER_PLAN.md)).
6. **Submit a PR** targeting `dev` with:
- Clear title following Conventional Commits
- Description of changes and motivation
- Reference to any related tickets
- Screenshots or recordings for UI changes
7. **Code review** - Address feedback from peer reviewer.
8. **Merge** - PRs require passing local checks and peer approval before merging into `dev`.

## Code Standards

- **PHP**: Strictly PHP 8.5+, following PER-CS / PSR-12 standards enforced by **Mago** (`carthage-software/mago`).
- **Type Safety**: Explicit return type declarations, parameter type hints, and Mago static analysis.
- **Testing**: Comprehensive Pest tests for happy, failure, and edge paths.
- **Security**: Never commit secrets or API keys. Keep environment configurations isolated.
- **Database**: Additive migrations only after Phase 0. No destructive schema updates.
- **Architecture**: Follow Laravel 13, Livewire 4, and single-database multi-tenancy conventions.

## Getting Help

If you have questions, please open an issue or consult the team in your active workspace channel.
