# Lesson 5 - Ship it

**You will** put Bookshelf online at `https://bookshelf-<you>.caligrafy.com`, with its code in your own GitHub
repository, and keep it online. **Time:** 15 minutes, most of it Claude working.

---

## Ship it

In the `bookshelf` folder, in Claude:
```text
/caligrafy-quill:ship bookshelf-<you>
```
Claude does the work, in this order:

| Claude | You |
|---|---|
| checks the project is ready: no `.env` file in git, `doctor` happy, the table scripts safe to run again, the tests passing, no migration changed after it ran | nothing |
| commits the code, creates your **private** GitHub repository and pushes it: with your SSH key if GitHub knows it, else with the GitHub command line's sign-in | only if your computer has no GitHub access: enter the code Claude gives you on the GitHub page that opens, and approve |
| signs in to Caligrafy Cloud | check that the Caligrafy Cloud page shows the same code as your terminal, and approve |
| creates the app, adds its read-only *deploy key* to your repository (what lets Caligrafy Cloud read the code, and nothing else) | nothing |
| deploys it: Caligrafy Cloud builds the app, creates its database and runs the table scripts | nothing |
| checks the app online, then gives you the site, the repository and the time left | nothing |

Claude asks you one question only: before spending credits. Every new app is free for its first 30 minutes online,
from its first deploy. If the app needs a service key (an AI or a payment key; Bookshelf needs none), Claude names it
and you set it yourself, in your terminal, with `php caligrafer.php cloud secret set bookshelf-<you> KEY=value`: you
never paste it in the chat.

---

## Try it online

Do the checks of [Lesson 4](Lesson-4-Review-It) again, on the real address:

- **The page**: `https://bookshelf-<you>.caligrafy.com`. Sign up again, then sign in: the online app has its own
  database, with the sample data but none of your local accounts.
- **The phone**: open the address on your phone, then *Add to Home Screen*. It opens like an app.
- **REST**: `curl -s -H "Accept: application/json" https://bookshelf-<you>.caligrafy.com/api/books`
- **The export**: `https://bookshelf-<you>.caligrafy.com/books.csv`, signed in.
- **An AI agent**: create a token on the online page and run the `claude mcp add ...` line it shows. It names the
  online address: a local token does not work online, and an online one does not work locally.

The code is in your GitHub repository: a normal Caligrafy project that you, or Claude, can keep changing. After a
change, `/caligrafy-quill:ship bookshelf-<you>` again commits, pushes and deploys it ([Lesson 6](Lesson-6-Change-It)).

---

## Keep it online

After the free 30 minutes, the app pauses: its address shows a paused page, you get an email, and its data is kept for
7 days, then deleted. To keep it online, ask Claude, or run in the project:

| | Command | Credits (1 credit = 1 USD) |
|---|---|---|
| Time left | `php caligrafer.php cloud status bookshelf-<you>` | free |
| A week | `php caligrafer.php cloud extend bookshelf-<you> week` | 2 |
| A month | `php caligrafer.php cloud extend bookshelf-<you> month` | 5 |
| A year | `php caligrafer.php cloud extend bookshelf-<you> year` | 50 |
| Buy credits | `php caligrafer.php cloud credits 5` (or 10, 25, 50: it prints the payment page) | |

A paused app starts again when you extend it. (If PHP is not installed on your computer, put
`docker compose exec dev-box` before `php`.) Your apps and credits are also on
[deploy.caligrafy.com](https://deploy.caligrafy.com).

---

## Check

- [ ] Your repository is on GitHub, **private**, and contains no `.env` file.
- [ ] The online page, REST and an AI agent show the same books.
- [ ] A visitor is refused a change online too, and the export sends a visitor to sign in.
- [ ] You never typed a password, a token or a key in the chat.
- [ ] `cloud status` shows the time left.

> **If it doesn't work**
>
> | You see | Do |
> |---|---|
> | The name is taken on GitHub or Caligrafy Cloud | Claude proposes another name |
> | The browser page did not open | Claude prints its address: open it and approve |
> | *has no time left* | say yes when Claude asks to spend credits, or buy some (`cloud credits 5`) |
> | The build failed | Claude reads the end of the build log, fixes the cause, pushes and deploys again |
> | The page works locally, but its calls are refused online (`the legacy API key is turned off`) | ask Claude to check how the page calls its routes: online, the shared API key is off, and pages use the JS client or the session |

**By hand:** [14 Put it online](14-Put-It-Online) does each of these steps yourself. The framework page:
[Putting it Online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online).

**Next:** [Lesson 6 - Change it, ship it again](Lesson-6-Change-It)
