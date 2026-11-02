# Lesson 5 - Ship it

**You will** put Bookshelf online at `https://bookshelf-<you>.caligrafy.com`, with its code in your own GitHub
repository, and keep it online. **Time:** 15 minutes, most of it Claude working.

---

## Ship it

In the `bookshelf` folder, in Claude:
```
/caligrafy-quill:ship bookshelf-<you>
```
Claude does the work, in this order:

| Claude | You |
|---|---|
| checks the project is ready to go online (no secret in git, the database scripts safe to run again) | nothing |
| commits the code, creates your **private** GitHub repository and pushes it | only if your computer has no GitHub access: approve the code GitHub shows in your browser |
| signs in to Caligrafy Cloud | approve the code on the Caligrafy Cloud page Claude opens |
| creates the app, gives it read-only access to the repository, deploys it | nothing |
| checks the app online | nothing |

Claude asks you one question only: before spending credits. Every new app is free for its first 30 minutes online.

---

## Try it online

Do the checks of [Lesson 4](Lesson-4-Review-It) again, on the real address:

- **The page**: `https://bookshelf-<you>.caligrafy.com`. Sign up again: the online app has its own database, with the
  sample data but none of your local accounts.
- **The phone**: open the address on your phone, then *Add to Home Screen*. It opens like an app.
- **REST**: `curl -s -H "Accept: application/json" https://bookshelf-<you>.caligrafy.com/api/books`
- **An AI agent**: create a token on the online page and run the `claude mcp add ...` line it shows.

The code is in your GitHub repository: a normal Caligrafy project that you, or Claude, can keep changing.

---

## Keep it online

After the free 30 minutes, the app pauses: its address shows a paused page, and its data is kept for 7 days. To keep
it online, ask Claude, or run in the project:

| | Command | Credits (1 credit = 1 USD) |
|---|---|---|
| Time left | `php caligrafer.php cloud status bookshelf-<you>` | free |
| A week | `php caligrafer.php cloud extend bookshelf-<you> week` | 2 |
| A month | `php caligrafer.php cloud extend bookshelf-<you> month` | 5 |
| Buy credits | `php caligrafer.php cloud credits 5` (opens the payment page) | |

(If PHP is not installed on your computer, put `docker compose exec dev-box` before `php`.) Your apps and credits are
also on [deploy.caligrafy.com](https://deploy.caligrafy.com).

---

## Check

- [ ] Your repository is on GitHub, **private**, and contains no `.env` file.
- [ ] The online page, REST and an AI agent show the same books.
- [ ] A visitor is refused a change online too.
- [ ] You never typed a password or a token in the chat.

> **If it doesn't work**
>
> | You see | Do |
> |---|---|
> | The name is taken on GitHub or Caligrafy Cloud | Claude proposes another name |
> | The browser page did not open | Claude prints its address: open it and approve |
> | *has no time left* | say yes when Claude asks to spend credits, or buy some (`cloud credits 5`) |
> | The page works locally, but its calls are refused online | ask Claude to check how the page calls its routes: online, the shared API key is off |

**By hand:** [14 Put it online](14-Put-It-Online).

**Next:** [Lesson 6 - Change it, ship it again](Lesson-6-Change-It)
