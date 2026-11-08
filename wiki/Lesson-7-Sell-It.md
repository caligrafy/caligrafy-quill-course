# Lesson 7 - Sell it

**You will** learn how an app you shipped becomes a **proven prompt** on the marketplace of caligrafy.com, and how
you earn from it. Read this lesson now; do it with an app of your own (Bookshelf is for practice).
**Time:** 15 minutes to read.

---

## How it works

Caligrafy does not sell your code, your data or your settings. It sells the **prompt that recreates your app** with
Caligrafy, using Claude, like the ones of [Lesson 2](Lesson-2-Describe-It). Buyers try your app live on **Explore**,
then take its prompt, or the code or a managed app that Caligrafy builds again from that prompt. You earn **25% of
every sale**.

| Step | You | Claude | Caligrafy |
|---|---|---|---|
| 1. Offer it | on your app's page on [deploy.caligrafy.com](https://deploy.caligrafy.com), *Offer it to the marketplace* | | |
| 2. Package it | run `/caligrafy-quill:marketplace <app>` in the app's folder | reads, runs and walks the app, writes the listing, sends it | |
| 3. Review | | | reviews the listing, may polish it, sets the final price, and publishes it, or tells you why not |
| 4. Earn | follow your sales, and choose **Get paid** | | pays you |

## 1. Offer it

On your app's page on Caligrafy Cloud, in the **Marketplace** section:

1. Say where Caligrafy pays you: your country, then Stripe where Stripe pays out, or a bank wire elsewhere (the bank
   details are kept encrypted, and only Caligrafy's administrators read them).
2. Read the terms on the page: Caligrafy may show the app, its description and screenshots on caligrafy.com, sells a
   prompt that recreates it, and decides its price and whether it is published.
3. Choose **Offer it to the marketplace**.

A listed app is public: if you had locked it with a password, offering it removes the password. You can withdraw the
offer at any time. The page then shows the command to run, with your app's name.

## 2. Package it

In the app's folder, in Claude:
```text
/caligrafy-quill:marketplace <app>
```
It takes a while, often 15 to 30 minutes, and you do nothing. Claude:

1. checks that you are signed in to Caligrafy Cloud, that the app exists there, and that the file it will write stays
   out of git;
2. reads the whole app: `web.php`, every capability, every table script and migration, the jobs, the pages, the
   tests, the names of the settings in `.env.example` (never their values);
3. runs a **separate copy** of the app on your computer, at `http://localhost:18080`, with its own empty database: never
   the live app, and never your local data. It signs up a test account and adds invented, realistic content;
4. walks the app as a newcomer would and keeps up to **5 screenshots** of its key features, at 1280 x 800, all
   different, each with a short caption: no empty list, no error, no sign-in page. A feature that needs a key that is
   not set here (an AI or a payment key) is skipped, not faked. Then it removes the copy and its data;
5. writes one private file, `.caligrafy/marketplace.json` (the `.caligrafy/` folder is never in git):

   | Field | What it holds |
   |---|---|
   | `title` | a few words, the app's own name when it has one |
   | `description` | 2 to 4 sentences for a buyer: what it does, for whom, why it is worth having |
   | `images` | the screenshots and their captions, the first one shown largest |
   | `prompt` | the request that rebuilds the whole app with `/caligrafy-quill:idea`, in the order of [Lesson 2](Lesson-2-Describe-It#or-start-from-a-proven-prompt), usually 400 to 1,500 words: no code, no repository address, no key, no personal data |
   | `price` | an estimated window in US dollars (`low` and `high`, the high at most three times the low) and the reason: what comparable templates and kits sell for |

6. shows you a short summary (the title, the description, the screenshots, the prompt's length, the price window),
   then sends the file with `php caligrafer.php cloud submit <app>` and redeploys the app.

Read the summary. The prompt describes your app: it must never contain a key, a password or anyone's personal data.
If you want to read the whole file before it is sent, say so when you start: "Show me the file before you submit
it." If something is wrong after it is sent, tell Claude: running the skill again replaces the submission until
Caligrafy publishes it.

## 3. Review

Your app's page shows the listing as **Processing**: its title, description, screenshots and estimated price.
Caligrafy reviews it, may polish the text, sets the final price, and then the listing is **Published** on
[caligrafy.com/marketplace](https://caligrafy.com/marketplace). If it is not right for the marketplace, the page shows
you why: make the changes, then **Offer it again**.

On caligrafy.com, signed in with the same account, **Listings** follows every listing of yours, and **Tasks** says
when Caligrafy asks you for a change.

## 4. Earn

Your app's page shows its purchases, its sales and your 25%. **Earnings**, on caligrafy.com, shows every sale and your
balance. To get paid, choose **Get paid** there:

| Rule | |
|---|---|
| Minimum | $50 |
| How often | at most once a month |
| When a sale counts | 14 days after it (refunds happen in that time) |
| Transfer fees | deducted from your payment |

---

## Check

- [ ] You can explain what a buyer gets: the prompt that recreates the app, or what Caligrafy builds from it, never
      your code or data.
- [ ] You know the four steps: offer, package, review, earn.
- [ ] You know where the listing is written (`.caligrafy/marketplace.json`, never in git), and which command sends it
      (`cloud submit`).
- [ ] You know why you read the summary: no key, no password, no personal data.

**By hand:** packaging is the skill's work, with no by-hand lesson. The Caligrafy Cloud commands it uses (`cloud
account`, `cloud status`, `cloud deploy`) are the ones of [14 Put it online](14-Put-It-Online).

**Next:** [Lesson 8 - Your own idea](Lesson-8-Your-Own-Idea)
