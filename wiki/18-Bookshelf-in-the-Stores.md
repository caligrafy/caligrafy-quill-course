# Lesson 18 - Bookshelf in the stores: an iOS and Android app

**In this lesson** Bookshelf becomes an app for iPhones and Android phones. One command creates it, one line of
`web.php` lets people sign in to it through Bookshelf's own sign-in page, and three screens (the catalog, a book, the
loans) call the capabilities you already have, as the person signed in. You run it in a browser, on an iPhone
simulator and on an Android emulator. No capability, model or rule is written: the app is one more way in.

**Before you start:** lesson 17 is done (tag `lesson-17`), `docker compose ps` shows the containers running, you can
sign in as `reader1@example.com` (an administrator), and `docker compose exec dev-box php caligrafer.php version` prints
`Caligrafy Quill 2.27.1` or later (else `docker compose exec dev-box php caligrafer.php update 2.27.1` first).
On your computer: Node.js 22.12 or newer (`node --version`). For the iPhone simulator: a Mac with Xcode; for the
Android emulator: Android Studio. Either one is enough to follow the lesson; the browser part needs neither.

**Time:** 60 to 90 minutes (the first Xcode and Android builds download a lot).

---

## New concepts

**A phone app is a client, not a second backend.** The app is built from the same Vue you know from lessons 09 and
10, wrapped by [Capacitor](https://capacitorjs.com) into an Xcode project (`ios/`) and an Android Studio project
(`android/`). Every screen reads and writes through `call('<capability>', inputs)`, so the rules of lesson 05, the
levels of lesson 11 and the audit log apply to it exactly as they apply to the pages, REST and the agents.

**Signing in from an app.** A phone app cannot use the site's session cookie. `Route::mobile` gives it the way apps
sign in everywhere: the app opens Bookshelf's own `/login` in the phone's browser, the person signs in and taps
**Allow**, and Bookshelf sends them back to the app (`com.bookshelf.reader://auth?code=...`). The app trades that
one-time code, with a secret only it knows (PKCE), for a **personal access token of that person**, the same kind of
token as lesson 08's, named after the phone. The app never sees a password. **Sign out** revokes the token.

**The app id** (`com.bookshelf.reader`) names the app for Apple and Google and is the address the sign-in comes back
through. Lowercase letters, digits and dots, and it cannot change once the app is in a store.

**Development and store builds.** `npm run ios` and `npm run android` build the app against your Docker server
(`.env.development`, `http://localhost:8080`; the Android emulator reaches it at `10.0.2.2` and the app switches by
itself). The store builds, `npm run release:ios` and `npm run release:android`, call Bookshelf online (lesson 14).

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - Create the app

```bash
docker compose exec dev-box php caligrafer.php mobile bookshelf-app --id com.bookshelf.reader --display Bookshelf --url https://bookshelf-<you>.caligrafy.com
```
Use your own address from lesson 14 in `--url`. Expected:
```text
 public/bookshelf-app created: Bookshelf (com.bookshelf.reader), calling https://bookshelf-<you>.caligrafy.com once online
 .github/workflows/mobile-bookshelf-app.yml written: the store builds on GitHub Actions (TestFlight and Google Play)
 Node.js 22+ is not available here: the packages and the native projects are NOT installed.
 On your computer: cd public/bookshelf-app && npm install && npx cap add ios && npx cap add android && npm run native && npm run icons
```
followed by the next steps. The Docker server has no Node.js, so finish on your computer, as it says:
```bash
cd public/bookshelf-app
npm install
npx cap add ios
npx cap add android
npm run native
npm run icons
cd ../..
```
`npx cap add ios` and `npx cap add android` create the Xcode and Android Studio projects; `npm run native` gives them
the app's sign-in address and notifications; `npm run icons` makes every icon and splash size from the Caligrafy mark
(put your own 1024 x 1024 `assets/icon-only.png` there later and run it again).

**Check**

- [ ] `public/bookshelf-app` has `ios/`, `android/`, `src/`, `capacitor.config.json` (`"appId": "com.bookshelf.reader"`).
- [ ] `npm run native` ended with ` Ready: ...` or ` Native projects already set up`.

### Step 2 - Let people sign in to it

In `application/config/routing/web.php`, after the line `Route::notifications();`, add:

```php
    Route::mobile(['scheme' => 'com.bookshelf.reader', 'name' => 'Bookshelf']);   // the phone app signs in through /login (lesson 18)
```
`scheme` is the app id; `name` is how the consent page names the app. It declared five addresses:

| Address | What it does |
|---|---|
| `GET /mobile/authorize` | the consent page, after Bookshelf's `/login` for visitors |
| `POST /mobile/authorize` | Allow or Not now |
| `POST /mobile/token` | the app trades the code and its secret for a token |
| `GET /mobile/session` | who the app's token signs in |
| `POST /mobile/signout` | revokes the app's token |

```bash
docker compose exec dev-box php caligrafer.php doctor --only project
```
Expected, among the lines: `OK    Phone app public/bookshelf-app: com.bookshelf.reader signs in through Route::mobile`.

### Step 3 - The screens

The template's home page goes; Bookshelf gets three screens. Delete
`public/bookshelf-app/src/components/pages/HomePage.vue`, then write the router and the screens.

**File `public/bookshelf-app/src/router/index.js`**

```js
import { createRouter, createWebHashHistory } from 'vue-router'
import CatalogPage from '../components/pages/CatalogPage.vue'
import BookPage from '../components/pages/BookPage.vue'
import LoansPage from '../components/pages/LoansPage.vue'
import SignInPage from '../components/pages/SignInPage.vue'
import { signedIn } from '../mobile/session.js'

const router = createRouter({
  // hash history (#/page): the app's files are served by the phone itself, with no server
  history: createWebHashHistory(),
  routes: [
    { path: '/', name: 'home', component: CatalogPage, meta: { signedIn: true } },
    { path: '/books/:id', name: 'book', component: BookPage, meta: { signedIn: true } },
    { path: '/loans', name: 'loans', component: LoansPage, meta: { signedIn: true } },
    { path: '/sign-in', name: 'sign-in', component: SignInPage },
  ]
})

// the pages that need someone signed in send visitors to the sign-in page, and back once they signed in
router.beforeEach(to => {
  if (to.meta.signedIn && !signedIn()) return { name: 'sign-in', query: { back: to.fullPath } }
  if (to.name === 'sign-in' && signedIn()) return { name: 'home' }
})

export default router
```

**File `public/bookshelf-app/src/components/pages/CatalogPage.vue`**

```html
<!-- The catalog: list_books, as the signed-in person. A row opens the book; administrators also see the loans -->
<template>
	<main>
		<section class='cq-hero cq-hero--page'>
			<h1 class='cq-display'>Catalog</h1>
			<p class='cq-lead'>{{ total }} books on the shelf.</p>
		</section>
		<section class='cq-band cq-band--tight'>
			<div class='cq-inner cq-inner--narrow'>
				<p class='cq-alert cq-alert--error' role='alert' v-if='error'>{{ error }}</p>
				<ul class='cq-rows'>
					<li class='cq-row cq-row--link' v-for='book in books' :key='book.id'>
						<router-link class='cq-row-main' :to='{ name: "book", params: { id: book.id } }'>
							<p class='cq-row-title'>{{ book.title }}</p>
							<p class='cq-row-meta'>{{ book.author ? book.author.name : '' }} · {{ book.published_year }}</p>
						</router-link>
					</li>
				</ul>
				<router-link v-if='admin' class='btn btn-secondary cq-btn-block cq-app-actions' :to='{ name: "loans" }'>Loans</router-link>
			</div>
		</section>
	</main>
</template>

<script>
import { call, currentUser } from '../../mobile/session.js';

export default {
	name: 'catalog-page',
	data() {
		return { books: [], total: 0, error: '', admin: (currentUser() || {}).permissions >= 1 };
	},
	async mounted() {
		try {
			const page = await call('list_books', { page: 1 });
			this.books = page.data;
			this.total = page.total;
		} catch (error) {
			this.error = error.message;
		}
	}
};
</script>
```

**File `public/bookshelf-app/src/components/pages/BookPage.vue`**

```html
<!-- One book: get_book, by the id in the address (#/books/3) -->
<template>
	<main>
		<section class='cq-hero cq-hero--page' v-if='book'>
			<h1 class='cq-display'>{{ book.title }}</h1>
			<p class='cq-lead'>{{ book.author.name }}, {{ book.published_year }}</p>
		</section>
		<section class='cq-band cq-band--tight'>
			<div class='cq-inner cq-inner--narrow'>
				<p class='cq-alert cq-alert--error' role='alert' v-if='error'>{{ error }}</p>
				<p v-if='book'>{{ book.summary }}</p>
				<router-link class='btn btn-secondary cq-btn-block cq-app-actions' :to='{ name: "home" }'>Back to the catalog</router-link>
			</div>
		</section>
	</main>
</template>

<script>
import { call } from '../../mobile/session.js';

export default {
	name: 'book-page',
	data() {
		return { book: null, error: '' };
	},
	async mounted() {
		try {
			this.book = await call('get_book', { id: Number(this.$route.params.id) });
		} catch (error) {
			this.error = error.status === 404 ? 'This book is not in the catalog any more.' : error.message;
		}
	}
};
</script>
```

**File `public/bookshelf-app/src/components/pages/LoansPage.vue`**

```html
<!-- The loans (administrators): list_loans, and return_book when a book comes back. Reminders reach this phone once
notifications are on (remind_loans, lesson 15) -->
<template>
	<main>
		<section class='cq-hero cq-hero--page'>
			<h1 class='cq-display'>Loans</h1>
			<p class='cq-lead'>{{ loans.length ? 'The books that are out, the soonest due first.' : 'Every book is on the shelf.' }}</p>
		</section>
		<section class='cq-band cq-band--tight'>
			<div class='cq-inner cq-inner--narrow'>
				<p class='cq-alert' role='status' v-if='status'>{{ status }}</p>
				<p class='cq-alert cq-alert--error' role='alert' v-if='error'>{{ error }}</p>
				<ul class='cq-rows'>
					<li class='cq-row' v-for='loan in loans' :key='loan.id'>
						<div class='cq-row-main'>
							<p class='cq-row-title'>{{ loan.book.title }}</p>
							<p class='cq-row-meta'>{{ loan.borrower }} · due {{ loan.due_on }}</p>
						</div>
						<div class='cq-row-actions'>
							<button type='button' class='btn btn-secondary' :disabled='busy' @click='returned(loan)'>Returned</button>
						</div>
					</li>
				</ul>
				<div class='cq-app-actions'>
					<button v-if='canPush' type='button' class='btn btn-secondary cq-btn-block' :disabled='busy' @click='notifications'>Remind me on this phone</button>
					<router-link class='btn btn-secondary cq-btn-block' :to='{ name: "home" }'>Back to the catalog</router-link>
				</div>
			</div>
		</section>
	</main>
</template>

<script>
import { call } from '../../mobile/session.js';
import { enablePush, pushAvailable } from '../../mobile/push.js';

export default {
	name: 'loans-page',
	data() {
		return { loans: [], busy: false, status: '', error: '', canPush: pushAvailable() };
	},
	mounted() {
		this.load();
	},
	methods: {
		async load() {
			try {
				this.loans = await call('list_loans');
			} catch (error) {
				this.error = error.status === 403 ? 'Only administrators see the loans.' : error.message;
			}
		},
		async returned(loan) {
			this.busy = true;
			this.status = this.error = '';
			try {
				await call('return_book', { book_id: loan.book_id });
				this.status = loan.book.title + ' is back on the shelf.';
				await this.load();
			} catch (error) {
				this.error = error.message;
			} finally {
				this.busy = false;
			}
		},
		async notifications() {
			this.busy = true;
			this.status = this.error = '';
			try {
				await enablePush();
				this.status = 'The reminders of the loans will reach this phone.';
			} catch (error) {
				this.error = error.message;
			} finally {
				this.busy = false;
			}
		}
	}
};
</script>
```

Each screen calls one capability, as the person signed in: `list_books` and `get_book` (public), `list_loans` and
`return_book` (`loans:*`, administrators). An error carries the answer of the capability (`error.status`,
`error.fields`), as in lesson 09. The top bar and **Sign out** come with the template.

### Step 4 - Try it in a browser

```bash
cd public/bookshelf-app
npm run dev
```
Open `http://localhost:5173` at a phone size (your browser's device toolbar), then:

1. **Sign in**: Bookshelf's own sign-in page opens. Sign in as `reader1@example.com`.
2. The consent page: **Sign in on this browser?** *The app of Bookshelf asks to use your account
   (reader1@example.com)*. Click **Allow**: you are back in the app, on the catalog, `5 books on the shelf.`
3. Open a book, then **Back to the catalog**, then **Loans**: lend a book first if none is out
   (`docker compose exec dev-box php caligrafer.php call lend_book book_id=4 borrower=Ana`). Click **Returned**:
   `Parable of the Sower is back on the shelf.`
4. **Sign out**: back to the sign-in screen.

In a browser the code comes back to the page itself (`?mobile_auth=1`): Bookshelf allows that only while it runs on
your computer. Stop the server with Ctrl+C.

**Check**

- [ ] After sign-in the catalog showed every book; the loans showed the one out and **Returned** closed it.
- [ ] `docker compose exec dev-box php caligrafer.php token list` shows `Mobile: this browser`, revoked after
      **Sign out**.

### Step 5 - On an iPhone simulator (Mac with Xcode)

```bash
npm run ios
```
It builds the app against your Docker server, copies it into `ios/` and opens Xcode. Pick an iPhone simulator at the
top (an iPhone 17 or newer) and click Run. The app starts on its sign-in screen; **Sign in** opens Bookshelf's sign-in
page in a browser sheet, then the consent page says **Sign in on iPhone ...?**; **Allow** brings you back to the app,
signed in. Close the app and open it again: you are still signed in.

### Step 6 - On an Android emulator (Android Studio)

```bash
npm run android
```
It opens Android Studio: create a device in Device Manager the first time (a Pixel with Google Play), then Run. The
sign-in opens in a Chrome tab; on the emulator Bookshelf is at `10.0.2.2:8080`, which the app uses by itself.
**Remind me on this phone** is not on the loans screen yet: Android notifications need a Firebase file, below.

**Check**

- [ ] On each device you tried: sign in, Allow, the catalog, a book, a return, sign out.
- [ ] `token list` shows one `Mobile: <device>` token per sign-in, revoked after each **Sign out**.

### Step 7 - Notifications (optional)

The reminders of lesson 15 (`remind_loans`) already use `Notify`: once an iPhone or an Android phone turns
notifications on, they reach it too, with no change to the capability. On the loans screen, **Remind me on this phone**
answers *This app does not send notifications to iPhones yet* until Bookshelf has the push settings of Apple
(`APNS_KEY`, `APNS_KEY_ID`, `APNS_TEAM_ID`, `APNS_TOPIC`) or Firebase (`FCM_SERVICE_ACCOUNT` and
`android/app/google-services.json`). They need an Apple Developer or Firebase account: the README of
`public/bookshelf-app` says where each comes from.

### Step 8 - Publishing (optional)

The stores need accounts only their owner can open: the Apple Developer Program (99 USD a year) and a Google Play
developer account (25 USD once). Then each release is one workflow,
`.github/workflows/mobile-bookshelf-app.yml`: iOS to TestFlight, Android to Google Play's internal testing. The steps,
the keys and the secrets are in the README of `public/bookshelf-app`, under *Publishing*, and in
[Phone and tablet apps](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.7-Phone-and-Tablet-Apps#publish-it).

---

## Check

- [ ] `web.php` is lesson 17's plus one line, `Route::mobile([...])`; the four app files are identical to the checkpoint.
- [ ] `doctor --only project` says the phone app signs in through `Route::mobile`.
- [ ] The app worked in a browser, and on the simulator or the emulator you have.
- [ ] No capability was added or changed.

## With Claude

```text
/caligrafy-quill:mobile bookshelf-app
```
Claude asks for the app id, creates the app, adds `Route::mobile`, writes the screens on your capabilities, checks
them in a browser at phone size and on a simulator, and, with `publish`, goes through the Apple and Google accounts
with you.

**Check** before you accept:
- [ ] The screens call capabilities through `call()`; no new capability was made for the app unless you asked.
- [ ] `web.php` has one new line, `Route::mobile([...])`.
- [ ] No Apple or Google key is in a file of the repository.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 18: Bookshelf for iPhones and Android phones"
git tag lesson-18
git push origin main --tags
```
Reference: [`checkpoints/lesson-18`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-18)
(only the files you write or change: run step 1 to create the app, then copy them in).
Learn more: [Phone and tablet apps](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.7-Phone-and-Tablet-Apps).

## Quiz

1. Where does the person type their password when they sign in to the phone app?
2. What does the app keep after **Allow**, and what happens to it on **Sign out**?
3. A reader without loans permissions opens **Loans**. What do they see, and which part of Bookshelf decides it?
4. Why does `npm run ios` call `http://localhost:8080` while the build in the store calls `https://bookshelf-<you>.caligrafy.com`?

<details><summary>Answers</summary>

1. In Bookshelf's own sign-in page, opened in the phone's browser: the app never sees it.
2. A personal access token of that person, named after the phone (`Mobile: iPhone ...`). Sign out revokes it on
   Bookshelf, so it stops working everywhere.
3. `Only administrators see the loans.`: `list_loans` refuses with 403, as it does for the pages, REST and the agents.
   The app only shows the answer.
4. `npm run ios` is a development build (`.env.development`, your Docker server); `npm run release:ios` is the store
   build (`.env.production`, the site online).
</details>

---
Previous: [Lesson 17 - The assistant](17-The-Assistant) · Next: [Appendix A - Routes and controllers](A-Routes-and-Controllers)
