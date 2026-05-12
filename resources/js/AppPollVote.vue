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
  <div class="bg-white dark:bg-slate-800 shadow rounded-lg p-6 max-w-2xl mx-auto mt-8">
    
    <!-- Loading state -->
    <div v-if="!poll && !errorMessage" class="text-center py-8 text-slate-500 dark:text-slate-400">
      <p>Chargement du sondage...</p>
    </div>

    <!-- Error when poll could not be loaded -->
    <div v-if="!poll && errorMessage" class="bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 p-4 rounded-md text-center">
      <p>{{ errorMessage }}</p>
    </div>

    <!-- Poll loaded successfully -->
    <div v-if="poll" class="space-y-6">
      
      <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ poll.question }}</h1>

      <!-- Vote form with radio buttons -->
      <form @submit.prevent="submitVote" class="space-y-4">
        <div class="space-y-3">
          <div v-for="option in poll.options" :key="option.id" class="flex items-center">
            <input
              type="radio"
              :id="'option-' + option.id"
              :value="option.id"
              v-model="selectedOptionId"
              name="poll-option"
              class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-slate-300 dark:border-slate-600 dark:bg-slate-900"
            />
            <label :for="'option-' + option.id" class="ml-3 block text-base font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
              {{ option.label }}
            </label>
          </div>
        </div>

        <div class="pt-2">
          <button 
            type="submit" 
            :disabled="selectedOptionId === null" 
            class="w-full sm:w-auto bg-teal-600 hover:bg-teal-700 text-white font-medium py-2 px-6 rounded-md transition focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Voter
          </button>
        </div>
      </form>

      <!-- Success message -->
      <div v-if="successMessage" class="p-3 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-md text-sm font-medium">
        {{ successMessage }}
      </div>

      <!-- Error message -->
      <div v-if="errorMessage && poll" class="p-3 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-md text-sm font-medium">
        {{ errorMessage }}
      </div>

      <!-- Live Results Section -->
      <div class="pt-6 border-t border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold text-slate-800 dark:text-white mb-4">
          Résultats <span class="text-sm font-normal text-slate-500 dark:text-slate-400">({{ totalVotes }} votes)</span>
        </h2>

        <div class="space-y-4">
          <!-- One bar per option -->
          <div v-for="option in poll.options" :key="'result-' + option.id">
            <!-- Option label, vote count and percentage -->
            <div class="flex justify-between text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
              <span>{{ option.label }}</span>
              <span>{{ option.votes_count }} ({{ getPercentage(option.votes_count) }}%)</span>
            </div>

            <!-- Background bar (grey track) -->
            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
              <!-- Filled bar (teal, width set to the percentage) -->
              <div
                class="bg-teal-600 h-2.5 rounded-full transition-all duration-500 ease-out"
                :style="{ width: getPercentage(option.votes_count) + '%' }"
              ></div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
