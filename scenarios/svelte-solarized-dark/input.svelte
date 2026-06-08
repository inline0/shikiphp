<script lang="ts">
  let count = 0;
  $: doubled = count * 2;

  function increment() {
    count += 1;
  }
</script>

<button on:click={increment}>
  Clicked {count} {count === 1 ? 'time' : 'times'}
</button>

<p>Doubled: {doubled}</p>

<style>
  button {
    color: var(--accent);
  }
</style>
