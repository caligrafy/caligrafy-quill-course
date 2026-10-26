<!-- Main App Template: the router renders the pages -->
<template>
	<div id='app' v-cloak>
		<show-navigation></show-navigation>
		<div class='container'>
			<div class='shadowCoverTop color0'></div>
			<div class='shadowContainer'><div class='radialShadowTop'></div></div>
			<div class='spacer-4'></div>
			<div class='wrapper bounded-large'>
				<router-view></router-view>
			</div>
		</div>
		<div class='spacer-2'></div>
		<footer>
			<p class='small-text semi-bold horizontal-center'>Showcase &middot; Copyright &copy; {{ new Date().getFullYear() }}</p>
		</footer>
	</div>
</template>

<script setup>
import ShowNavigation from './components/global/ShowNavigation.vue'
</script>
