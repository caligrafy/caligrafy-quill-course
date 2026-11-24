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
