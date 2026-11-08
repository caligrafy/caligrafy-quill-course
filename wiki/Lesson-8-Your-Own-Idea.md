# Lesson 8 - Your own idea

**You will** describe, approve and ship an app of your own, on your own, with the checklists of the course.
**Time:** an hour or two.

---

## Describe it

Take the description you wrote in [Lesson 2](Lesson-2-Describe-It). Check that it answers the five questions: who
uses it, what they do, what it remembers, who may do what, and what is not in this version. Keep it small: 1 to 3
tables, a handful of actions.

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git <your-app>
cd <your-app>
claude
```
```text
/caligrafy-quill:idea <your-app>-<you>: <your description>
```

## Approve it

**The plan** ([Lesson 3](Lesson-3-Approve-The-Plan)):
- [ ] every fact of your description has a column, and the tables are linked where they should be;
- [ ] every action is a capability, and nothing you did not ask for is;
- [ ] reads are read-only, deletes are destructive, and changes are only for the people you said;
- [ ] if people sign in with their email, the plan says so; otherwise usernames are letters and digits;
- [ ] the sample data contains nothing personal and no password;
- [ ] if your idea has slow work (an AI call, an email to many), it runs in the background; if it has "every
      morning", "remind" or "alert me", the plan names each job on a timer and each notification.

Then: "OK. Build it and check it here, then stop before putting it online."

**What Claude built** ([Lesson 4](Lesson-4-Review-It)):
- [ ] the page, the REST API and an AI agent agree;
- [ ] a visitor is refused a change everywhere;
- [ ] an invalid input gives the same message everywhere, under its own field on the page;
- [ ] the tables' scripts have no `DROP`, `DELETE` or `TRUNCATE`, and later changes are migrations;
- [ ] the capabilities read with the query builder, and `web.php` has one line per page, form, export and REST
      address, with no controller of the app's own: what a page or a form cannot say is a `Route::action`;
- [ ] if the app takes payments, a `Route::webhook(...)` line receives the provider's events, and Claude names the
      setting (`STRIPE_WEBHOOK_SECRET`) you set yourself online;
- [ ] `doctor`, the tests and the `caligrafy-reviewer` agent find nothing left to fix.

## Ship it

```text
/caligrafy-quill:ship <your-app>-<you>
```
([Lesson 5](Lesson-5-Ship-It)):
- [ ] the repository is private and has no `.env`;
- [ ] every service key the app needs was set by you, with `cloud secret set`, never in the chat;
- [ ] the online app works on the page, on your phone, over REST and with an AI agent;
- [ ] `cloud status` shows the time left, and you extended it if you want to keep it.

Then keep improving it with the same three steps ([Lesson 6](Lesson-6-Change-It)), and when people like it, offer it
on the marketplace ([Lesson 7](Lesson-7-Sell-It)). When a new Caligrafy release comes out,
`/caligrafy-quill:update` brings your project to it; ship it again afterwards.

---

## What you learned

You can now take any idea to a complete app online, with Claude doing the work and you in charge:

| Step | You |
|---|---|
| **Describe it** | say who uses it, what they do, what it remembers, who may do what, and what it does not do yet |
| **Approve it** | read the plan, change it, then review what Claude built, file by file, before it goes online |
| **Ship it** | approve the sign-ins in your browser, and keep it online |

**By hand:** to understand every line Claude writes, take the [By hand](Home#by-hand) track: the same app, built
yourself, one step at a time. When your idea has reminders or alerts, [15 Reminders](15-Reminders) builds a job on a
timer and a notification yourself.
