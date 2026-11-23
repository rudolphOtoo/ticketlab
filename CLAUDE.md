# Working on TicketLab

## Commits

- The user runs every git write (commit, push, merge, rebase, reset, branches, PRs). Leave changes uncommitted and
  hand over the `git add` file list and a commit message.
- Commit messages have **no trailers**: no `Co-Authored-By:` and no `Claude-Session:` lines, nor any other
  attribution line.
- Small commits, one topic each; stay on the current branch unless told otherwise. No deploys.

## Every commit must be green

- `vendor/bin/mago format --check`, `vendor/bin/mago lint`, `vendor/bin/mago analyze`
- `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` (Larastan)
- `vendor/bin/pest --testsuite=Unit,Feature --parallel`, `vendor/bin/pest tests/Browser` (after `npm run build`)
- `npm run test:js`

## Ground rules

- Investigate and report the root cause with each fix; measure before and after for performance work.
- No major-version dependency upgrades without asking.
- Never write real credentials into tracked files; `.env.example` keeps placeholders only.
- Livewire's JavaScript is bundled from `resources/js/vendor/livewire.esm.js` (Vercel builds before Composer runs).
  `composer update` refreshes that copy; commit it with the lock file, or `LivewireBundleTest` fails CI.
