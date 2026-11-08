# Lesson 1 - Your first app in one conversation

**You will** go through the three steps once, quickly, on a tiny app: describe it, approve it, ship it. The next
lessons take each step slowly. **Time:** 20 to 40 minutes, most of it Claude working.

---

## 1. Describe it

Get a copy of Caligrafy in a new folder and start Claude in it:
```bash
git clone https://github.com/caligrafy/caligrafy-quill.git quotes
cd quotes
claude
```
Answer *Yes* when Claude Code asks whether you trust the folder (and *Yes* if it asks to enable the
`caligrafy-quill` plugin). Then describe the app in one sentence. Replace `<you>` with your name or initials, so that
the address is yours:
```text
/caligrafy-quill:idea quotes-<you>: a list of quotes I like, each with who said it and a tag.
```

## 2. Approve it

Claude answers with a **plan**, a few short tables. It writes nothing before your OK:

| Part | What it says |
|---|---|
| Name | `quotes-<you>`: 3 to 30 lowercase letters, digits and dashes. Your app's address will be `https://quotes-<you>.caligrafy.com` |
| Tables | what the app remembers: a `quotes` table with the quote, `author`, `tag` (the names can differ a little) |
| Capabilities | what the app can do: `list_quotes`, `get_quote`, `create_quote`, `update_quote`, `delete_quote`, each with what it does, whether it only reads or deletes, and its scope (`quotes:read`, `quotes:write`). Claude adds `create_my_token`, for the AI agents card, and sometimes one more read such as `list_tags` |
| Who may do what | anyone reads, people who signed up add and change. It may add that each person changes only their own quotes: say so if you want it otherwise |
| Pages | `/quotes`: a form, the list with its actions, and a message when the list is empty; `/` opens it |
| Sample data | 3 to 6 quotes, so the app is not empty, online too |

It ends with what your OK lets Claude do: set up the project (it replaces the history of the Caligrafy copy with your
app's own), build and check it, create a private GitHub repository and put the app online. You will only approve
sign-ins in your browser, and Claude asks before spending any credits. For now, answer:
```text
OK
```

## 3. Ship it

Claude now works without asking you anything. It says which step it is in as it goes:

1. It sets up the project in Docker (the first time, Docker downloads and builds the server: a few minutes).
2. It writes the table, the capabilities, the sign-in pages, the page, the REST API and the AI agent connection, then
   checks every one of them.
3. It commits the code, creates your **private** GitHub repository and puts the app online on Caligrafy Cloud.

It stops only when **you** must approve something, in your browser:

- **Caligrafy Cloud**: a page opens. The first time, choose **Create account** (your email and a password, twice),
  open the confirmation email from Caligrafy, then sign in on that page and approve the code your terminal shows.
- **GitHub**, only if your computer has no GitHub access yet: enter the code Claude gives you on the GitHub page that
  opens, and approve.

At the end, Claude gives you your addresses. Open `https://quotes-<you>.caligrafy.com`: sign up (a username of letters
and digits, a password of at least 8 characters), sign in, then add a quote, change it, delete it. Signing up does not
sign you in: sign in after it. On your phone, open the same address and choose *Add to Home Screen*: it opens like an
app.

Every new app is free for its first 30 minutes online, then it pauses. That is fine for this one: it was for practice.

---

## What just happened

You wrote one sentence and said OK once. Claude did everything else, and you stayed in charge: nothing was built
before your OK, and nothing went online without your approval in the browser. The rest of the course teaches you
what to look for at each step, so that your OK means something.

---

## Check

- [ ] The plan had a name, tables, capabilities, who may do what, pages and sample data, and said what your OK lets
      Claude do.
- [ ] Claude never asked you for a password, a token or a key.
- [ ] Your app opened at `https://quotes-<you>.caligrafy.com`, and you could add a quote after signing in, not before.

> **If it doesn't work**
>
> | You see | Do |
> |---|---|
> | `/caligrafy-quill:idea` is not a command | quit Claude (`/exit`), start `claude` again in the `quotes` folder and answer *Yes* to trusting it and to the `caligrafy-quill` plugin; or type `/plugin` and enable `caligrafy-quill` |
> | Docker is not running | start Docker Desktop, then tell Claude to go on |
> | A port is already in use | let Claude use other ports: it moves the app to free ones and gives you the new address |
> | The name is taken | Claude proposes another one |
> | The browser page did not open | Claude prints its address: open it, approve, Claude goes on |
> | Caligrafy Cloud says **Confirm your email first** | open the link of the email from Caligrafy (look in spam), then sign in again |
> | You forgot your Caligrafy password | **Forgot your password?** on the sign-in page emails you a 6-digit code |
> | The sign-up page refuses your username | letters and digits only: no email address, dot or dash, unless your description said people sign in with their email ([Lesson 2](Lesson-2-Describe-It)) |

**By hand:** [01 Your project in one command](01-Your-Project-in-One-Command) sets up the same project yourself, and
[02 Build once, seen three ways](02-Build-Once-Seen-Three-Ways) shows one capability on a page, over REST and to an AI
agent.

**Next:** [Lesson 2 - Describe it](Lesson-2-Describe-It)
