# Lesson 1 - Your first app in one conversation

**You will** go through the three steps once, quickly, on a tiny app: describe it, approve it, ship it. The next
lessons take each step slowly. **Time:** 20 to 40 minutes, most of it Claude working.

---

## 1. Describe it

Create a project and start Claude in it:
```bash
git clone https://github.com/caligrafy/caligrafy-quill.git quotes
cd quotes
claude
```
Answer *Yes* when Claude Code asks whether you trust the folder. Then describe the app in one sentence (replace
`<you>` with your name or initials, so that the address is yours):
```
/caligrafy-quill:idea quotes-<you>: a list of quotes I like, each with who said it and a tag.
```

## 2. Approve it

Claude answers with a **plan**, a few short tables:

| Part | What it says |
|---|---|
| Name | `quotes-<you>`: your app's address will be `https://quotes-<you>.caligrafy.com` |
| Tables | what the app remembers: a `quotes` table with `text`, `author`, `tag` |
| Capabilities | what the app can do: `list_quotes`, `create_quote`, `update_quote`, `delete_quote`... |
| Who may do what | anyone reads, people who signed up add and change |
| Pages | `/quotes`: a form and the list |
| Sample data | a few quotes, so the app is not empty |

It ends with what your OK lets Claude do. Read it; for now, just answer:
```
OK
```

## 3. Ship it

Claude now works without asking you anything: it sets up the project, builds every feature, checks it, creates a
private GitHub repository and puts the app online. It stops only when **you** must approve something, in your
browser:

- **Caligrafy Cloud**: a page opens. The first time, sign up and confirm your email, then approve the code your
  terminal shows.
- **GitHub**, only if your computer has no GitHub access yet: approve the code GitHub shows.

At the end, Claude gives you your addresses. Open `https://quotes-<you>.caligrafy.com`: sign up (a username of letters
and digits), add a quote, change it, delete it. On your phone, open the same address and choose *Add to Home Screen*:
it opens like an app.

Every new app is free for its first 30 minutes online, then it pauses. That is fine for this one: it was for practice.

---

## What just happened

You wrote one sentence and said OK once. Claude did everything else, and you stayed in charge: nothing was built
before your OK, and nothing went online without your approval in the browser. The rest of the course teaches you
what to look for at each step, so that your OK means something.

---

## Check

- [ ] The plan had a name, tables, capabilities, who may do what, pages and sample data.
- [ ] Claude never asked you for a password, a token or a key.
- [ ] Your app opened at `https://quotes-<you>.caligrafy.com`, and you could add a quote after signing up.

> **If it doesn't work**
>
> | You see | Do |
> |---|---|
> | `/caligrafy-quill:idea` is not a command | in the `quotes` folder, run `php caligrafer.php plugin`, then restart `claude` |
> | Docker is not running | start Docker Desktop, then tell Claude to go on |
> | A port is already in use | let Claude use other ports: it proposes them |
> | The name is taken | Claude proposes another one |
> | The browser page did not open | Claude prints its address: open it, approve, Claude goes on |

**Next:** [Lesson 2 - Describe it](Lesson-2-Describe-It)
