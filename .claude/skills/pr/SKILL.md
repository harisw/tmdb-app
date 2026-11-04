---
name: pr
description: Draft a PR description from this branch and session context, then open or update the PR with gh.
disable-model-invocation: true
---

# Open a pull request

## 1. Check the branch

- Stop if the current branch is `master`; ask for a branch name.
- If `git status --porcelain` shows uncommitted changes, list them and ask whether to commit first. Never commit
  unasked.
- Run `gh pr view --json number,url,body` to find an existing PR for this branch.

## 2. Gather the change

```bash
git log --oneline master..HEAD
git diff --stat master...HEAD
git diff master...HEAD -- . ':!package-lock.json' ':!composer.lock' ':!public/build'
```

The reason for the change comes from this conversation. If the session holds no record of why, ask the user one
question about motivation instead of guessing.

## 3. Write the title and body

Title: conventional-commit style under 70 characters (`feat:`, `fix:`, `refactor:`, `chore:`, `ci:`, `docs:`).

Body, omitting any section with nothing true to say:

```markdown
## <one-line outcome>

<one or two sentences on what this changes for the app>

### Why

<the problem, with evidence: numbers, logs, links>

### Changes

- <grouped by area (backend, frontend, CI, infra), one line each, naming the key file>

### Verification

- <commands run in this session and their results>

### Rollout

- <only what applies: migrations, new queues or Horizon supervisors, Pennant flags, new env keys or secrets,
  scheduled jobs, shared-prop caches needing a clear, manual steps on the EC2 host>
```

Rules:

- Report only checks that ran in this session. If tests were not run, say so under Verification.
- Check the diff against the couplings described in CLAUDE.md (the `title_type` hook in `resources/js/app.js` with
  `TitleTypeScope`; jobs on the `embeddings` or `enrichment` queues; global scopes inside jobs) and mention any the
  change touches.
- Use a table for before/after numbers.
- No filler: no "This PR...", no restating the title, no file-by-file list.

## 4. Confirm, then publish

Write the body to a file in the scratchpad directory and show the user the title and body. After approval, push the
branch if it has no upstream, then create the PR, or edit it if one exists:

```bash
git push -u origin HEAD
gh pr create --base master --title "<title>" --body-file <file>
gh pr edit <number> --body-file <file>
```

Print the PR URL.
