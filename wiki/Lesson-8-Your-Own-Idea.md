# Lesson 8 - Your own idea

**You will** describe, approve and ship an app of your own, on your own, with the checklists of the course.
**Time:** an hour or two.

---

## Describe it

Take the description you wrote in [Lesson 2](Lesson-2-Describe-It). Check that it answers the five questions: who
uses it, what they do, what it remembers, who may do what, and what is not in this version. Keep it small.

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git <your-app>
cd <your-app>
claude
```
```
/caligrafy-quill:idea <your-app>-<you>: <your description>
```

## Approve it

**The plan** ([Lesson 3](Lesson-3-Approve-The-Plan)):
- [ ] every fact of your description has a column, and the tables are linked where they should be;
- [ ] every action is a capability, and nothing you did not ask for is;
- [ ] changes are only for the people you said;
- [ ] the sample data contains nothing personal.

Then: "OK. Build it and check it here, then stop before putting it online."

**What Claude built** ([Lesson 4](Lesson-4-Review-It)):
- [ ] the page, the REST API and an AI agent agree;
- [ ] a visitor is refused a change everywhere;
- [ ] an invalid input gives the same message everywhere;
- [ ] `doctor` and the `caligrafy-reviewer` agent find nothing left to fix.

## Ship it

```
/caligrafy-quill:ship <your-app>-<you>
```
([Lesson 5](Lesson-5-Ship-It)):
- [ ] the repository is private and has no `.env`;
- [ ] the online app works on the page, on your phone, over REST and with an AI agent.

Then keep improving it with the same three steps ([Lesson 6](Lesson-6-Change-It)), and when people like it, offer it
on the marketplace ([Lesson 7](Lesson-7-Sell-It)).

---

## What you learned

You can now take any idea to a complete app online, with Claude doing the work and you in charge:

| Step | You |
|---|---|
| **Describe it** | say who uses it, what they do, what it remembers, who may do what, and what it does not do yet |
| **Approve it** | read the plan, change it, then review what Claude built before it goes online |
| **Ship it** | approve the sign-ins in your browser, and keep it online |

To understand every line Claude writes, take the [By hand](Home#by-hand) track: the same app, built yourself, one
step at a time.
