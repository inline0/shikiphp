<template>
  <div class="counter">
    <h1>{{ title }}</h1>
    <button @click="increment">Count: {{ count }}</button>
    <p v-if="count > 5">High!</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";

const title = "Counter";
const count = ref(0);
const doubled = computed(() => count.value * 2);

function increment(): void {
  count.value++;
}
</script>

<style scoped>
.counter {
  padding: 1rem;
  color: var(--accent, #42b883);
}
</style>
