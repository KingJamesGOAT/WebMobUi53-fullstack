<script setup>
  // Define the 'polls' prop that comes from the parent component
  const props = defineProps({
    polls: { type: Array, default: () => [] },
  });
</script>

<template>
  <!-- Show a message if there are no polls -->
  <p v-if="polls.length === 0">Aucun sondage.</p>

  <!-- Simple table to display polls -->
  <table v-else class="w-full border-collapse text-left">
    <thead>
      <tr>
        <th class="border px-3 py-2">Titre</th>
        <th class="border px-3 py-2">Question</th>
        <th class="border px-3 py-2">Créé le</th>
      </tr>
    </thead>
    <tbody>
      <!-- Loop through each poll and display a row -->
      <tr v-for="poll in polls" :key="poll.id">
        <td class="border px-3 py-2">{{ poll.title || '-' }}</td>
        <td class="border px-3 py-2">{{ poll.question }}</td>
        <td class="border px-3 py-2">{{ poll.created_at }}</td>
      </tr>
    </tbody>
  </table>
</template>