<!-- The frame of every screen: the Caligrafy header, then the page of the current address
     (:key loads the page again when only the id changes, from #/books/1 to #/books/2) -->
<template>
  <show-navigation></show-navigation>
  <router-view :key="$route.fullPath"></router-view>
</template>

<script>
import ShowNavigation from './components/global/ShowNavigation.vue'

export default {
  name: 'App',
  components: { 'show-navigation': ShowNavigation }
}
</script>
