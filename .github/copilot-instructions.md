# GitHub Copilot Instructions

Use these repository instructions when helping with code, commits, or pull requests in this repository.

## Release and milestone discipline

- Follow the milestone-driven workflow described in `docs/release-playbook.md`.
- Keep work aligned to one milestone at a time.
- Do not bundle unrelated feature work into one change.

## Commit guidance

When suggesting a commit message, prefer:

```text
type(scope): short summary
```

Examples:

- `fix(orm): avoid rolling back shared SQLite transactions on disconnect`
- `docs(core): define 1.0.0 as the minimum viable framework identity`
- `test(console): cover SQLite migration down selection`

Preferred types:

- `fix`
- `feature`
- `docs`
- `test`
- `refactor`
- `chore`

Keep commits small and single-purpose.

## Pull request guidance

When suggesting or drafting a pull request, use these sections:

- `Milestone`
- `Type`
- `Why this belongs in this milestone`
- `What changed`
- `What did not change`
- `Verification`
- `User impact`
- `Release notes`
- `Upgrade notes`

Keep the PR body concise and milestone-focused.

## Verification guidance

When suggesting verification steps:

- prefer the real repo checks that matter for the touched area
- include `composer analyze` when working in repos where static analysis is part of CI
- do not claim work is green unless the relevant checks actually passed

## 1.0.0 framing

Treat `1.0.0` as the minimum viable identity of AssegaiPHP, not the complete list of everything the framework could eventually become.

Do not frame optional or future expansion work as a `1.0.0` blocker unless the repository guidance clearly says it is identity-defining.
