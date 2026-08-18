<template>
  <div class="cards-view">
    <div class="cards-view__grid">
      <!-- panels arrive in tasks 4-6 -->
    </div>
  </div>
</template>

<script>
export default {
  name: "CardsView",
  props: {
    tasks: { type: Array, default: function () { return []; } },
    projects: { type: Array, default: function () { return []; } },
    events: { type: Array, default: function () { return []; } },
    mentions: { type: Array, default: function () { return []; } },
    signatures: { type: Array, default: function () { return []; } },
  },
};
</script>

<style scoped>
/* Flex, not grid: five equal grid columns cannot centre four items on a
   second row — that needs a half-column offset. Flex wrap plus
   justify-content: center gives it for free. */
.cards-view__grid {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-md);
  justify-content: center;
  align-items: flex-start;
}
.cards-view__grid > * {
  flex: 0 0 calc((100% - 4 * var(--spacing-md)) / 5);
}
</style>
