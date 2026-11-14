<!-- The frame of every screen: the Caligrafy top bar, the page of the current address, then the footer
     (:key loads the page again when only the id changes, from #/books/1 to #/books/2) -->
<template>
  <show-navigation></show-navigation>
  <router-view :key="$route.fullPath"></router-view>
  <footer class="cq-footer">
    <p>Copyright &copy; {{ new Date().getFullYear() }}</p>
  </footer>
</template>

<script>
import ShowNavigation from './components/global/ShowNavigation.vue'

export default {
  name: 'App',
  components: { 'show-navigation': ShowNavigation }
}
</script>
