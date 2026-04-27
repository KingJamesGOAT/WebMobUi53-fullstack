<script setup>
  import { ref, computed, onMounted } from 'vue';
  import { useFetchApi } from '@/composables/useFetchApi';
  import { usePolling } from '@/composables/usePolling';

  // Get the fetchApi function from the composable
  const { fetchApi } = useFetchApi();

  // Reactive variables
  const token = ref('');
  const poll = ref(null);
  const selectedOptionId = ref(null);
  const successMessage = ref('');
  const errorMessage = ref('');

  // Function to fetch (or re-fetch) the poll data from the API
  async function fetchPoll() {
    // Only fetch if we have a token
    if (!token.value) return;

    try {
      const data = await fetchApi({ url: '/polls/' + token.value });
      poll.value = data;
    } catch (error) {
      errorMessage.value = 'Impossible de charger le sondage.';
    }
  }

  // When the component mounts, read the token and do the first fetch
  onMounted(() => {
    // Read the token from the HTML data attribute
    token.value = document.getElementById('app-vote').dataset.token;
    fetchPoll();
  });

  // Re-fetch the poll data every 3 seconds to get live vote counts
  usePolling(fetchPoll, 3000);

  // Computed property: total number of votes across all options
  const totalVotes = computed(() => {
    if (!poll.value || !poll.value.options) return 0;

    let total = 0;
    for (const option of poll.value.options) {
      total += option.votes_count;
    }
    return total;
  });

  // Calculate the percentage of votes for a specific option
  function getPercentage(votesCount) {
    if (totalVotes.value === 0) return 0;
    return Math.round((votesCount / totalVotes.value) * 100);
  }

  // Called when the user submits their vote
  async function submitVote() {
    // Reset messages before each attempt
    successMessage.value = '';
    errorMessage.value = '';

    try {
      // POST the selected option to the vote endpoint
      await fetchApi({
        url: '/polls/' + token.value + '/vote',
        data: { option_id: selectedOptionId.value },
      });

      successMessage.value = 'Votre vote a été enregistré !';

      // Immediately re-fetch to update the results
      await fetchPoll();
    } catch (error) {
      // Display the error message from the server (e.g. "already voted")
      errorMessage.value = error.data?.message || 'Une erreur est survenue.';
    }
  }
</script>

<template>
  <!-- Loading state -->
  <p v-if="!poll && !errorMessage">Chargement...</p>

  <!-- Error when poll could not be loaded -->
  <p v-if="!poll && errorMessage" style="color: red;">{{ errorMessage }}</p>

  <!-- Poll loaded successfully -->
  <div v-if="poll">
    <h1>{{ poll.question }}</h1>

    <!-- Vote form with radio buttons -->
    <form @submit.prevent="submitVote">
      <div v-for="option in poll.options" :key="option.id">
        <label>
          <input
            type="radio"
            :value="option.id"
            v-model="selectedOptionId"
            name="poll-option"
          />
          {{ option.label }}
        </label>
      </div>

      <button type="submit" :disabled="selectedOptionId === null" style="margin-top: 1rem;">
        Voter
      </button>
    </form>

    <!-- Success message -->
    <p v-if="successMessage" style="color: green; margin-top: 1rem;">{{ successMessage }}</p>

    <!-- Error message -->
    <p v-if="errorMessage" style="color: red; margin-top: 1rem;">{{ errorMessage }}</p>

    <!-- Live Results Section -->
    <div style="margin-top: 2rem;">
      <h2>Résultats ({{ totalVotes }} votes)</h2>

      <!-- One bar per option -->
      <div v-for="option in poll.options" :key="'result-' + option.id" style="margin-bottom: 0.75rem;">
        <!-- Option label, vote count and percentage -->
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
          <span>{{ option.label }}</span>
          <span>{{ option.votes_count }} ({{ getPercentage(option.votes_count) }}%)</span>
        </div>

        <!-- Background bar (grey track) -->
        <div style="background-color: #e5e7eb; border-radius: 4px; height: 20px; width: 100%;">
          <!-- Filled bar (teal, width set to the percentage) -->
          <div
            :style="{
              width: getPercentage(option.votes_count) + '%',
              backgroundColor: '#0d9488',
              height: '100%',
              borderRadius: '4px',
              transition: 'width 0.3s ease',
            }"
          ></div>
        </div>
      </div>
    </div>
  </div>
</template>
