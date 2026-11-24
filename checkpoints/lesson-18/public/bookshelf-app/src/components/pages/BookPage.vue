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
