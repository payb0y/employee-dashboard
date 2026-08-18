<template>
  <div class="iz-panel card-panel">
    <div class="card-panel__head">
      <span class="card-panel__icon"><slot name="icon"></slot></span>
      <span class="card-panel__title">{{ title }}</span>
      <span v-if="total >= 0" class="iz-badge iz-badge--accent card-panel__total">{{ total }}</span>
    </div>
    <div v-if="$slots.controls" class="card-panel__controls">
      <slot name="controls"></slot>
    </div>
    <div class="card-panel__body">
      <div v-if="total === 0" class="iz-empty card-panel__empty">{{ empty }}</div>
      <slot v-else></slot>
    </div>
  </div>
</template>

<script>
export default {
  name: "CardPanel",
  props: {
    title: { type: String, required: true },
    // -1 hides the badge entirely; 0 shows the empty state.
    total: { type: Number, default: -1 },
    empty: { type: String, default: "Nothing here" },
  },
};
</script>

<style scoped>
/* Height comes from the wall, not the contents: rows stay aligned and the
   page is the same length for two cards as for sixty. Only the body moves,
   so the title and its count stay legible while the list scrolls. */
.card-panel {
  height: 340px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  overflow: hidden;
}
.card-panel__head {
  flex: none;
  display: flex;
  align-items: center;
  gap: 8px;
}
.card-panel__icon {
  display: flex;
  color: var(--color-text-secondary);
}
.card-panel__title {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-primary);
}
.card-panel__total { margin-left: auto; }
.card-panel__controls {
  flex: none;
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}
/* min-height: 0 is required — without it a flex child refuses to shrink
   below its content and the body never scrolls. */
.card-panel__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  /* overflow-y:auto forces the computed overflow-x to auto too, so any child
     even a pixel too wide grows a horizontal scrollbar inside the card. */
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.card-panel__empty {
  margin: auto 0;
  text-align: center;
}

@media (max-width: 900px) {
  /* Released below the wall: nine fixed-height cards in one narrow column
     each trap a scroll gesture, which is unpleasant on touch. */
  .card-panel {
    height: auto;
  }
  /* The body must stop being a scroll container too. Leaving a max-height
     here keeps exactly the nested-scroll trap the release exists to remove. */
  .card-panel__body {
    overflow-y: visible;
  }
}
</style>
