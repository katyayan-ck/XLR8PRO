# Daily records (date-wise)

One folder per working day, `docs/daily/DD-MM-YYYY/`, with three files (DEC-086 addendum, user request 29-09-2026):

| File | What | Also kept in (cumulative) |
|---|---|---|
| `handoff.md` | The hand-over: done, in progress, open questions, how to verify. Rewritten with every commit; the last version of the day is that day's closing hand-over | `.ai/state/handoff.md` (always the latest) |
| `changelog.md` | Every change made that day (files, before → after, reason, DEC / BUG ids) | `docs/changelog.md` under `## YYYY-MM-DD` |
| `accomplishments.md` | Every task completed that day, with full details | `docs/todo.md` Part 2 under `## DD-MM-YYYY` |

**Rule:** update the day's file **and** its cumulative counterpart in the same commit. On the first commit of a new day,
create the new day's folder (copy the handoff from `.ai/state/handoff.md`, start empty changelog / accomplishments
files with their headers).

Days before 29-09-2026 exist only in the cumulative files (`docs/changelog.md` by date; accomplishments summary in
`docs/todo.md`).

| Day | Files |
|---|---|
| [29-09-2026](29-09-2026/) | [handoff](29-09-2026/handoff.md) · [changelog](29-09-2026/changelog.md) · [accomplishments](29-09-2026/accomplishments.md) |
| [30-09-2026](30-09-2026/) | [handoff](30-09-2026/handoff.md) · [changelog](30-09-2026/changelog.md) · [accomplishments](30-09-2026/accomplishments.md) |
| [01-10-2026](01-10-2026/) | [handoff](01-10-2026/handoff.md) · [changelog](01-10-2026/changelog.md) · [accomplishments](01-10-2026/accomplishments.md) |
| [02-10-2026](02-10-2026/) | [handoff](02-10-2026/handoff.md) · [changelog](02-10-2026/changelog.md) · [accomplishments](02-10-2026/accomplishments.md) |
| [03-10-2026](03-10-2026/) | [handoff](03-10-2026/handoff.md) · [changelog](03-10-2026/changelog.md) · [accomplishments](03-10-2026/accomplishments.md) |
