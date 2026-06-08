<template>
  <div class="counter">
    <h2>{{ title }}</h2>
    <button @click="increment" :disabled="count >= max">
      Count is {{ count }}
    </button>
    <p v-if="count > 0">Clicked {{ count }} times</p>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";

const props = defineProps<{ title: string; max?: number }>();
const count = ref(0);
const max = props.max ?? 10;

function increment(): void {
  count.value++;
}
</script>

<style scoped>
.counter {
  display: flex;
  gap: 0.5rem;
  color: #42b883;
}
</style>
