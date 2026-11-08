# Lesson 0 - Get ready

**You will** install the four tools the course uses, check each one, and meet the commands Claude gets in a Caligrafy
project. **Time:** 20 to 40 minutes, most of it downloads.

---

## What you need

| Tool | Why | Check |
|---|---|---|
| A terminal | where you talk to Claude Code | macOS: *Terminal*. Windows: *Ubuntu* on WSL 2 (`wsl --install` in PowerShell as administrator, then restart). Linux: your usual terminal |
| [Git](https://git-scm.com/) | the history of your project | `git --version` prints 2.28 or newer |
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) | runs your app on your computer (PHP, MySQL, phpMyAdmin): nothing else to install | `docker compose version` prints 2.24 or newer, with Docker Desktop running |
| [Claude Code](https://claude.com/claude-code) | builds your app with you | `claude --version` prints a version followed by `(Claude Code)` |

You also need a free [GitHub](https://github.com/) account. Your Caligrafy Cloud account is created when Claude opens
its page for you, in [Lesson 1](Lesson-1-Your-First-App).

On Windows, install Git, Docker's WSL integration (Docker Desktop > Settings > Resources > WSL integration > *Ubuntu*)
and Claude Code **inside Ubuntu**, and run every command there.

Tell Git who you are, once:
```bash
git config --global user.name "Your Name"
git config --global user.email "you@example.com"
```

You do not install PHP or MySQL: they run in Docker. When a lesson shows a `php caligrafer.php ...` command and PHP is
not installed on your computer, put `docker compose exec dev-box` before it. Most of the time you do not type these
commands at all: you ask Claude.

---

## The Caligrafy plugin

You do not install it: it ships inside every Caligrafy project, in the folder `claude-plugin/`. The first time you start
`claude` in a project and answer *Yes* to trusting the folder, Claude learns Caligrafy (its documentation, a verified
reference and a list of known mistakes) and gets these commands:

| Command | What it does | Lesson |
|---|---|---|
| `/caligrafy-quill:idea <your idea>` | describe it, approve the plan, and Claude builds, checks and ships the whole app | 1, 3, 8 |
| `/caligrafy-quill:ship <name>` | puts the app online: your GitHub repository, then Caligrafy Cloud | 5, 6 |
| `/caligrafy-quill:new-capability` | adds one feature, written once, to the page, REST and AI agents | 6 |
| `/caligrafy-quill:new-resource` | adds a table with its model and its list, get, create, update and delete capabilities | 6 |
| `/caligrafy-quill:design <style>` | gives every page another look (`off` brings Caligrafy's back) | 6 |
| `/caligrafy-quill:marketplace` | packages your app for the marketplace of caligrafy.com | 7 |
| `/caligrafy-quill:server` | starts, stops or checks the app on your computer | 4 |
| `/caligrafy-quill:db` | creates or changes tables, runs migrations, shows what is in the database | 6 |
| `/caligrafy-quill:update` | brings the project to the latest Caligrafy release | 8 |
| agent `caligrafy-reviewer` | reviews the code Claude wrote, for Caligrafy mistakes and security issues | 4 |

Two more are for later: `/caligrafy-quill:new-app` (a Vue app, a chatbot, a camera app) and
`/caligrafy-quill:deploy` (a readiness check, or your own server instead of Caligrafy Cloud). The framework's page
[Claude Code Plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin) describes each one.

---

## Three rules for working with Claude

1. **Nothing is built before your OK.** Read what Claude proposes; ask it to explain anything you do not understand.
2. **Claude can be wrong.** Every lesson ends with a **Check** list: go through it before you accept a result.
3. **Never paste a secret** (a password, a token, the contents of `.env`) into the chat. Claude never asks for one:
   sign-ins happen in your browser.

---

## Check

- [ ] `git --version`, `docker compose version` and `claude --version` all print a version: Git 2.28 or newer,
      Compose 2.24 or newer.
- [ ] Docker Desktop is running.
- [ ] You can sign in to GitHub.

> **If it doesn't work**
>
> | You see | Do |
> |---|---|
> | `command not found` | install the tool, then close and reopen the terminal |
> | `Cannot connect to the Docker daemon` | start Docker Desktop and wait until the engine runs |
> | Windows: `docker` works in PowerShell, not in Ubuntu | turn on WSL integration for *Ubuntu* in Docker Desktop |
> | `docker compose version` is older than 2.24 | update Docker Desktop |

**By hand:** [00 Your tools](00-Introduction-and-Tools) installs and checks the same tools, and explains what each one
does.

**Next:** [Lesson 1 - Your first app in one conversation](Lesson-1-Your-First-App)
