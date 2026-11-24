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
