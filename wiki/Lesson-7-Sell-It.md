# Lesson 7 - Sell it

**You will** learn how an app you shipped becomes a **proven prompt** on the marketplace of caligrafy.com, and how
you earn from it. Read this lesson now; do it with an app of your own (Bookshelf is for practice).
**Time:** 15 minutes to read.

---

## How it works

Caligrafy does not sell your app or its code. It sells the **prompt that recreates it** with Caligrafy, like the ones
of [Lesson 2](Lesson-2-Describe-It). Buyers try your app live, buy the prompt, and describe it to Claude. You earn
**25% of every sale**.

| Step | You | Caligrafy |
|---|---|---|
| 1. Offer it | on your app's page on [deploy.caligrafy.com](https://deploy.caligrafy.com), *Offer it to the marketplace* | |
| 2. Package it | run `/caligrafy-quill:marketplace` in the app's folder | |
| 3. Review | | reviews the listing, may edit it, sets the final price, and publishes it, or tells you why not |
| 4. Earn | follow your sales on the app's page, and ask to get paid | pays you |

## 1. Offer it

On your app's page on Caligrafy Cloud, in the **Marketplace** section, choose *Offer it to the marketplace*. The first
time, you say how you get paid: through Stripe in the countries it supports, or by wire transfer elsewhere (your bank
details are encrypted, and only Caligrafy's administrator sees them).

## 2. Package it

In the app's folder, in Claude:
```
/caligrafy-quill:marketplace
```
It takes a while, often 15 to 30 minutes. Claude:
- reads the whole code;
- runs the app on your computer and walks through it, taking up to 5 screenshots of its key features (all different);
- writes the title, the description for buyers, the prompt that recreates the app, and an estimated price, in one
  private file, `.caligrafy/marketplace.json` (never in git);
- sends it all to Caligrafy and redeploys the app.

Read what it wrote before it sends it: the prompt describes your app, so it must never contain a key, a password or
anyone's personal data.

## 3. Review

Your app's page shows the listing as **Processing**. Caligrafy reviews it: it may edit the text, keep the best
screenshots, and sets the final price. Then the listing is **Published** on
[caligrafy.com/marketplace](https://caligrafy.com/marketplace). If it is not right for the marketplace, the page shows
you why, and you may offer it again.

## 4. Earn

The app's page shows every sale and your 25%. To get paid, choose **Get paid**:

| Rule | |
|---|---|
| Minimum | $50 |
| How often | at most once a month |
| When a sale counts | 14 days after it (refunds happen in that time) |
| Transfer fees | deducted from your payment |

---

## Check

- [ ] You can explain what a buyer gets: the prompt that recreates the app, not your code.
- [ ] You know the four steps: offer, package, review, earn.
- [ ] You know why you read the listing before it is sent: no key, no password, no personal data.

**Next:** [Lesson 8 - Your own idea](Lesson-8-Your-Own-Idea)
